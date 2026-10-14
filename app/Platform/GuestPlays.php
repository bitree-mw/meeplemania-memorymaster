<?php

namespace App\Platform;

use App\Games\GameSettings;
use App\Models\GameSession;

/**
 * Guests may play a limited number of games per device: after "nudge_after_plays"
 * they are asked to create an account, after "max_plays" they must. A game counts
 * once a round has been played, so opening a game page (which prepares a session
 * in the background) costs nothing.
 */
class GuestPlays
{
    public function __construct(private GameSettings $settings) {}

    public function played(string $device): int
    {
        return GameSession::whereNull('user_id')
            ->where('guest_token', $device)
            ->where('rounds_played', '>', 0)
            ->count();
    }

    public function exhausted(?string $device): bool
    {
        return $device !== null && $this->played($device) >= $this->limit();
    }

    /** @return array{played: int, limit: int, remaining: int, nudge: bool} */
    public function status(string $device): array
    {
        $played = $this->played($device);
        $limit = $this->limit();

        return [
            'played' => $played,
            'limit' => $limit,
            'remaining' => max(0, $limit - $played),
            'nudge' => $played >= $this->nudgeAfter(),
        ];
    }

    public function limitMessage(): string
    {
        return "You’ve played all {$this->limit()} guest games. Create a free account to keep playing, compete on the leaderboards and save your progress.";
    }

    private function limit(): int
    {
        // Read with defaults: settings cached before this section existed lack it.
        return (int) $this->settings->get('platform.guests.max_plays', 20);
    }

    private function nudgeAfter(): int
    {
        return (int) $this->settings->get('platform.guests.nudge_after_plays', 10);
    }
}
