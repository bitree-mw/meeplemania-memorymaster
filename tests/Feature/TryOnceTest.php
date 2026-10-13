<?php

namespace Tests\Feature;

use App\Games\DailyChallengeService;
use App\Models\DailyChallenge;
use App\Models\DailyChallengeAttempt;
use App\Models\Game;
use App\Models\GameSession;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TryOnceTest extends TestCase
{
    public function test_guests_cannot_take_part(): void
    {
        $this->postJson('/api/play/sessions', ['game' => 'dice-digits', 'mode' => 'once'])->assertStatus(401)->assertJsonPath('code', 'account_required');
        $this->get('/play/dice-digits/once')->assertRedirect('/login');
    }

    public function test_everyone_gets_the_same_puzzle_per_game_per_day(): void
    {
        $specs = [];
        foreach (User::factory()->count(2)->create() as $user) {
            $this->actingAs($user);
            $specs[] = $this->begin('pattern-peek', 'once')->specs;
        }

        $this->assertSame($specs[0], $specs[1]);
        $this->assertSame('L2A0', array_key_first($specs[0]), 'Try Once starts at the configured level.');

        $this->actingAs(User::factory()->create());
        $this->assertNotSame($specs[0], $this->begin('dice-digits', 'once')->specs, 'Each game has its own challenge.');

        Carbon::setTestNow(Carbon::parse('2026-10-14 12:00:00'));
        $this->actingAs(User::factory()->create());
        $this->assertNotSame($specs[0]['L2A0']['lit'], $this->begin('pattern-peek', 'once')->specs['L2A0']['lit'], 'A new day brings a new puzzle.');
    }

    public function test_only_the_first_attempt_of_the_day_is_ranked(): void
    {
        $this->actingAs($user = User::factory()->create());

        $ranked = $this->begin('spot-the-swap', 'once');
        $this->assertTrue($ranked->session['is_ranked']);
        $ranked->clear();
        while (! $ranked->summary) {
            $ranked->fail();
        }

        $practice = $this->begin('spot-the-swap', 'once');
        $this->assertFalse($practice->session['is_ranked']);
        $this->assertTrue($practice->session['is_practice']);
        $practice->clear();
        $practice->clear();
        $practice->finish('exited');

        $attempt = DailyChallengeAttempt::where('user_id', $user->id)->sole();
        $this->assertSame($ranked->uuid, $attempt->session->uuid);
        $this->assertSame($ranked->summary['final_score'], $attempt->session->final_score);

        // The other games' challenges are unaffected.
        $this->assertTrue($this->begin('dice-digits', 'once')->session['is_ranked']);
    }

    public function test_the_unique_index_blocks_duplicate_ranked_attempts(): void
    {
        $this->actingAs($user = User::factory()->create());
        $this->begin('memory-master', 'once');

        $this->expectException(UniqueConstraintViolationException::class);
        DailyChallengeAttempt::create([
            'daily_challenge_id' => DailyChallenge::sole()->id,
            'user_id' => $user->id,
            'game_session_id' => GameSession::sole()->id,
        ]);
    }

    public function test_the_challenge_completes_after_its_last_level(): void
    {
        $game = Game::where('slug', 'dice-digits')->sole();
        app(DailyChallengeService::class)->today($game)->update(['starting_level' => 1, 'max_levels' => 2]);

        $this->actingAs(User::factory()->create());
        $run = $this->begin('dice-digits', 'once');
        $run->clear();
        $run->clear();

        $this->assertSame('completed', $run->summary['status']);
        $this->assertSame('challenge_complete', $run->summary['end_reason']);
    }

    public function test_top_finishers_earn_badges_after_the_day_ends(): void
    {
        [$winner, $second] = User::factory()->count(2)->create();

        $this->actingAs($winner);
        $this->playTo('meeple-pairs', 2, 'once');
        $this->actingAs($second);
        $this->playTo('meeple-pairs', 0, 'once');

        Carbon::setTestNow(Carbon::parse('2026-10-14 00:30:00'));
        $this->artisan('games:daily-rollover')->assertSuccessful();

        $this->assertTrue($winner->achievements()->where('key', 'daily_champion')->exists());
        $this->assertTrue($second->achievements()->where('key', 'daily_podium')->exists());
        $this->assertFalse($second->achievements()->where('key', 'daily_champion')->exists());

        $this->artisan('games:daily-rollover')->assertSuccessful();
        $this->assertSame(1, $winner->achievements()->where('key', 'daily_champion')->count(), 'Badges are awarded once.');
    }
}
