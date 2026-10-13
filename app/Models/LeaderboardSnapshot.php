<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaderboardSnapshot extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['snapshot_on' => 'date'];
    }
}
