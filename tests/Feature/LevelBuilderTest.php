<?php

namespace Tests\Feature;

use App\Models\GameRound;
use App\Models\GameSession;
use App\Models\PlayerStatistic;
use App\Models\User;
use Tests\Support\TestRun;
use Tests\TestCase;

class LevelBuilderTest extends TestCase
{
    public function test_every_game_can_be_played_end_to_end(): void
    {
        $this->actingAs($user = User::factory()->create());

        foreach (self::GAMES as $game) {
            $run = $this->playTo($game, 3);

            $this->assertSame('failed', $run->summary['status'], $game);
            $this->assertSame(3, $run->summary['highest_level'], $game);
            $this->assertGreaterThan(0, $run->summary['final_score'], $game);
        }

        $this->assertSame(5, PlayerStatistic::where('user_id', $user->id)->count());
        $this->assertSame(5, $user->fresh()->profile->games_played);
    }

    public function test_start_reveals_rounds_ahead_but_never_the_seed(): void
    {
        $this->actingAs(User::factory()->create());
        $run = $this->begin('memory-master');

        $this->assertSame(['L1A0', 'L2A0', 'L3A0'], array_keys($run->specs));
        $this->assertSame('L1A0', $run->session['next_key']);
        $this->assertTrue($run->session['is_ranked']);
        $this->assertStringNotContainsString('seed', json_encode($run->last->json()));
    }

    public function test_games_with_lives_reveal_every_retry_up_front(): void
    {
        $this->actingAs(User::factory()->create());
        $run = $this->begin('pattern-peek');

        $this->assertSame(3, $run->session['lives']);
        $this->assertArrayHasKey('L1A2', $run->specs, 'Lost lives never wait on the network.');
    }

    public function test_clearing_awards_server_points_and_reveals_more(): void
    {
        $this->actingAs(User::factory()->create());
        $run = $this->begin('memory-master');

        $json = $run->clear();

        $this->assertSame(1, $json['verified']);
        $this->assertSame('L2A0', $json['next_key']);
        $this->assertArrayHasKey('L4A0', $run->specs);
        $round = GameRound::first();
        $this->assertSame('cleared', $round->status);
        $this->assertSame(100 + 50 + $round->speed_bonus, $round->points);
        $this->assertSame($round->points, $json['session']['score']);
    }

    public function test_a_failed_round_costs_a_life_and_replays_the_level_with_a_new_puzzle(): void
    {
        $this->actingAs(User::factory()->create());
        $run = $this->begin('dice-digits');
        $run->clear();

        $first = $run->spec();
        $json = $run->fail();

        $this->assertSame(2, $json['session']['lives']);
        $this->assertSame('L2A1', $json['next_key']);
        $this->assertNotSame($first['dice'], $run->spec()['dice']);

        $run->clear();
        $this->assertSame('L3A1', $run->session['next_key'], 'The attempt chain continues after a clear.');
    }

    public function test_the_run_ends_when_lives_run_out(): void
    {
        $this->actingAs(User::factory()->create());
        $run = $this->begin('spot-the-swap');

        $run->fail();
        $run->fail();
        $this->assertNull($run->summary);
        $run->fail();

        $this->assertSame('failed', $run->summary['status']);
        $this->assertSame('wrong', $run->summary['end_reason']);
        $this->assertSame(0, $run->summary['final_score']);
    }

    public function test_results_can_be_batched_and_retried_safely(): void
    {
        $this->actingAs(User::factory()->create());
        $run = $this->begin('memory-master');
        $batch = [
            $run->result($run->specs['L1A0'], $run->specs['L1A0']['sequence']),
            $run->result($run->specs['L2A0'], $run->specs['L2A0']['sequence']),
        ];

        $first = $run->sync($batch);
        $again = $run->sync($batch, advance: false);

        $this->assertSame(2, $first['verified']);
        $this->assertSame(2, $again['verified'], 'Re-sent results are ignored.');
        $this->assertSame($first['session']['score'], $again['session']['score']);
        $this->assertSame(2, GameRound::count());
    }

    public function test_results_out_of_order_are_not_accepted(): void
    {
        $this->actingAs(User::factory()->create());
        $run = $this->begin('memory-master');

        $json = $run->sync([$run->result($run->specs['L2A0'], $run->specs['L2A0']['sequence'])]);

        $this->assertSame(0, $json['verified']);
        $this->assertSame('L1A0', $json['next_key']);
    }

    public function test_malformed_inputs_are_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        $run = $this->begin('memory-master');

        $this->postJson("/api/play/sessions/{$run->uuid}/sync", ['results' => [['key' => 'L1A0', 'inputs' => [7], 'timings' => [300]]]])
            ->assertStatus(422)->assertJsonPath('code', 'malformed_result');
    }

    public function test_impossibly_fast_taps_flag_the_run(): void
    {
        $this->actingAs($user = User::factory()->create());
        $run = $this->begin('memory-master');
        $run->clear();
        $run->clear();

        $spec = $run->spec();
        $run->sync([$run->result($spec, $spec['sequence'], timings: [200, 210, 220])]);

        $this->assertTrue($run->summary['is_flagged']);
        $this->assertDatabaseHas('game_sessions', ['uuid' => $run->uuid, 'flag_reason' => 'impossible_tap_rate']);
        $this->assertNull(PlayerStatistic::where('user_id', $user->id)->first(), 'Flagged runs stay out of statistics.');
    }

    public function test_claiming_more_play_time_than_the_server_saw_is_flagged(): void
    {
        $this->actingAs(User::factory()->create());
        $run = $this->begin('memory-master');
        $spec = $run->spec();

        // Claim a 30 s answer but submit immediately.
        $run->sync([$run->result($spec, $spec['sequence'], timings: [30000])], advance: false);

        $this->assertSame('clock_mismatch', GameSession::where('uuid', $run->uuid)->value('flag_reason'));
    }

    public function test_an_incomplete_round_counts_as_a_timeout(): void
    {
        $this->actingAs(User::factory()->create());
        $run = $this->begin('memory-master');
        $run->clear();
        $spec = $run->spec();

        $run->sync([$run->result($spec, [$spec['sequence'][0]])]);

        $this->assertSame('timeout', $run->summary['end_reason']);
    }

    public function test_exiting_keeps_verified_points(): void
    {
        $this->actingAs($user = User::factory()->create());
        $run = $this->begin('pattern-peek');
        $score = $run->clear()['session']['score'];

        $json = $run->finish('exited');

        $this->assertSame('abandoned', $json['summary']['status']);
        $this->assertSame($score, $json['summary']['final_score']);
        $this->assertSame($score, (int) $user->fresh()->profile->total_points);
        $run->finish('exited');
        $this->assertSame(1, $user->fresh()->profile->games_played, 'Finishing is idempotent.');
    }

    public function test_the_final_results_can_arrive_with_finish(): void
    {
        $this->actingAs(User::factory()->create());
        $run = $this->begin('memory-master');
        $spec = $run->spec();

        $json = $run->finish('ended', [$run->result($spec, TestRun::wrong($spec))]);

        $this->assertSame('failed', $json['summary']['status']);
        $this->assertSame(1, $json['summary']['rounds_played']);
    }

    public function test_guests_play_casual_unranked_runs(): void
    {
        $run = $this->begin('meeple-pairs');
        $this->assertFalse($run->session['is_ranked']);
        $run->clear();

        $this->assertSame(0, PlayerStatistic::count());
    }

    public function test_players_cannot_touch_someone_elses_run(): void
    {
        $this->actingAs(User::factory()->create());
        $run = $this->begin();

        $this->actingAs(User::factory()->create());
        $this->postJson("/api/play/sessions/{$run->uuid}/sync", ['results' => []])->assertForbidden();
        auth()->logout();
        $this->getJson("/api/play/sessions/{$run->uuid}")->assertForbidden();
    }

    public function test_starting_a_new_run_closes_the_previous_one(): void
    {
        $this->actingAs(User::factory()->create());
        $first = $this->begin('memory-master');
        $this->begin('dice-digits');

        $this->assertDatabaseHas('game_sessions', ['uuid' => $first->uuid, 'status' => 'abandoned']);
    }

    public function test_idle_runs_expire(): void
    {
        $this->actingAs(User::factory()->create());
        $run = $this->begin();
        $run->clear();

        $this->travel(61)->minutes();
        $this->artisan('games:expire-sessions')->assertSuccessful();

        $this->assertDatabaseHas('game_sessions', ['uuid' => $run->uuid, 'status' => 'expired']);
    }

    public function test_unknown_games_and_modes_are_rejected(): void
    {
        $this->postJson('/api/play/sessions', ['game' => 'chess', 'mode' => 'classic'])->assertStatus(422);
        $this->postJson('/api/play/sessions', ['game' => 'memory-master', 'mode' => 'blitz'])->assertStatus(422);
        $this->get('/play/memory-master/blitz')->assertNotFound();
        $this->get('/play/chess/classic')->assertNotFound();
    }
}
