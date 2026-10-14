<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Hidden(['seed'])]
class GameSession extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_ABANDONED = 'abandoned';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_VOIDED = 'voided';

    /** Statuses that represent a finished, countable run. */
    public const FINISHED_STATUSES = [
        self::STATUS_COMPLETED,
        self::STATUS_FAILED,
        self::STATUS_ABANDONED,
        self::STATUS_EXPIRED,
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'seed' => 'encrypted',
            'rules' => 'array',
            'outcome' => 'array',
            'is_ranked' => 'boolean',
            'is_flagged' => 'boolean',
            'accuracy' => 'float',
            'started_at' => 'datetime',
            'deadline_at' => 'datetime',
            'paused_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'ended_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function mode(): BelongsTo
    {
        return $this->belongsTo(GameMode::class, 'game_mode_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dailyChallenge(): BelongsTo
    {
        return $this->belongsTo(DailyChallenge::class);
    }

    public function rounds(): HasMany
    {
        return $this->hasMany(GameRound::class)->orderBy('round_number');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function modeSlug(): string
    {
        return $this->rules['mode'];
    }

    public function isTimed(): bool
    {
        return $this->modeSlug() === GameMode::TIMED;
    }

    /** Finished, ranked, unflagged and not voided: eligible for leaderboards and statistics. */
    public function scopeCountsForRanking(Builder $query): void
    {
        $query->where('is_ranked', true)
            ->where('is_flagged', false)
            ->whereIn('status', self::FINISHED_STATUSES);
    }

    public function durationSeconds(): ?int
    {
        return $this->ended_at ? (int) $this->started_at->diffInSeconds($this->ended_at) : null;
    }
}
