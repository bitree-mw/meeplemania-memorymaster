<?php

namespace App\Games;

use App\Models\DailyChallengeAttempt;
use App\Models\Game;
use App\Models\GameMode;
use App\Models\GameRound;
use App\Models\GameSeason;
use App\Models\GameSession;
use App\Models\User;
use App\Platform\AchievementService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Local-first play protocol, shared by every game and mode.
 *
 *  1. start()  creates a session with a secret seed and a rules snapshot, and
 *              returns the first few round specs (never the seed).
 *  2. The browser plays those rounds with no network waits and reports each
 *     result through sync() in the background; every sync response reveals
 *     more rounds ahead of the player.
 *  3. sync()   accepts results strictly in order. For each, the server
 *              regenerates the round from the seed, replays the inputs, checks
 *              tap timing and the claimed play time against its own clock, and
 *              computes the official score. Re-sent results are ignored, so
 *              retries are safe; nothing the client says about points is used.
 *  4. finish() flushes the last results and closes the run; finalize()
 *              records statistics, streaks, personal bests and achievements.
 */
class PlayService
{
    public function __construct(
        private GameRegistry $registry,
        private GameSettings $settings,
        private ScoreCalculator $calculator,
        private StatisticsService $statistics,
        private DailyChallengeService $daily,
        private LeaderboardService $leaderboards,
        private AchievementService $achievements,
    ) {}

    /** @return array{session: array, specs: array} */
    public function start(Game $game, GameMode $mode, ?User $user, ?string $guestToken, ?int $duration = null): array
    {
        if (! $game->is_active || ! $mode->is_active) {
            throw new GameException('This game mode is currently unavailable.', 'mode_unavailable', 422);
        }

        if ($mode->requires_account && ! $user) {
            throw new GameException('Sign in to play Try Once.', 'account_required', 401);
        }

        if ($mode->slug === GameMode::TIMED && ! in_array($duration, config('games.platform.timed_durations'), true)) {
            throw new GameException('Choose 30, 45 or 60 seconds.', 'invalid_duration', 422);
        }

        $this->abandonActiveSessions($user, $guestToken);

        $rules = $this->settings->game($game->slug) + [
            'fair_play' => $this->settings->platform('fair_play'),
            'mode' => $mode->slug,
            'duration' => $mode->slug === GameMode::TIMED ? $duration : null,
            'countdown' => (int) config('games.platform.countdown_seconds'),
        ];
        $seed = Rng::newSeed();
        $challenge = null;

        if ($mode->slug === GameMode::ONCE) {
            $challenge = $this->daily->today($game);
            $seed = $challenge->seed;
            $rules['once']['starting_level'] = $challenge->starting_level;
            $rules['once']['max_levels'] = $challenge->max_levels;
        }

        $level = match ($mode->slug) {
            GameMode::TIMED => 1,
            GameMode::ONCE => (int) $rules['once']['starting_level'],
            default => (int) $rules['classic']['starting_level'],
        };

        $session = DB::transaction(function () use ($game, $mode, $user, $guestToken, $rules, $seed, $challenge, $level, $duration) {
            $timed = $mode->slug === GameMode::TIMED;

            $session = GameSession::create([
                'uuid' => (string) Str::uuid(),
                'game_id' => $game->id,
                'game_mode_id' => $mode->id,
                'user_id' => $user?->id,
                'guest_token' => $user ? null : $guestToken,
                'daily_challenge_id' => $challenge?->id,
                'game_season_id' => GameSeason::covering($this->settings->today())?->id,
                'seed' => $seed,
                'rules' => $rules,
                'duration_seconds' => $timed ? $duration : null,
                'is_ranked' => $user !== null && $mode->is_ranked && ! $challenge,
                'status' => GameSession::STATUS_ACTIVE,
                'level' => $level,
                'lives' => $timed ? null : $this->livesTotal($rules),
                'revealed' => $timed ? 0 : $level - 1,
                'started_at' => now(),
                'deadline_at' => $timed
                    ? now()->addSeconds($rules['countdown'] + $duration + (int) $rules['fair_play']['timed_sync_grace_seconds'])
                    : null,
                'last_activity_at' => now(),
            ]);

            if ($challenge && $user) {
                // The unique (challenge, user) index makes this the single ranked attempt,
                // even under concurrent requests. Later runs that day are practice.
                $claimed = DailyChallengeAttempt::query()->insertOrIgnore([
                    'daily_challenge_id' => $challenge->id,
                    'user_id' => $user->id,
                    'game_session_id' => $session->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]) === 1;

                $session->update(['is_ranked' => $claimed]);
            }

            return $session;
        });

        $session->refresh();
        $specs = $this->reveal($session);
        $session->save();

        return ['session' => $this->present($session), 'specs' => $specs];
    }

    /**
     * Verify results in order and reveal more rounds.
     *
     * @param  array<int, array{key:string, inputs:int[], timings:int[]}>  $results
     */
    public function sync(GameSession $session, array $results): array
    {
        return DB::transaction(function () use ($session, $results) {
            $session = $this->lock($session);

            if ($session->isActive()) {
                $this->process($session, $results);
            }

            $specs = $session->isActive() ? $this->reveal($session) : [];
            $session->save();

            return $this->syncResponse($session, $specs);
        });
    }

    /** Flush final results and close the run. Idempotent. */
    public function finish(GameSession $session, array $results, string $reason): array
    {
        $this->sync($session, $results);

        return DB::transaction(function () use ($session, $reason) {
            $session = $this->lock($session);

            if ($session->isActive()) {
                $timeUp = $session->isTimed() && $reason === 'time_up';
                $this->finalize($session, $timeUp ? GameSession::STATUS_COMPLETED : GameSession::STATUS_ABANDONED, $timeUp ? 'time_up' : 'exited');
            }

            return $this->syncResponse($session, []);
        });
    }

    /**
     * The player paused (the exit prompt). Only a Timed Rush run has a server
     * deadline to protect; level modes are checked against elapsed time, which
     * a pause only adds to.
     */
    public function pause(GameSession $session): void
    {
        DB::transaction(function () use ($session) {
            $session = $this->lock($session);

            if ($session->isActive() && $session->isTimed() && $session->paused_at === null) {
                $session->forceFill(['paused_at' => now(), 'last_activity_at' => now()])->save();
            }
        });
    }

    /** Push the Timed Rush deadline back by the time spent paused. The client's clock was frozen too. */
    public function resume(GameSession $session): void
    {
        DB::transaction(function () use ($session) {
            $session = $this->lock($session);

            if ($session->isActive() && $session->paused_at !== null) {
                $pausedMs = (int) $session->paused_at->diffInMilliseconds(now(), true);
                $session->forceFill([
                    'deadline_at' => $session->deadline_at?->addMilliseconds($pausedMs),
                    'paused_at' => null,
                    'last_activity_at' => now(),
                ])->save();
            }
        });
    }

    /** Close runs left idle, and Timed Rush runs whose clock (plus grace) has run out. Points are kept. */
    public function expireIdleSessions(): int
    {
        $cutoff = now()->subMinutes((int) $this->settings->platform('fair_play')['session_idle_minutes']);
        $count = 0;

        GameSession::query()
            ->where('status', GameSession::STATUS_ACTIVE)
            // A paused run's clock is stopped; it only expires once idle for too long.
            ->where(fn ($q) => $q->where('last_activity_at', '<', $cutoff)->orWhere(fn ($q) => $q->where('deadline_at', '<', now())->whereNull('paused_at')))
            ->orderBy('id')
            ->each(function (GameSession $session) use (&$count) {
                DB::transaction(function () use ($session, &$count) {
                    $session = $this->lock($session);

                    if ($session->isActive()) {
                        $timedOut = $session->isTimed() && $session->deadline_at?->isPast();
                        $this->finalize($session, $timedOut ? GameSession::STATUS_COMPLETED : GameSession::STATUS_EXPIRED, $timedOut ? 'time_up' : 'idle');
                        $count++;
                    }
                });
            });

        return $count;
    }

    public function present(GameSession $session): array
    {
        $rules = $session->rules;

        return [
            'uuid' => $session->uuid,
            'game' => $this->registry->gameById($session->game_id)?->slug,
            'mode' => $rules['mode'],
            'duration' => $session->duration_seconds,
            'countdown_seconds' => $session->isTimed() ? (int) $rules['countdown'] : 0,
            'status' => $session->status,
            'is_ranked' => $session->is_ranked,
            'is_practice' => $rules['mode'] === GameMode::ONCE && ! $session->is_ranked,
            'level' => $session->level,
            'attempt' => $session->attempt,
            'lives' => $session->lives,
            'lives_total' => $session->isTimed() ? null : $this->livesTotal($rules),
            'max_level' => $this->maxLevel($session),
            'next_key' => $this->expectedKey($session),
            'score' => $this->runningScore($session),
            'rounds_played' => $session->rounds_played,
            'rounds_cleared' => $session->rounds_cleared,
            'success_streak' => $session->success_streak,
            'mistakes' => $session->incorrect_inputs,
            // Lets the client show a provisional score instantly; the server's score is official.
            'scoring' => $rules['scoring'],
        ];
    }

    public function summary(GameSession $session): array
    {
        return [
            'status' => $session->status,
            'end_reason' => $session->end_reason,
            'is_ranked' => $session->is_ranked,
            'is_flagged' => $session->is_flagged,
            'final_score' => $session->final_score,
            'base_score' => $session->base_score,
            'bonus_score' => $session->bonus_score,
            'penalty_score' => $session->penalty_score,
            'accuracy' => $session->accuracy,
            'highest_level' => $session->highest_level,
            'rounds_played' => $session->rounds_played,
            'rounds_cleared' => $session->rounds_cleared,
            'correct_inputs' => $session->correct_inputs,
            'mistakes' => $session->incorrect_inputs,
            'expected_inputs' => $session->expected_inputs,
            'avg_unit_ms' => $session->avg_unit_ms,
            'best_success_streak' => $session->best_success_streak,
            'duration' => $session->duration_seconds,
            'outcome' => $session->outcome,
        ];
    }

    private function process(GameSession $session, array $results): void
    {
        $known = $session->rounds()->pluck('key')->flip();
        $fair = $session->rules['fair_play'];

        foreach ($results as $result) {
            $key = (string) ($result['key'] ?? '');

            if (isset($known[$key])) {
                continue; // Already verified: safe retry.
            }

            if (! $session->isActive() || $key !== $this->expectedKey($session)) {
                break; // Out of order: the response tells the client where the server is.
            }

            if ($session->isTimed() && now()->greaterThan($session->deadline_at) && ! $session->is_flagged) {
                $session->is_flagged = true;
                $session->flag_reason = 'late_sync';
            }

            $this->verifyAndApply($session, $result['inputs'] ?? [], $result['timings'] ?? [], $fair);
            $known[$key] = true;
        }
    }

    private function verifyAndApply(GameSession $session, array $inputs, array $timings, array $fair): void
    {
        $definition = $this->registry->definition($this->registry->gameById($session->game_id)->slug);
        $rules = $session->rules;
        $mode = $rules['mode'];
        $timed = $session->isTimed();
        $n = $session->rounds_played + 1;

        $spec = $timed
            ? $definition->spec($rules, $session->seed, $mode, $definition->timedLevel($rules, $n), 0, $n)
            : $definition->spec($rules, $session->seed, $mode, $session->level, $session->attempt);

        $inputs = array_map('intval', array_values($inputs));
        $timings = array_map('intval', array_values($timings));

        if (count($inputs) !== count($timings) || count($inputs) > $spec['max_inputs']) {
            throw new GameException('Malformed round result.', 'malformed_result', 422);
        }

        foreach ($inputs as $value) {
            if ($value < $spec['input_min'] || $value >= $spec['input_max']) {
                throw new GameException('Malformed round result.', 'malformed_result', 422);
            }
        }

        $evaluation = $definition->evaluate($spec, $inputs);
        $responseMs = $timings === [] ? 0 : max(0, (int) end($timings));

        [$status, $failReason] = match (true) {
            $evaluation->cleared => [GameRound::STATUS_CLEARED, null],
            $evaluation->failed => [GameRound::STATUS_FAILED, 'wrong'],
            $timed => [GameRound::STATUS_EXPIRED, 'time_up'],
            default => [GameRound::STATUS_FAILED, 'timeout'],
        };

        // Claimed play time can never exceed what the server clock (or the Timed Rush clock) allows.
        $claimed = (int) $session->claimed_ms + (int) $spec['preview_ms'] + $responseMs;
        $invalid = $this->implausibleTiming($timings, $fair);

        if (! $invalid && $timed && $claimed > $session->duration_seconds * 1000 + (int) $fair['timed_clock_grace_ms']) {
            $invalid = 'clock_overrun';
        }

        if (! $invalid && ! $timed && $claimed > now()->getTimestampMs() - $session->started_at->getTimestampMs() + (int) $fair['latency_allowance_ms']) {
            $invalid = 'clock_mismatch';
        }

        if ($invalid) {
            [$status, $failReason] = [GameRound::STATUS_INVALID, 'integrity'];
        }

        $points = $this->calculator->round($rules['scoring'], $mode, $spec['level'], $spec['units'], $responseMs, $status);

        $round = $session->rounds()->create([
            'round_number' => $n,
            'key' => $spec['key'],
            'level' => $spec['level'],
            'units' => $spec['units'],
            'spec' => $spec,
            'status' => $status,
            'fail_reason' => $failReason,
            'inputs' => $inputs,
            'input_timings' => $timings,
            'correct_inputs' => $evaluation->correct,
            'mistakes' => $evaluation->mistakes,
            'preview_ms' => $spec['preview_ms'],
            'response_ms' => $responseMs,
            ...$points,
        ]);

        $this->apply($session, $round, $evaluation, $claimed, $invalid);
    }

    private function apply(GameSession $session, GameRound $round, Evaluation $evaluation, int $claimed, ?string $invalid): void
    {
        $session->rounds_played++;
        $session->claimed_ms = $claimed;
        $session->correct_inputs += $evaluation->correct;
        $session->expected_inputs += $evaluation->expected;
        $session->incorrect_inputs += $evaluation->mistakes;
        $session->base_score += $round->base_points;
        $session->bonus_score += $round->difficulty_bonus + $round->speed_bonus;
        $session->penalty_score += $round->penalty;
        $session->last_activity_at = now();
        $levelMode = ! $session->isTimed();

        if ($round->status === GameRound::STATUS_CLEARED) {
            $session->rounds_cleared++;
            $session->success_streak++;
            $session->best_success_streak = max($session->best_success_streak, $session->success_streak);
            $session->total_response_ms += $round->response_ms;
            $session->cleared_units += $round->units;
            $session->highest_level = max($session->highest_level, $round->level);

            if ($levelMode) {
                $session->level = $round->level + 1;
            }
        } elseif ($round->status !== GameRound::STATUS_EXPIRED) {
            $session->success_streak = 0;

            if ($levelMode) {
                $session->lives--;
                $session->attempt++;
            }
        }

        $session->final_score = $this->runningScore($session);

        if ($invalid) {
            $session->is_flagged = true;
            $session->flag_reason = $invalid;
            $this->finalize($session, GameSession::STATUS_FAILED, 'integrity');
        } elseif ($levelMode && $session->lives <= 0) {
            $this->finalize($session, GameSession::STATUS_FAILED, $round->fail_reason);
        } elseif ($levelMode && ($max = $this->maxLevel($session)) !== null && $session->level > $max) {
            $this->finalize($session, GameSession::STATUS_COMPLETED, 'challenge_complete');
        }
    }

    /**
     * Close the run and, for ranked play, record statistics, streaks, personal
     * bests, rank movement and achievements. Call with the session locked.
     */
    public function finalize(GameSession $session, string $status, ?string $reason): void
    {
        $session->fill([
            'status' => $status,
            'end_reason' => $reason,
            'accuracy' => $this->calculator->accuracy($session->correct_inputs, $session->expected_inputs),
            'avg_unit_ms' => $session->cleared_units > 0 ? (int) round($session->total_response_ms / $session->cleared_units) : null,
            'final_score' => $this->runningScore($session),
            'ended_at' => now(),
        ]);

        $outcome = ['personal_bests' => [], 'achievements' => [], 'rank_before' => null, 'rank_after' => null, 'daily_streak' => null];

        if ($session->user_id && $session->is_ranked && ! $session->is_flagged && $session->rounds_played > 0) {
            $session->save();
            $board = new Board(null, 'all_time');

            $rankBefore = $this->leaderboards->rankFor($board, $session->user_id);
            $recorded = $this->statistics->record($session);
            $rankAfter = $this->leaderboards->rankFor($board, $session->user_id);

            $unlocked = $this->achievements->evaluate($session->user_id, [
                'rounds_cleared_total' => $recorded['profile']->rounds_cleared,
                'best_level' => $recorded['stats']->best_level,
                'timed_score' => $session->isTimed() ? $session->final_score : null,
                'session_streak' => $session->best_success_streak,
                'daily_streak' => $recorded['profile']->current_daily_streak,
                'games_played' => $recorded['profile']->games_played,
                'games_tried' => $this->statistics->gamesTried($session->user_id),
                'all_time_rank' => $rankAfter,
            ], $session->id);

            $outcome = [
                'personal_bests' => $recorded['personal_bests'],
                'achievements' => $unlocked->map(fn ($a) => AchievementService::present($a, now()))->all(),
                'rank_before' => $rankBefore,
                'rank_after' => $rankAfter,
                'daily_streak' => $recorded['daily_streak'],
            ];
        }

        $session->outcome = $outcome;
        $session->save();
    }

    /** Round specs not yet sent, up to the lookahead. Updates the session's reveal pointer. */
    private function reveal(GameSession $session): array
    {
        $definition = $this->registry->definition($this->registry->gameById($session->game_id)->slug);
        $rules = $session->rules;
        $mode = $rules['mode'];
        $specs = [];

        if ($session->isTimed()) {
            $upto = $session->rounds_played + (int) config('games.platform.lookahead_timed_rounds');

            for ($n = $session->revealed + 1; $n <= $upto; $n++) {
                $specs[] = $definition->spec($rules, $session->seed, $mode, $definition->timedLevel($rules, $n), 0, $n);
            }
        } else {
            $upto = $session->level + (int) config('games.platform.lookahead_levels') - 1;
            if (($max = $this->maxLevel($session)) !== null) {
                $upto = min($upto, $max);
            }

            // Every remaining attempt per level, so a lost life never waits on the network.
            for ($level = $session->revealed + 1; $level <= $upto; $level++) {
                for ($attempt = $session->attempt; $attempt < $this->livesTotal($rules); $attempt++) {
                    $specs[] = $definition->spec($rules, $session->seed, $mode, $level, $attempt);
                }
            }
        }

        $session->revealed = max($session->revealed, $upto);

        return $specs;
    }

    private function syncResponse(GameSession $session, array $specs): array
    {
        return [
            'verified' => $session->rounds_played,
            'next_key' => $this->expectedKey($session),
            'session' => $this->present($session),
            'specs' => $specs,
            'summary' => $session->isActive() ? null : $this->summary($session),
        ];
    }

    private function expectedKey(GameSession $session): string
    {
        return $session->isTimed()
            ? GameDefinition::key(GameMode::TIMED, 0, 0, $session->rounds_played + 1)
            : GameDefinition::key($session->rules['mode'], $session->level, $session->attempt, 0);
    }

    private function livesTotal(array $rules): int
    {
        return max(1, (int) ($rules['mode'] === GameMode::ONCE ? $rules['once']['lives'] : $rules['classic']['lives']));
    }

    private function maxLevel(GameSession $session): ?int
    {
        $rules = $session->rules;

        return $rules['mode'] === GameMode::ONCE
            ? (int) $rules['once']['starting_level'] + (int) $rules['once']['max_levels'] - 1
            : null;
    }

    private function implausibleTiming(array $timings, array $fair): ?string
    {
        if ($timings === []) {
            return null;
        }

        if ($timings[0] < (int) $fair['min_first_tap_ms']) {
            return 'impossible_reaction_time';
        }

        for ($i = 1, $count = count($timings); $i < $count; $i++) {
            $gap = $timings[$i] - $timings[$i - 1];

            if ($gap < 0) {
                return 'non_monotonic_timings';
            }
            if ($gap < (int) $fair['min_tap_interval_ms']) {
                return 'impossible_tap_rate';
            }
        }

        return null;
    }

    private function abandonActiveSessions(?User $user, ?string $guestToken): void
    {
        if (! $user && ! $guestToken) {
            return;
        }

        GameSession::query()
            ->where('status', GameSession::STATUS_ACTIVE)
            ->when($user, fn ($q) => $q->where('user_id', $user->id), fn ($q) => $q->where('guest_token', $guestToken))
            ->get()
            ->each(fn (GameSession $s) => $this->finish($s, [], 'replaced'));
    }

    private function lock(GameSession $session): GameSession
    {
        return GameSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
    }

    private function runningScore(GameSession $session): int
    {
        return max(0, $session->base_score + $session->bonus_score - $session->penalty_score);
    }
}
