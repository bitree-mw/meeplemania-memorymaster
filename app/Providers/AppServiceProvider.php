<?php

namespace App\Providers;

use App\Games\GameRegistry;
use App\Games\GameSettings;
use App\Models\User;
use App\Platform\Presence\DatabasePresenceStore;
use App\Platform\Presence\PresenceStore;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GameRegistry::class);
        $this->app->singleton(GameSettings::class);

        // Swap for a Redis-backed store later without touching gameplay code.
        $this->app->singleton(PresenceStore::class, DatabasePresenceStore::class);
    }

    public function boot(): void
    {
        Gate::define('access-admin', fn (User $user) => $user->isAdmin() && ! $user->isDisabled());

        $byPlayer = fn (Request $request) => $request->user()?->id ?: $request->ip();

        RateLimiter::for('game-start', fn (Request $request) => Limit::perMinute(20)->by($byPlayer($request)));
        RateLimiter::for('game-round', fn (Request $request) => Limit::perMinute(240)->by($byPlayer($request)));
        RateLimiter::for('presence', fn (Request $request) => Limit::perMinute(10)->by($request->session()->getId()));
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(6)->by(strtolower((string) $request->input('email')).'|'.$request->ip()));
    }
}
