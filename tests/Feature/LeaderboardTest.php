<?php

namespace Tests\Feature;

use App\Games\Board;
use App\Games\GameSettings;
use App\Games\LeaderboardService;
use App\Models\Game;
use App\Models\GameSeason;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LeaderboardTest extends TestCase
{
    private function board(?string $game, string $category, ?int $duration = null): Board
    {
        return new Board($game ? Game::where('slug', $game)->sole() : null, $category, $duration);
    }

    private function rows(Board $board, ?string $search = null): array
    {
        cache()->flush();

        return app(LeaderboardService::class)->page($board, 1, $search)['rows'];
    }

    public function test_highest_level_ranks_per_game_with_earliest_achiever_first(): void
    {
        [$early, $late, $best] = User::factory()->count(3)->create();

        $this->actingAs($early);
        $this->playTo('pattern-peek', 2);
        $this->travel(1)->minutes();
        $this->actingAs($late);
        $this->playTo('pattern-peek', 2);
        $this->actingAs($best);
        $this->playTo('pattern-peek', 3);
        $this->playTo('dice-digits', 9);

        $this->assertSame([$best->id, $early->id, $late->id], array_column($this->rows($this->board('pattern-peek', 'level')), 'user_id'));
        $this->assertSame([1, 2, 3], array_column($this->rows($this->board('pattern-peek', 'level')), 'position'));
        $this->assertSame([$best->id], array_column($this->rows($this->board('dice-digits', 'level')), 'user_id'));
    }

    public function test_timed_boards_are_separate_per_duration(): void
    {
        [$a, $b] = User::factory()->count(2)->create();

        $this->actingAs($a);
        $run = $this->begin('memory-master', 'timed', 30);
        $run->clear(200);
        $run->finish('time_up');

        $this->actingAs($b);
        $run = $this->begin('memory-master', 'timed', 60);
        $run->clear(200);
        $run->clear(200);
        $run->finish('time_up');

        $this->assertSame([$a->id], array_column($this->rows($this->board('memory-master', 'timed', 30)), 'user_id'));
        $this->assertSame([$b->id], array_column($this->rows($this->board('memory-master', 'timed', 60)), 'user_id'));
        $this->assertSame([], $this->rows($this->board('memory-master', 'timed', 45)));
    }

    public function test_the_all_games_board_sums_points_across_games(): void
    {
        [$specialist, $allRounder] = User::factory()->count(2)->create();

        $this->actingAs($specialist);
        $one = $this->playTo('memory-master', 3)->summary['final_score'];

        $this->actingAs($allRounder);
        $total = 0;
        foreach (self::GAMES as $game) {
            $total += $this->playTo($game, 2)->summary['final_score'];
        }

        $rows = $this->rows($this->board(null, 'all_time'));
        $this->assertSame([$allRounder->id, $specialist->id], array_column($rows, 'user_id'));
        $this->assertSame([$total, $one], array_map('intval', array_column($rows, 'points')));
    }

    public function test_flagged_runs_disabled_players_and_guests_are_excluded(): void
    {
        [$honest, $cheat, $banned] = User::factory()->count(3)->create();

        $this->actingAs($honest);
        $this->playTo('memory-master', 1);
        $this->actingAs($banned);
        $this->playTo('memory-master', 3);
        $this->actingAs($cheat);
        $run = $this->begin('memory-master');
        $run->clear();
        $spec = $run->spec();
        $run->sync([$run->result($spec, $spec['sequence'], timings: [100, 110])]);

        $banned->forceFill(['disabled_at' => now()])->save();
        auth()->logout();
        $this->playTo('memory-master', 4); // guest

        $this->assertSame([$honest->id], array_column($this->rows($this->board(null, 'all_time')), 'user_id'));
    }

    public function test_search_keeps_global_positions(): void
    {
        foreach (['alpha_one' => 3, 'bravo_two' => 2, 'charlie_three' => 1] as $name => $levels) {
            $this->actingAs(User::factory()->create(['username' => $name]));
            $this->playTo('spot-the-swap', $levels);
        }

        $rows = $this->rows($this->board('spot-the-swap', 'level'), 'charlie');
        $this->assertCount(1, $rows);
        $this->assertSame(3, $rows[0]['position']);
    }

    public function test_weekly_board_counts_this_week_only(): void
    {
        [$a, $b] = User::factory()->count(2)->create();
        $this->actingAs($a);
        $this->playTo('dice-digits', 3);
        $this->travel(8)->days();
        $this->actingAs($b);
        $this->playTo('dice-digits', 1);

        $this->assertSame([$b->id], array_column($this->rows($this->board(null, 'weekly')), 'user_id'));
        $this->assertSame([$b->id], array_column($this->rows($this->board('dice-digits', 'weekly')), 'user_id'));
    }

    public function test_rank_movement_compares_with_the_previous_snapshot(): void
    {
        [$a, $b] = User::factory()->count(2)->create();
        $this->actingAs($a);
        $this->playTo('memory-master', 2);
        $this->actingAs($b);
        $this->playTo('memory-master', 1);

        app(LeaderboardService::class)->snapshot($this->board('memory-master', 'level'));
        $this->travel(1)->days();
        $this->playTo('memory-master', 4);

        $rows = collect($this->rows($this->board('memory-master', 'level')))->keyBy('user_id');
        $this->assertSame(1, $rows[$b->id]['movement']);
        $this->assertSame(-1, $rows[$a->id]['movement']);
    }

    public function test_a_new_season_starts_the_season_board_from_zero(): void
    {
        $today = CarbonImmutable::parse(app(GameSettings::class)->today());
        GameSeason::create(['name' => 'Season 1', 'starts_on' => $today, 'ends_on' => $today->addDay()]);
        GameSeason::create(['name' => 'Season 2', 'starts_on' => $today->addDays(2), 'ends_on' => $today->addDays(30)]);

        [$a, $b] = User::factory()->count(2)->create();
        $this->actingAs($a);
        $this->playTo('memory-master', 4);
        $this->actingAs($b);
        $this->playTo('memory-master', 1);
        app(LeaderboardService::class)->snapshot($this->board(null, 'season'));

        $this->travel(2)->days();
        $this->assertSame([], $this->rows($this->board(null, 'season')));

        $points = $this->playTo('memory-master', 1)->summary['final_score'];
        $rows = $this->rows($this->board(null, 'season'));
        $this->assertSame([$b->id], array_column($rows, 'user_id'));
        $this->assertSame($points, (int) $rows[0]['points']);
        $this->assertNull($rows[0]['movement'], 'Last season’s ranks are not compared with');
    }

    public function test_players_below_the_podium_see_two_places_either_side_of_them(): void
    {
        $users = User::factory()->count(6)->create();
        foreach ($users as $i => $user) {
            $this->actingAs($user);
            $this->playTo('pattern-peek', 6 - $i); // users[0] first … users[5] last
        }
        cache()->flush();

        $this->actingAs($users[3])->get('/leaderboard?game=pattern-peek&board=level')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('me.position', 4)
                ->where('around', fn ($rows) => collect($rows)->pluck('position')->all() === [2, 3, 4, 5, 6]));

        // On the podium already: no extra list.
        $this->actingAs($users[2])->get('/leaderboard?game=pattern-peek&board=level')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('around', []));
    }

    public function test_the_page_is_for_members_and_shows_the_viewers_position(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->playTo('meeple-pairs', 2);
        auth()->logout();

        $this->get('/leaderboard?game=meeple-pairs&board=level')->assertRedirect('/login')->assertSessionHas('error');

        $this->actingAs($user)->get('/leaderboard?game=meeple-pairs&board=level')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Leaderboard')->has('podium', 1)->where('board', 'level'));

        $this->get('/leaderboard')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('me.position', 1)->where('game', 'all')->where('board', 'all_time'));

        $this->get('/leaderboard?game=meeple-pairs&board=timed&duration=45')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('duration', 45)->where('label', 'Timed Rush · 45s'));
    }
}
