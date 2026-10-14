<?php

namespace Tests\Feature;

use App\Models\GameSession;
use App\Models\PlayerStatistic;
use App\Models\User;
use Tests\TestCase;

class TimedRushTest extends TestCase
{
    public function test_players_choose_30_45_or_60_seconds(): void
    {
        $this->actingAs(User::factory()->create());

        foreach ([30, 45, 60] as $duration) {
            $this->assertSame($duration, $this->begin('pattern-peek', 'timed', $duration)->session['duration']);
        }

        $this->postJson('/api/play/sessions', ['game' => 'pattern-peek', 'mode' => 'timed', 'duration' => 20])->assertStatus(422);
        $this->postJson('/api/play/sessions', ['game' => 'pattern-peek', 'mode' => 'timed'])->assertStatus(422)->assertJsonPath('code', 'invalid_duration');
    }

    public function test_misses_cost_points_without_ending_the_run(): void
    {
        $this->actingAs(User::factory()->create());
        $run = $this->begin('memory-master', 'timed', 30);

        $this->assertSame('N1', $run->session['next_key']);
        $this->assertCount(15, $run->specs);
        $hit = $run->clear(200)['session']['score'];
        $miss = $run->fail();

        $this->assertSame('active', $miss['session']['status']);
        $this->assertSame($hit - 25, $miss['session']['score']);
        $this->assertSame(1, $miss['session']['mistakes']);
        $this->assertNull($run->session['lives']);
    }

    public function test_difficulty_ramps_with_round_number(): void
    {
        $this->actingAs(User::factory()->create());
        $run = $this->begin('memory-master', 'timed', 60);

        $this->assertSame([3, 3, 3, 4], array_map(fn ($k) => count($run->specs[$k]['sequence']), ['N1', 'N2', 'N3', 'N4']));
    }

    public function test_finishing_records_the_best_score_for_that_duration(): void
    {
        $this->actingAs($user = User::factory()->create());
        $run = $this->begin('dice-digits', 'timed', 45);
        $run->clear(200);
        $run->clear(200);

        $json = $run->finish('time_up');

        $this->assertSame('completed', $json['summary']['status']);
        $this->assertSame('time_up', $json['summary']['end_reason']);
        $stats = PlayerStatistic::where('user_id', $user->id)->first();
        $this->assertSame($json['summary']['final_score'], $stats->best_timed_45);
        $this->assertSame(0, $stats->best_timed_30);
        $this->assertContains('timed', $json['summary']['outcome']['personal_bests']);
    }

    public function test_a_round_cut_off_by_the_clock_scores_nothing(): void
    {
        $this->actingAs(User::factory()->create());
        $run = $this->begin('pattern-peek', 'timed', 30);
        $spec = $run->spec();

        $json = $run->finish('time_up', [$run->result($spec, [$spec['lit'][0]])]);

        $this->assertSame(0, $json['summary']['final_score']);
        $this->assertSame(0, $json['summary']['penalty_score']);
    }

    public function test_claiming_more_rounds_than_fit_in_the_clock_is_flagged(): void
    {
        $this->actingAs(User::factory()->create());
        $run = $this->begin('memory-master', 'timed', 30);

        // 15 rounds each claiming 3 s of answering cannot fit in 30 seconds.
        $results = [];
        foreach (range(1, 15) as $n) {
            $spec = $run->specs["N{$n}"];
            $results[] = $run->result($spec, $spec['sequence'], 1000);
        }
        $run->sync($results);

        $this->assertSame('clock_overrun', GameSession::where('uuid', $run->uuid)->value('flag_reason'));
    }

    public function test_results_arriving_long_after_the_clock_go_to_review(): void
    {
        $this->actingAs(User::factory()->create());
        $run = $this->begin('memory-master', 'timed', 30);

        $this->travel(2)->minutes();
        $run->clear(200);

        $this->assertDatabaseHas('game_sessions', ['uuid' => $run->uuid, 'is_flagged' => true, 'flag_reason' => 'late_sync']);
    }

    public function test_a_pause_pushes_the_deadline_back_by_the_time_paused(): void
    {
        $this->actingAs(User::factory()->create());
        $run = $this->begin('memory-master', 'timed', 30);
        $run->clear(200);
        $deadline = GameSession::where('uuid', $run->uuid)->value('deadline_at');

        $this->postJson("/api/play/sessions/{$run->uuid}/pause")->assertOk();
        $this->travel(5)->minutes();
        // Paused: the clock has stopped, so the sweeper leaves it alone.
        $this->artisan('games:expire-sessions')->assertSuccessful();
        $this->assertDatabaseHas('game_sessions', ['uuid' => $run->uuid, 'status' => 'active']);

        $this->postJson("/api/play/sessions/{$run->uuid}/resume")->assertOk();
        $session = GameSession::where('uuid', $run->uuid)->sole();
        $this->assertNull($session->paused_at);
        $this->assertEqualsWithDelta($deadline->addMinutes(5)->getTimestamp(), $session->deadline_at->getTimestamp(), 2);

        $run->clear(200);
        $this->assertDatabaseHas('game_sessions', ['uuid' => $run->uuid, 'is_flagged' => false]);

        // Another player cannot pause someone else's run.
        $this->actingAs(User::factory()->create())->postJson("/api/play/sessions/{$run->uuid}/pause")->assertForbidden();
    }

    public function test_abandoned_timed_runs_close_after_the_clock(): void
    {
        $this->actingAs(User::factory()->create());
        $run = $this->begin('spot-the-swap', 'timed', 30);
        $run->clear(200);

        $this->travel(2)->minutes();
        $this->artisan('games:expire-sessions')->assertSuccessful();

        $this->assertDatabaseHas('game_sessions', ['uuid' => $run->uuid, 'status' => 'completed', 'end_reason' => 'time_up']);
    }
}
