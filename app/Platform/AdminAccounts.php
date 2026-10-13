<?php

namespace App\Platform;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creating and promoting administrators. At most User::MAX_ADMINS accounts may
 * hold the admin role (disabled ones included); the check runs under a row
 * lock so two simultaneous requests cannot both take the last slot.
 */
class AdminAccounts
{
    public function count(): int
    {
        return User::where('role', User::ROLE_ADMIN)->count();
    }

    public function slotsLeft(): int
    {
        return max(0, User::MAX_ADMINS - $this->count());
    }

    /** @param  array{username: string, email: string, password: string, district?: ?string}  $data */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $this->ensureSlotAvailable();

            $user = User::create($data);
            $user->forceFill(['role' => User::ROLE_ADMIN, 'email_verified_at' => now()])->save();

            return $user;
        });
    }

    public function promote(User $user): void
    {
        if ($user->isAdmin()) {
            return;
        }

        DB::transaction(function () use ($user) {
            $this->ensureSlotAvailable();
            $user->forceFill(['role' => User::ROLE_ADMIN])->save();
        });
    }

    public function revoke(User $user): void
    {
        $user->forceFill(['role' => User::ROLE_PLAYER])->save();
    }

    private function ensureSlotAvailable(): void
    {
        $admins = User::where('role', User::ROLE_ADMIN)->lockForUpdate()->count();

        if ($admins >= User::MAX_ADMINS) {
            throw ValidationException::withMessages([
                'admin' => 'The limit of '.User::MAX_ADMINS.' admin accounts has been reached. Revoke admin access from an account first.',
            ]);
        }
    }
}
