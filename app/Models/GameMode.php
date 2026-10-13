<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameMode extends Model
{
    /** "Level Builder": progressive levels with a few lives. */
    public const CLASSIC = 'classic';

    /** "Timed Rush": as many rounds as possible in 30/45/60 seconds. */
    public const TIMED = 'timed';

    /** "Try Once": one ranked attempt per day, same puzzle for everyone. */
    public const ONCE = 'once';

    public const ALL = [self::CLASSIC, self::TIMED, self::ONCE];

    public const LABELS = [
        self::CLASSIC => 'Level Builder',
        self::TIMED => 'Timed Rush',
        self::ONCE => 'Try Once',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_ranked' => 'boolean',
            'requires_account' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function label(): string
    {
        return self::LABELS[$this->slug] ?? $this->slug;
    }
}
