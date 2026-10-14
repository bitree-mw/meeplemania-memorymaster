<?php

namespace App\Platform;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * A long-lived random id for this browser, set by IdentifyDevice. Guest games
 * are tied to it (so the guest play allowance survives closing the browser),
 * and it lets a device hold only one signed-in account at a time.
 *
 * A different browser or a private window has its own cookies, so it counts as
 * a different device; clearing cookies starts a new one.
 */
class Device
{
    public const COOKIE = 'mm_device';

    public const MINUTES = 60 * 24 * 365 * 5;

    public const BUSY_MESSAGE = 'Another account is already signed in on this device. Sign out of it first.';

    /** How long a device remembers which session is signed in on it. */
    private const LOCK_MINUTES = 60 * 24 * 30;

    public static function id(Request $request): ?string
    {
        return $request->attributes->get(self::COOKIE);
    }

    public static function isValid(mixed $id): bool
    {
        return is_string($id) && preg_match('/^[A-Za-z0-9]{40}$/', $id) === 1;
    }

    /** Record that the current (just signed-in) session holds this device. */
    public function claim(Request $request): void
    {
        if ($id = self::id($request)) {
            Cache::put($this->lockKey($id), $request->session()->getId(), now()->addMinutes(self::LOCK_MINUTES));
        }
    }

    /** Free the device when its signed-in session signs out. */
    public function release(Request $request): void
    {
        $id = self::id($request);

        if ($id && Cache::get($this->lockKey($id)) === $request->session()->getId()) {
            Cache::forget($this->lockKey($id));
        }
    }

    /**
     * The user signed in on this device in another session that is still live,
     * or null when the device is free. A session that expired or signed out frees it.
     */
    public function signedInUserId(Request $request): ?int
    {
        $id = self::id($request);
        $sessionId = $id ? Cache::get($this->lockKey($id)) : null;

        if (! is_string($sessionId) || $sessionId === $request->session()->getId()) {
            return null;
        }

        // Load the other session through the same handler (and encryption) without touching ours.
        $other = clone $request->session();
        $other->flush();
        $other->setId($sessionId);
        $other->start();

        $userId = $other->get(Auth::guard('web')->getName());

        if (! $userId) {
            Cache::forget($this->lockKey($id));

            return null;
        }

        return (int) $userId;
    }

    private function lockKey(string $id): string
    {
        return "device-session:{$id}";
    }
}
