/**
 * Provisional scoring, mirroring App\Games\ScoreCalculator so the player sees
 * points instantly. The server recalculates everything; its score is official.
 */
export function speedBonus(units, responseMs, targetPerUnitMs, bonusPerUnit) {
    const target = Math.max(1, units * targetPerUnitMs);
    const ratio = Math.max(0, Math.min(1, 1 - responseMs / target));
    return Math.round(units * bonusPerUnit * ratio);
}

/** @returns {{base_points:number, difficulty_bonus:number, speed_bonus:number, penalty:number, points:number}} */
export function roundPoints(scoring, mode, level, units, responseMs, status) {
    const timed = mode === 'timed';
    const breakdown = (base = 0, difficulty = 0, speed = 0, penalty = 0) => ({
        base_points: base,
        difficulty_bonus: difficulty,
        speed_bonus: speed,
        penalty,
        points: base + difficulty + speed - penalty,
    });

    if (status === 'failed' && timed) return breakdown(0, 0, 0, scoring.timed_mistake_penalty);
    if (status !== 'cleared') return breakdown();

    const speed = speedBonus(units, responseMs, scoring.speed_target_ms_per_unit, scoring.speed_bonus_per_unit);

    return timed
        ? breakdown(scoring.timed_base_points, scoring.timed_points_per_unit * units, speed)
        : breakdown(scoring.base_points, scoring.points_per_level * level, speed);
}
