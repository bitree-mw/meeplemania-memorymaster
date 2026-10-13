<?php

namespace App\Platform;

use App\Models\Achievement;
use App\Models\PlayerAchievement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Platform-wide achievements. Each names a criteria metric and a threshold
 * (editable by admins). After a run, the game supplies a context of metric
 * values; anything crossing its threshold is unlocked exactly once (enforced
 * by a unique index on user + achievement).
 */
class AchievementService
{
    /**
     * "min" metrics unlock at value >= threshold; "max" (rank) metrics at value <= threshold.
     */
    public const CRITERIA = [
        'rounds_cleared_total' => ['direction' => 'min', 'label' => 'Rounds cleared across all games'],
        'best_level' => ['direction' => 'min', 'label' => 'Level Builder level reached in any game'],
        'timed_score' => ['direction' => 'min', 'label' => 'Timed Rush score in one run'],
        'session_streak' => ['direction' => 'min', 'label' => 'Rounds cleared in a row in one run'],
        'daily_streak' => ['direction' => 'min', 'label' => 'Consecutive days played'],
        'games_played' => ['direction' => 'min', 'label' => 'Ranked games played'],
        'games_tried' => ['direction' => 'min', 'label' => 'Different games played'],
        'all_time_rank' => ['direction' => 'max', 'label' => 'All-time leaderboard rank'],
        'daily_rank' => ['direction' => 'max', 'label' => 'Try Once finishing position'],
    ];

    /** Badge icons an admin can pick; mirrors ICONS in resources/js/game/palette.js. */
    public const ICONS = [
        'Trophy', 'Medal', 'Award', 'Crown', 'Star', 'Sparkles', 'Zap', 'Flame', 'Layers', 'LayoutGrid', 'Eye',
        'TrendingUp', 'Gamepad2', 'Compass', 'Dices', 'ShieldCheck', 'Gem', 'Heart', 'Clover', 'Anchor', 'Hourglass', 'Rocket', 'Ghost',
    ];

    /**
     * @param  array<string, int|null>  $context  metric => value
     * @return Collection<int, Achievement> Newly unlocked achievements.
     */
    public function evaluate(int $userId, array $context, ?int $sessionId = null): Collection
    {
        $unlocked = PlayerAchievement::where('user_id', $userId)->pluck('achievement_id');

        return Achievement::query()
            ->where('is_active', true)
            ->whereIn('criteria', array_keys($context))
            ->whereNotIn('id', $unlocked)
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (Achievement $a) => $this->meets($a, $context[$a->criteria]))
            ->filter(fn (Achievement $a) => $this->unlock($userId, $a, $sessionId))
            ->values();
    }

    /** Returns true only when this call created the unlock. */
    public function unlock(int $userId, Achievement $achievement, ?int $sessionId = null): bool
    {
        return DB::table('player_achievements')->insertOrIgnore([
            'user_id' => $userId,
            'achievement_id' => $achievement->id,
            'game_session_id' => $sessionId,
            'unlocked_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]) === 1;
    }

    public function meets(Achievement $achievement, ?int $value): bool
    {
        if ($value === null) {
            return false;
        }

        $direction = self::CRITERIA[$achievement->criteria]['direction'] ?? 'min';

        return $direction === 'max'
            ? $value >= 1 && $value <= $achievement->threshold
            : $value >= $achievement->threshold;
    }

    public static function present(Achievement $achievement, $unlockedAt = null): array
    {
        return [
            'key' => $achievement->key,
            'name' => $achievement->name,
            'description' => $achievement->description,
            'icon' => $achievement->icon,
            'unlocked_at' => $unlockedAt?->toIso8601String(),
        ];
    }
}
