<?php

namespace App\Http\Middleware;

use App\Games\StatisticsService;
use App\Platform\Presence\PresenceService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    ...$user->toPublicArray(),
                    'email' => $user->email,
                    'is_admin' => $user->isAdmin(),
                ] : null,
            ],
            // Compact header stats; null for guests.
            'player' => fn () => $user ? $this->playerSummary($user->id) : null,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'presence' => fn () => ['heartbeat_seconds' => app(PresenceService::class)->heartbeatSeconds()],
        ];
    }

    private function playerSummary(int $userId): array
    {
        $statistics = app(StatisticsService::class);
        $profile = $statistics->profile($userId);

        return [
            'daily_streak' => $statistics->displayDailyStreak($profile),
            'total_points' => (int) ($profile->total_points ?? 0),
        ];
    }
}
