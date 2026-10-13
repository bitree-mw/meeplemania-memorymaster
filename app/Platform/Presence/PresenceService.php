<?php

namespace App\Platform\Presence;

use App\Games\GameSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Online / playing counts built on periodic heartbeats.
 *
 * Visitor identity: authenticated users are keyed by user id (all their tabs and
 * devices count once); guests are keyed by a hash of their session id, so tabs
 * sharing a browser session count once too.
 */
class PresenceService
{
    public function __construct(private PresenceStore $store, private GameSettings $settings) {}

    public function heartbeat(Request $request, bool $isPlaying): void
    {
        $user = $request->user();
        $key = $user ? 'u:'.$user->id : 'g:'.hash('sha256', $request->session()->getId());

        $this->store->touch($key, $user?->id, $isPlaying);
    }

    public function counts(): array
    {
        return Cache::remember('presence:counts', 10, fn () => $this->store->counts($this->threshold()));
    }

    public function isOnline(int $userId): bool
    {
        return $this->store->isOnline($userId, $this->threshold());
    }

    public function prune(): int
    {
        return $this->store->prune(max(3600, $this->threshold() * 10));
    }

    public function threshold(): int
    {
        return (int) $this->settings->platform('presence')['online_threshold_seconds'];
    }

    public function heartbeatSeconds(): int
    {
        return (int) $this->settings->platform('presence')['heartbeat_seconds'];
    }
}
