<?php

namespace App\Games;

use App\Models\Game;
use App\Platform\Districts;

/**
 * Identifies one leaderboard: a game (or all games) + category (+ Timed Rush duration),
 * country-wide or limited to one district.
 */
final class Board
{
    /** Categories available per game. */
    public const GAME_CATEGORIES = ['level', 'timed', 'once', 'fastest', 'weekly', 'all_time'];

    /** Categories available across all games. */
    public const PLATFORM_CATEGORIES = ['all_time', 'weekly', 'season'];

    public const LABELS = [
        'level' => 'Highest Level',
        'timed' => 'Timed Rush',
        'once' => 'Try Once · Today',
        'fastest' => 'Fastest',
        'weekly' => 'Weekly',
        'season' => 'Season',
        'all_time' => 'All-Time',
    ];

    public const RULES = [
        'level' => 'Highest Level Builder level cleared. Ties: whoever reached it first.',
        'timed' => 'Best single Timed Rush score at this duration. Ties: whoever set it first.',
        'once' => "Today's Try Once score. Ties: higher level, then lower total response time, then earlier finish.",
        'fastest' => 'Lowest average answer time per item in a Level Builder run reaching level 5+. Ties: whoever set it first.',
        'weekly' => 'Points from ranked games this week (Monday start). Ties: fewer games played, then earlier sign-up.',
        'season' => 'Points from ranked games in the current season. Ties: fewer games played, then earlier sign-up.',
        'all_time' => 'Lifetime points from ranked games. Ties: fewer games played, then earlier sign-up.',
    ];

    public function __construct(
        public readonly ?Game $game,
        public readonly string $category,
        public readonly ?int $duration = null,
        // Malawi district or region slug (App\Platform\Districts); both null = country-wide standings.
        public readonly ?string $district = null,
        public readonly ?string $region = null,
    ) {}

    public function key(): string
    {
        return ($this->game?->slug ?? 'all').':'.$this->category.($this->category === 'timed' ? ':'.$this->duration : '')
            .($this->district ? '@'.$this->district : ($this->region ? '@region-'.$this->region : ''));
    }

    public function isPlatform(): bool
    {
        return $this->game === null;
    }

    public function label(): string
    {
        return self::LABELS[$this->category].($this->category === 'timed' ? " · {$this->duration}s" : '')
            .($this->district ? ' · '.Districts::name($this->district) : ($this->region ? ' · '.Districts::regions()[$this->region] : ''));
    }

    public function rule(): string
    {
        return self::RULES[$this->category];
    }

    /** Daily snapshots (movement arrows) are not taken for the one-day Try Once board. */
    public function tracksMovement(): bool
    {
        return $this->category !== 'once';
    }
}
