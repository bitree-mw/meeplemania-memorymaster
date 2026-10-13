<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerStatistic extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'best_level_at' => 'datetime',
            'best_score_at' => 'datetime',
            'best_avg_unit_at' => 'datetime',
            'best_timed_30_at' => 'datetime',
            'best_timed_45_at' => 'datetime',
            'best_timed_60_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function accuracy(): float
    {
        return $this->expected_inputs > 0
            ? round($this->correct_inputs / $this->expected_inputs * 100, 1)
            : 0.0;
    }
}
