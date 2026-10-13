<?php

namespace Tests\Feature;

use App\Models\OnlinePresence;
use App\Models\User;
use App\Platform\Presence\PresenceService;
use Tests\TestCase;

class PresenceTest extends TestCase
{
    public function test_heartbeats_count_players_guests_and_active_games(): void
    {
        $this->actingAs(User::factory()->create())->postJson('/api/presence/heartbeat', ['playing' => true])->assertOk();
        auth()->logout();
        $this->postJson('/api/presence/heartbeat')->assertOk();
        cache()->flush();

        $this->getJson('/api/presence/counts')->assertExactJson(['online' => 2, 'players' => 1, 'guests' => 1, 'playing' => 1]);
    }

    public function test_multiple_tabs_of_one_player_count_once(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/presence/heartbeat')->assertOk();
        $this->actingAs($user)->postJson('/api/presence/heartbeat', ['playing' => true])->assertOk();

        $this->assertSame(1, OnlinePresence::count());
    }

    public function test_presence_expires_after_the_threshold(): void
    {
        $this->actingAs(User::factory()->create())->postJson('/api/presence/heartbeat')->assertOk();

        $this->travel(121)->seconds();
        cache()->flush();

        $this->assertSame(0, app(PresenceService::class)->counts()['online']);
    }

    public function test_stale_rows_are_pruned(): void
    {
        $this->postJson('/api/presence/heartbeat')->assertOk();
        $this->travel(2)->hours();

        $this->artisan('games:prune-presence')->assertSuccessful();

        $this->assertSame(0, OnlinePresence::count());
    }
}
