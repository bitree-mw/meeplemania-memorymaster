<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OnlinePresence extends Model
{
    protected $table = 'online_presence';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_playing' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }
}
