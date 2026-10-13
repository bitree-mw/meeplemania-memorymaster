<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Platform-wide totals across all games, and the daily activity streak. */
class PlayerProfile extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['last_active_on' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
