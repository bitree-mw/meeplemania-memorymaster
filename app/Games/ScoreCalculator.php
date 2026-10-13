<?php

namespace App\Games;

use App\Models\GameMode;
use App\Models\GameRound;

/**
 * Authoritative scoring, shared by every game. The client mirrors these
 * formulas only to show a provisional score instantly (resources/js/game/scoring.js).
 *
 * Level modes:  cleared round = base + points_per_level × level + speed bonus; failed = 0.
 * Timed Rush:   cleared round = timed base + per-unit points × units + speed bonus;
 *               failed = −penalty; expired (cut off by the clock) = 0.
 * Speed bonus:  up to speed_bonus_per_unit × units, falling linearly to zero at
 *               speed_target_ms_per_unit × units.
 */
class ScoreCalculator
{
    /**
     * @return array{base_points:int, difficulty_bonus:int, speed_bonus:int, penalty:int, points:int}
     */
    public function round(array $scoring, string $mode, int $level, int $units, int $responseMs, string $status): array
    {
        $timed = $mode === GameMode::TIMED;

        if ($status === GameRound::STATUS_FAILED && $timed) {
            return $this->breakdown(penalty: (int) $scoring['timed_mistake_penalty']);
        }

        if ($status !== GameRound::STATUS_CLEARED) {
            return $this->breakdown();
        }

        $speed = $this->speedBonus($units, $responseMs, (int) $scoring['speed_target_ms_per_unit'], (int) $scoring['speed_bonus_per_unit']);

        return $timed
            ? $this->breakdown((int) $scoring['timed_base_points'], (int) $scoring['timed_points_per_unit'] * $units, $speed)
            : $this->breakdown((int) $scoring['base_points'], (int) $scoring['points_per_level'] * $level, $speed);
    }

    public function speedBonus(int $units, int $responseMs, int $targetPerUnitMs, int $bonusPerUnit): int
    {
        $target = max(1, $units * $targetPerUnitMs);
        $ratio = max(0.0, min(1.0, 1 - $responseMs / $target));

        return (int) round($units * $bonusPerUnit * $ratio);
    }

    public function accuracy(int $correct, int $expected): float
    {
        return $expected > 0 ? round($correct / $expected * 100, 2) : 0.0;
    }

    private function breakdown(int $base = 0, int $difficulty = 0, int $speed = 0, int $penalty = 0): array
    {
        return [
            'base_points' => $base,
            'difficulty_bonus' => $difficulty,
            'speed_bonus' => $speed,
            'penalty' => $penalty,
            'points' => $base + $difficulty + $speed - $penalty,
        ];
    }
}
