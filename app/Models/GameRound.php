<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameRound extends Model
{
    public const STATUS_CLEARED = 'cleared';

    public const STATUS_FAILED = 'failed';

    /** Timed Rush round cut short by the clock: no points, no penalty. */
    public const STATUS_EXPIRED = 'expired';

    public const STATUS_INVALID = 'invalid';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'spec' => 'array',
            'inputs' => 'array',
            'input_timings' => 'array',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(GameSession::class, 'game_session_id');
    }
}
