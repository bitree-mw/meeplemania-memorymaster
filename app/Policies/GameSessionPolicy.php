<?php

namespace App\Policies;

use App\Models\GameSession;
use App\Models\User;
use App\Platform\Device;

class GameSessionPolicy
{
    /**
     * Only the player who started a session may play or inspect it. Guest
     * sessions are bound to the visitor's device id (see App\Platform\Device).
     */
    public function play(?User $user, GameSession $session): bool
    {
        if ($session->user_id !== null) {
            return $user !== null && $user->id === $session->user_id;
        }

        $guestToken = Device::id(request());

        return $user === null && $guestToken !== null && hash_equals($session->guest_token ?? '', $guestToken);
    }
}
