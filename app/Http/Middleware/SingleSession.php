<?php

namespace App\Http\Middleware;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Session\Middleware\AuthenticateSession;

/**
 * One device per account. Signing in calls Auth::logoutOtherDevices(), which
 * re-hashes the password; every other session (and "keep me signed in" cookie)
 * then holds a stale hash, so this middleware signs it out on its next request
 * and tells the player why.
 */
class SingleSession extends AuthenticateSession
{
    public const SIGNED_OUT_MESSAGE = 'You were signed out because your account was signed in on another device.';

    protected function logout($request)
    {
        $this->guard()->logoutCurrentDevice();

        $request->session()->flush();
        $request->session()->flash('error', self::SIGNED_OUT_MESSAGE);

        throw new AuthenticationException(
            self::SIGNED_OUT_MESSAGE, [$this->auth->getDefaultDriver()], $this->redirectTo($request)
        );
    }
}
