<?php

namespace App\Platform\Presence;

/**
 * Storage for online presence. The default implementation uses the database;
 * a Redis implementation can be bound in AppServiceProvider without touching
 * gameplay or controllers.
 */
interface PresenceStore
{
    /** Record a heartbeat. $visitorKey must be stable across tabs of one browser session. */
    public function touch(string $visitorKey, ?int $userId, bool $isPlaying): void;

    /**
     * @return array{online:int, players:int, guests:int, playing:int}
     */
    public function counts(int $thresholdSeconds): array;

    /** Whether a given user has been seen within the threshold. */
    public function isOnline(int $userId, int $thresholdSeconds): bool;

    /** Remove entries older than the given number of seconds. */
    public function prune(int $olderThanSeconds): int;
}
