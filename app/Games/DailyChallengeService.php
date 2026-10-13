<?php

namespace App\Games;

use App\Models\DailyChallenge;
use App\Models\DailyChallengeAttempt;
use App\Models\Game;
use App\Models\GameSession;
use App\Platform\AchievementService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Try Once: one deterministic challenge per game per calendar day (game
 * timezone), one ranked attempt per player, badges for the top finishers.
 */
class DailyChallengeService
{
    public function __construct(
        private GameRegistry $registry,
        private GameSettings $settings,
        private AchievementService $achievements,
    ) {}

    public function today(Game $game): DailyChallenge
    {
        return $this->forDate($game, $this->settings->today());
    }

    /** Fetch or create a game's challenge for a date. Safe under concurrent requests. */
    public function forDate(Game $game, string $date): DailyChallenge
    {
        $existing = DailyChallenge::where('game_id', $game->id)->whereDate('challenge_date', $date)->first();

        if ($existing) {
            return $existing;
        }

        $once = $this->settings->game($game->slug)['once'];

        $challenge = DailyChallenge::createOrFirst(
            ['game_id' => $game->id, 'challenge_date' => $date],
            [
                'seed' => self::seed($game->slug, $date),
                'starting_level' => (int) $once['starting_level'],
                'max_levels' => (int) $once['max_levels'],
            ],
        );

        // First challenge of a new day: settle earlier days, in case the scheduler isn't running.
        if ($challenge->wasRecentlyCreated) {
            $this->grantPendingRewards();
        }

        return $challenge;
    }

    /** Identical for every player of a game on a date; reproducible for verification. */
    public static function seed(string $slug, string $date): string
    {
        return hash_hmac('sha256', "meeple-mania:daily:{$slug}:{$date}", (string) config('app.key'));
    }

    public function attemptFor(DailyChallenge $challenge, int $userId): ?DailyChallengeAttempt
    {
        return DailyChallengeAttempt::where('daily_challenge_id', $challenge->id)
            ->where('user_id', $userId)
            ->with('session')
            ->first();
    }

    /** Score, then level reached, then total response time, then who finished first. */
    public function standingsQuery(DailyChallenge $challenge): Builder
    {
        return GameSession::query()
            ->countsForRanking()
            ->where('daily_challenge_id', $challenge->id)
            ->where('rounds_played', '>', 0)
            ->join('users', 'users.id', '=', 'game_sessions.user_id')
            ->whereNull('users.disabled_at')
            ->orderByDesc('final_score')
            ->orderByDesc('highest_level')
            ->orderBy('total_response_ms')
            ->orderBy('ended_at')
            ->orderBy('game_sessions.id');
    }

    /** Award Try Once achievements for every finished day not yet settled. */
    public function grantPendingRewards(): int
    {
        $settled = 0;

        DailyChallenge::query()
            ->whereNull('rewards_granted_at')
            ->whereDate('challenge_date', '<', $this->settings->today())
            ->orderBy('challenge_date')
            ->each(function (DailyChallenge $challenge) use (&$settled) {
                DB::transaction(function () use ($challenge) {
                    $locked = DailyChallenge::whereKey($challenge->id)->lockForUpdate()->first();

                    if ($locked->rewards_granted_at !== null) {
                        return;
                    }

                    $sessions = $this->standingsQuery($locked)->select('game_sessions.*')->limit(3)->get();

                    foreach ($sessions->values() as $index => $session) {
                        $this->achievements->evaluate($session->user_id, ['daily_rank' => $index + 1], $session->id);
                    }

                    $locked->update(['rewards_granted_at' => now()]);
                });
                $settled++;
            });

        return $settled;
    }
}
