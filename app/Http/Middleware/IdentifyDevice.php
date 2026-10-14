<?php

namespace App\Http\Middleware;

use App\Platform\Device;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives every browser a long-lived device id (see App\Platform\Device). It is
 * mirrored in the session so it holds for the whole visit even when the
 * browser refuses the cookie.
 */
class IdentifyDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $fromCookie = $request->cookie(Device::COOKIE);
        $id = Device::isValid($fromCookie) ? $fromCookie : $request->session()->get(Device::COOKIE);

        if (! Device::isValid($id)) {
            $id = Str::random(40);
        }

        $request->attributes->set(Device::COOKIE, $id);
        $request->session()->put(Device::COOKIE, $id);

        $response = $next($request);

        if ($fromCookie !== $id) {
            $response->headers->setCookie(cookie(Device::COOKIE, $id, Device::MINUTES));
        }

        return $response;
    }
}
