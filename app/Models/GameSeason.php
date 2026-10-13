<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameSeason extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    /** The season covering the given Y-m-d date, if any. */
    public static function covering(string $date): ?self
    {
        return static::query()
            ->whereDate('starts_on', '<=', $date)
            ->whereDate('ends_on', '>=', $date)
            ->orderByDesc('starts_on')
            ->first();
    }
}
