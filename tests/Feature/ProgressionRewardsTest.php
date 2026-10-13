<?php

namespace Tests\Feature;

use App\Games\StatisticsService;
use App\Models\Achievement;
use App\Models\PlayerProfile;
use App\Models\PlayerStatistic;
use App\Models\User;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Streaks, personal bests and achievements.
 */
class ProgressionRewardsTest extends TestCase
{
    public function test_daily_streak_counts_consecutive_days_across_all_games(): void
    {
        $this->actingAs($user = User::factory()->create());
        $profile = fn () => PlayerProfile::where('user_id', $user->id)->sole();

        $this->playTo('memory-master', 1);
        $this->playTo('dice-digits', 1); // same day: unchanged
        $this->assertSame(1, $profile()->current_daily_streak);

        Carbon::setTestNow(Carbon::parse('2026-10-14 09:00'));
        $this->playTo('pattern-peek', 1);
        Carbon::setTestNow(Carbon::parse('2026-10-15 09:00'));
        $this->playTo('meeple-pairs', 1);
        $this->assertSame(3, $profile()->current_daily_streak);

        Carbon::setTestNow(Carbon::parse('2026-10-17 09:00'));
        $this->assertSame(0, app(StatisticsService::class)->displayDailyStreak($profile()), 'A missed day shows as broken.');

        $this->playTo('memory-master', 1);
        $this->assertSame(1, $profile()->current_daily_streak);
        $this->assertSame(3, $profile()->longest_daily_streak);
    }

    public function test_session_success_streak_is_tracked_separately(): void
    {
        $this->actingAs($user = User::factory()->create());
        $run = $this->begin('pattern-peek');
        foreach (range(1, 4) as $_) {
            $run->clear();
        }
        $run->fail();
        $run->clear();
        while (! $run->summary) {
            $run->fail();
        }

        $this->assertSame(4, $run->summary['best_success_streak']);
        $this->assertSame(4, PlayerStatistic::where('user_id', $user->id)->sole()->best_session_streak);
        $this->assertSame(1, PlayerProfile::where('user_id', $user->id)->sole()->current_daily_streak);
    }

    public function test_personal_bests_and_rank_movement_are_reported(): void
    {
        $this->actingAs(User::factory()->create());
        $this->playTo('memory-master', 3);

        $this->actingAs(User::factory()->create());
        $first = $this->playTo('memory-master', 1)->summary['outcome'];
        $this->assertContains('level', $first['personal_bests']);
        $this->assertNull($first['rank_before']);
        $this->assertSame(2, $first['rank_after']);

        $second = $this->playTo('memory-master', 5)->summary['outcome'];
        // Reaching level 5 also qualifies the run for the Fastest board.
        $this->assertSame(['level', 'score', 'speed'], $second['personal_bests']);
        $this->assertSame(2, $second['rank_before']);
        $this->assertSame(1, $second['rank_after']);
    }

    public function test_achievements_unlock_once(): void
    {
        $this->actingAs($user = User::factory()->create());

        $first = array_column($this->playTo('dice-digits', 1)->summary['outcome']['achievements'], 'key');
        $this->assertContains('first_victory', $first);
        $this->assertContains('top_100', $first);

        $again = array_column($this->playTo('dice-digits', 1)->summary['outcome']['achievements'], 'key');
        $this->assertNotContains('first_victory', $again);
        $this->assertSame(1, $user->achievements()->where('key', 'first_victory')->count());
    }

    public function test_game_explorer_unlocks_after_trying_all_five_games(): void
    {
        $this->actingAs($user = User::factory()->create());

        foreach (self::GAMES as $i => $game) {
            $keys = array_column($this->playTo($game, 1)->summary['outcome']['achievements'], 'key');
            $this->assertSame($i === 4, in_array('game_explorer', $keys, true), "after {$game}");
        }
    }

    public function test_thresholds_are_configurable(): void
    {
        Achievement::where('key', 'speed_runner')->update(['threshold' => 50]);
        $this->actingAs(User::factory()->create());

        $run = $this->begin('spot-the-swap', 'timed', 30);
        $run->clear(200);
        $keys = array_column($run->finish('time_up')['summary']['outcome']['achievements'], 'key');

        $this->assertContains('speed_runner', $keys);
    }

    public function test_statistics_can_be_rebuilt_from_runs(): void
    {
        $this->actingAs($user = User::factory()->create());
        $this->playTo('memory-master', 2);
        $this->playTo('pattern-peek', 3);
        $snapshot = fn () => [
            PlayerProfile::where('user_id', $user->id)->sole()->only(['games_played', 'total_points', 'current_daily_streak']),
            PlayerStatistic::where('user_id', $user->id)->orderBy('game_id')->get()->map->only(['game_id', 'best_level', 'total_points'])->all(),
        ];
        $before = $snapshot();

        PlayerProfile::where('user_id', $user->id)->update(['total_points' => 999999]);
        app(StatisticsService::class)->rebuild($user);

        $this->assertEquals($before, $snapshot());
    }
}
