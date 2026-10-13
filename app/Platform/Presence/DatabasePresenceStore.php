<?php

namespace App\Platform\Presence;

use App\Models\OnlinePresence;

class DatabasePresenceStore implements PresenceStore
{
    public function touch(string $visitorKey, ?int $userId, bool $isPlaying): void
    {
        $now = now();

        OnlinePresence::upsert(
            [[
                'visitor_key' => $visitorKey,
                'user_id' => $userId,
                'is_playing' => $isPlaying,
                'last_seen_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['visitor_key'],
            ['user_id', 'is_playing', 'last_seen_at', 'updated_at'],
        );
    }

    public function counts(int $thresholdSeconds): array
    {
        $row = OnlinePresence::query()
            ->where('last_seen_at', '>=', now()->subSeconds($thresholdSeconds))
            ->selectRaw('COUNT(user_id) as players')
            ->selectRaw('SUM(CASE WHEN user_id IS NULL THEN 1 ELSE 0 END) as guests')
            ->selectRaw('SUM(CASE WHEN is_playing = 1 THEN 1 ELSE 0 END) as playing')
            ->first();

        $players = (int) $row->players;
        $guests = (int) $row->guests;

        return [
            'online' => $players + $guests,
            'players' => $players,
            'guests' => $guests,
            'playing' => (int) $row->playing,
        ];
    }

    public function isOnline(int $userId, int $thresholdSeconds): bool
    {
        return OnlinePresence::where('user_id', $userId)
            ->where('last_seen_at', '>=', now()->subSeconds($thresholdSeconds))
            ->exists();
    }

    public function prune(int $olderThanSeconds): int
    {
        return OnlinePresence::where('last_seen_at', '<', now()->subSeconds($olderThanSeconds))->delete();
    }
}
