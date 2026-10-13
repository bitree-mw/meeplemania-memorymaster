<?php

namespace App\Platform;

use App\Models\PasswordResetLink;
use App\Models\User;
use App\Notifications\PasswordResetLinkNotification;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Password reset links of the form APP_URL/reset-password/{username}/{secret}.
 * The secret is generated when the link is sent and only its SHA-256 hash is
 * stored. A link works once, until it expires (auth.passwords.users.expire
 * minutes); sending a new link cancels older ones, and expired links are
 * deleted by games:prune-reset-links.
 */
class PasswordResetLinks
{
    /** Returns false (and sends nothing) if a link was sent to this account moments ago. */
    public function send(User $user, ?User $requestedBy = null): bool
    {
        $throttle = (int) config('auth.passwords.users.throttle', 60);

        if (PasswordResetLink::where('user_id', $user->id)->where('created_at', '>', now()->subSeconds($throttle))->exists()) {
            return false;
        }

        $secret = Str::random(64);

        DB::transaction(function () use ($user, $requestedBy, $secret) {
            PasswordResetLink::where('user_id', $user->id)->delete();
            PasswordResetLink::create([
                'user_id' => $user->id,
                'token_hash' => hash('sha256', $secret),
                'requested_by' => $requestedBy?->id,
                'ip_address' => request()->ip(),
                'expires_at' => now()->addMinutes($this->expiresInMinutes()),
                'created_at' => now(),
            ]);
        });

        $user->notify(new PasswordResetLinkNotification($this->url($user, $secret), $this->expiresInMinutes(), $requestedBy !== null));

        return true;
    }

    public function url(User $user, string $secret): string
    {
        return rtrim((string) config('app.url'), '/').'/reset-password/'.rawurlencode($user->username).'/'.$secret;
    }

    /** The live link for this username + secret, or null if unknown, used or expired. */
    public function find(string $username, string $secret): ?PasswordResetLink
    {
        $user = User::where('username', $username)->first();

        if (! $user) {
            return null;
        }

        return PasswordResetLink::where('user_id', $user->id)
            ->where('token_hash', hash('sha256', $secret))
            ->where('expires_at', '>', now())
            ->first();
    }

    /** Sets the new password, burns every link for the account and signs out its other sessions. */
    public function reset(PasswordResetLink $link, string $password): User
    {
        $user = $link->user;

        DB::transaction(function () use ($user, $password) {
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            PasswordResetLink::where('user_id', $user->id)->delete();

            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
            }
        });

        event(new PasswordReset($user));

        return $user;
    }

    public function prune(): int
    {
        return PasswordResetLink::where('expires_at', '<=', now())->delete();
    }

    public function expiresInMinutes(): int
    {
        return (int) config('auth.passwords.users.expire', 60);
    }
}
