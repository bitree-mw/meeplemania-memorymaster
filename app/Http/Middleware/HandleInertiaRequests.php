<?php

namespace App\Http\Middleware;

use App\Games\StatisticsService;
use App\Models\DistrictChangeRequest;
use App\Platform\Device;
use App\Platform\DistrictChanges;
use App\Platform\GuestPlays;
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
                    'district' => $user->district,
                    'is_admin' => $user->isAdmin(),
                ] : null,
            ],
            // Compact header stats; null for guests.
            'player' => fn () => $user ? $this->playerSummary($user->id) : null,
            // How many of their free games a guest has used on this device; null for players.
            'guestPlays' => fn () => $user || ! Device::id($request) ? null : app(GuestPlays::class)->status(Device::id($request)),
            // Pop-up telling a player an admin approved or declined their district change.
            'districtNotice' => fn () => $user ? app(DistrictChanges::class)->noticeFor($user) : null,
            'pendingDistrictRequests' => fn () => $user?->isAdmin() ? DistrictChangeRequest::where('status', DistrictChangeRequest::PENDING)->count() : null,
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
