<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Achievement extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function players(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'player_achievements')->withPivot('unlocked_at');
    }
}
