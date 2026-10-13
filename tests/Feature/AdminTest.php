<?php

namespace Tests\Feature;

use App\Games\GameSettings;
use App\Models\GameSession;
use App\Models\PlayerProfile;
use App\Models\PlayerStatistic;
use App\Models\User;
use Tests\TestCase;

class AdminTest extends TestCase
{
    public function test_only_admins_can_reach_the_dashboard(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();

        $this->actingAs(User::factory()->admin()->create());
        foreach (['/admin', '/admin/settings', '/admin/settings?scope=meeple-pairs', '/admin/players', '/admin/reviews',
            '/admin/challenges?game=dice-digits', '/admin/seasons', '/admin/achievements', '/admin/leaderboards?game=pattern-peek&board=timed&duration=60'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_players_cannot_change_settings(): void
    {
        $this->actingAs(User::factory()->create())
            ->put('/admin/settings/memory-master/scoring', ['base_points' => 9999])
            ->assertForbidden();
    }

    public function test_game_settings_apply_to_new_runs_only(): void
    {
        $admin = User::factory()->admin()->create();
        $player = User::factory()->create();
        $this->actingAs($player);
        $running = $this->begin('dice-digits');

        $scoring = config('games.games.dice-digits.rules.scoring');
        $this->actingAs($admin)->put('/admin/settings/dice-digits/scoring', [...$scoring, 'base_points' => 500, 'points_per_level' => 0, 'speed_bonus_per_unit' => 0])
            ->assertSessionHasNoErrors();
        $this->assertSame(500, app(GameSettings::class)->game('dice-digits')['scoring']['base_points']);
        $this->assertSame(100, app(GameSettings::class)->game('pattern-peek')['scoring']['base_points'], 'Other games are untouched.');

        $this->actingAs($player);
        $running->clear(200);
        $this->assertSame(100, GameSession::where('uuid', $running->uuid)->sole()->rounds()->sole()->base_points, 'A running session keeps the rules it started with.');
        $this->assertSame(500, $this->begin('dice-digits')->clear(200)['session']['score']);
    }

    public function test_settings_are_validated(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->put('/admin/settings/meeple-pairs/game', ['preview_ms_per_pair' => 1])->assertSessionHasErrors(['preview_ms_per_pair', 'mismatches_allowed']);
        $this->put('/admin/settings/platform/daily', ['timezone' => 'Mars/Olympus'])->assertSessionHasErrors('timezone');
        $this->put('/admin/settings/chess/game', [])->assertNotFound();
    }

    public function test_platform_timezone_controls_the_day_rollover(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->put('/admin/settings/platform/daily', ['timezone' => 'Pacific/Auckland'])->assertSessionHasNoErrors();

        // 12:00 UTC on 13 Oct is already 14 Oct in Auckland.
        $this->assertSame('2026-10-14', app(GameSettings::class)->today());
    }

    public function test_admins_can_disable_players(): void
    {
        $admin = User::factory()->admin()->create();
        $player = User::factory()->create();

        $this->actingAs($admin)->post("/admin/players/{$player->id}/disable", ['reason' => 'Abuse'])->assertRedirect();
        $this->assertTrue($player->fresh()->isDisabled());

        $this->post("/admin/players/{$admin->id}/disable")->assertStatus(422);
    }

    public function test_flagged_results_can_be_approved_or_voided(): void
    {
        $this->actingAs($player = User::factory()->create());
        $run = $this->begin('memory-master');
        $run->clear();
        $spec = $run->spec();
        $run->sync([$run->result($spec, $spec['sequence'], timings: [100, 110])]);
        $this->assertNull(PlayerProfile::where('user_id', $player->id)->first());

        $this->actingAs(User::factory()->admin()->create());
        $this->post("/admin/sessions/{$run->uuid}/approve")->assertRedirect();

        $this->assertSame(1, PlayerProfile::where('user_id', $player->id)->sole()->games_played);
        $this->assertSame(1, PlayerStatistic::where('user_id', $player->id)->sole()->best_level);

        $this->post("/admin/sessions/{$run->uuid}/void")->assertRedirect();
        $this->assertSame('voided', GameSession::where('uuid', $run->uuid)->value('status'));
        $this->assertSame(0, PlayerProfile::where('user_id', $player->id)->sole()->games_played);
    }

    public function test_try_once_can_be_configured_per_game_until_someone_plays(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put('/admin/challenges', [
            'game' => 'pattern-peek', 'date' => '2026-10-13', 'title' => 'Big grid day', 'starting_level' => 9, 'max_levels' => 5,
        ])->assertSessionHasNoErrors();

        $this->actingAs(User::factory()->create());
        $run = $this->begin('pattern-peek', 'once');
        $this->assertSame('L9A0', $run->session['next_key']);
        $this->assertSame(13, $run->session['max_level']);
        $this->assertSame('L2A0', $this->begin('dice-digits', 'once')->session['next_key'], 'Other games keep their defaults.');

        $this->actingAs($admin)->put('/admin/challenges', [
            'game' => 'pattern-peek', 'date' => '2026-10-13', 'title' => null, 'starting_level' => 1, 'max_levels' => 5,
        ])->assertSessionHas('error');
    }

    public function test_seasons_cannot_overlap(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post('/admin/seasons', ['name' => 'Clash', 'starts_on' => '2026-10-20', 'ends_on' => '2026-11-20'])
            ->assertSessionHasErrors('starts_on');
    }
}
