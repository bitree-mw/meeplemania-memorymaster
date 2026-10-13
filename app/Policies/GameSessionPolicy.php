<?php

namespace App\Policies;

use App\Models\GameSession;
use App\Models\User;

class GameSessionPolicy
{
    /**
     * Only the player who started a session may play or inspect it. Guest
     * sessions are bound to the guest token kept in the visitor's web session.
     */
    public function play(?User $user, GameSession $session): bool
    {
        if ($session->user_id !== null) {
            return $user !== null && $user->id === $session->user_id;
        }

        $guestToken = request()->session()->get('mm_guest_token');

        return $user === null && $guestToken !== null && hash_equals($session->guest_token ?? '', $guestToken);
    }
}
