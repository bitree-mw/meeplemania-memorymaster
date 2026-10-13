<?php

namespace App\Games;

use App\Models\GameMode;
use App\Models\GameSession;
use App\Models\PlayerProfile;
use App\Models\PlayerStatistic;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Per-game statistics and personal bests (player_statistics) and platform-wide
 * totals (player_profiles), both derived from finalized ranked sessions.
 *
 * Two streaks are tracked and never mixed:
 *  - Daily activity streak: consecutive calendar days (game timezone) with a
 *    qualifying ranked session in any game. Stored on the profile.
 *  - Session success streak: consecutive cleared rounds inside one run. Stored
 *    on the session; the best ever is copied to the game statistics.
 */
class StatisticsService
{
    public function __construct(private GameSettings $settings) {}

    public function for(int $userId, int $gameId): ?PlayerStatistic
    {
        return PlayerStatistic::where('user_id', $userId)->where('game_id', $gameId)->first();
    }

    /** @return Collection<int, PlayerStatistic> keyed by game_id */
    public function allFor(int $userId)
    {
        return PlayerStatistic::where('user_id', $userId)->get()->keyBy('game_id');
    }

    public function profile(int $userId): ?PlayerProfile
    {
        return PlayerProfile::where('user_id', $userId)->first();
    }

    /** Number of different games the player has finished a ranked run in. */
    public function gamesTried(int $userId): int
    {
        return PlayerStatistic::where('user_id', $userId)->where('games_played', '>', 0)->count();
    }

    /**
     * Apply a finalized ranked session. Must run inside the finalize transaction.
     *
     * @return array{personal_bests: string[], daily_streak: int, stats: PlayerStatistic, profile: PlayerProfile}
     */
    public function record(GameSession $session): array
    {
        PlayerStatistic::firstOrCreate(['user_id' => $session->user_id, 'game_id' => $session->game_id]);
        PlayerProfile::firstOrCreate(['user_id' => $session->user_id]);

        $stats = PlayerStatistic::where('user_id', $session->user_id)->where('game_id', $session->game_id)->lockForUpdate()->first();
        $profile = PlayerProfile::where('user_id', $session->user_id)->lockForUpdate()->first();

        $bests = $this->accumulate($stats, $profile, $session);
        $stats->save();
        $profile->save();

        return ['personal_bests' => $bests, 'daily_streak' => $profile->current_daily_streak, 'stats' => $stats, 'profile' => $profile];
    }

    /** Recompute a player's statistics from scratch (after an admin voids or approves a result). */
    public function rebuild(User $user): void
    {
        DB::transaction(function () use ($user) {
            PlayerStatistic::where('user_id', $user->id)->delete();
            PlayerProfile::where('user_id', $user->id)->delete();

            $profile = new PlayerProfile(['user_id' => $user->id]);
            $stats = [];

            GameSession::query()
                ->countsForRanking()
                ->where('user_id', $user->id)
                ->where('rounds_played', '>', 0)
                ->orderBy('ended_at')
                ->orderBy('id')
                ->each(function (GameSession $session) use (&$stats, $profile) {
                    $stats[$session->game_id] ??= new PlayerStatistic(['user_id' => $session->user_id, 'game_id' => $session->game_id]);
                    $this->accumulate($stats[$session->game_id], $profile, $session);
                });

            foreach ($stats as $row) {
                $row->save();
            }
            $profile->save();
        });
    }

    /** The streak as it should be shown today: a missed day shows as broken. */
    public function displayDailyStreak(?PlayerProfile $profile): int
    {
        if (! $profile || ! $profile->last_active_on) {
            return 0;
        }

        $today = $this->settings->now()->startOfDay();
        $last = CarbonImmutable::parse($profile->last_active_on->toDateString(), $this->settings->timezone());

        return $last->diffInDays($today) <= 1 ? $profile->current_daily_streak : 0;
    }

    /** @return string[] Personal-best categories improved by this session. */
    private function accumulate(PlayerStatistic $stats, PlayerProfile $profile, GameSession $session): array
    {
        $mode = $session->rules['mode'];
        $at = $session->ended_at ?? now();
        $bests = [];

        foreach ([$stats, $profile] as $row) {
            $row->games_played = (int) $row->games_played + 1;
            $row->total_points = (int) $row->total_points + $session->final_score;
            $row->rounds_cleared = (int) $row->rounds_cleared + $session->rounds_cleared;
        }

        $stats->correct_inputs = (int) $stats->correct_inputs + $session->correct_inputs;
        $stats->expected_inputs = (int) $stats->expected_inputs + $session->expected_inputs;
        $stats->best_session_streak = max((int) $stats->best_session_streak, $session->best_success_streak);

        if ($mode === GameMode::CLASSIC) {
            if ($session->highest_level > (int) $stats->best_level) {
                $stats->best_level = $session->highest_level;
                $stats->best_level_at = $at;
                $bests[] = 'level';
            }

            if ($session->final_score > (int) $stats->best_score) {
                $stats->best_score = $session->final_score;
                $stats->best_score_at = $at;
                $bests[] = 'score';
            }

            $qualifies = $session->avg_unit_ms !== null && $session->highest_level >= (int) config('games.platform.fastest_min_level');

            if ($qualifies && ($stats->best_avg_unit_ms === null || $session->avg_unit_ms < $stats->best_avg_unit_ms)) {
                $stats->best_avg_unit_ms = $session->avg_unit_ms;
                $stats->best_avg_unit_at = $at;
                $bests[] = 'speed';
            }
        }

        if ($mode === GameMode::TIMED && in_array($session->duration_seconds, config('games.platform.timed_durations'), true)) {
            $column = 'best_timed_'.$session->duration_seconds;

            if ($session->final_score > (int) $stats->{$column}) {
                $stats->{$column} = $session->final_score;
                $stats->{$column.'_at'} = $at;
                $bests[] = 'timed';
            }
        }

        $minRounds = (int) $this->settings->platform('streaks')['min_rounds_for_qualifying_session'];

        if ($session->rounds_played >= $minRounds) {
            $this->applyActivityDay($profile, CarbonImmutable::parse($at)->setTimezone($this->settings->timezone())->toDateString());
        }

        return $bests;
    }

    private function applyActivityDay(PlayerProfile $profile, string $day): void
    {
        $last = $profile->last_active_on?->toDateString();

        if ($last !== null && $day <= $last) {
            return;
        }

        $yesterday = CarbonImmutable::parse($day)->subDay()->toDateString();

        $profile->current_daily_streak = $last === $yesterday ? (int) $profile->current_daily_streak + 1 : 1;
        $profile->longest_daily_streak = max((int) $profile->longest_daily_streak, $profile->current_daily_streak);
        $profile->last_active_on = $day;
    }
}
