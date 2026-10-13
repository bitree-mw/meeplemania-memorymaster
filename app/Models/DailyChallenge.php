<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Hidden(['seed'])]
class DailyChallenge extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'challenge_date' => 'date',
            'seed' => 'encrypted',
            'rewards_granted_at' => 'datetime',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(DailyChallengeAttempt::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(GameSession::class);
    }
}
