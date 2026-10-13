<?php

namespace Tests\Feature;

use App\Games\Board;
use App\Games\LeaderboardService;
use App\Models\Achievement;
use App\Models\AdminActivityLog;
use App\Models\DistrictChangeRequest;
use App\Models\Game;
use App\Models\GameSession;
use App\Models\PasswordResetLink;
use App\Models\PlayerAchievement;
use App\Models\PlayerProfile;
use App\Models\PlayerStatistic;
use App\Models\User;
use App\Notifications\PasswordResetLinkNotification;
use App\Platform\Districts;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AccountManagementTest extends TestCase
{
    private function requestResetLink(User $user): string
    {
        Notification::fake();

        $this->post('/forgot-password', ['login' => $user->username])->assertSessionHas('status');

        $url = null;
        Notification::assertSentTo($user, PasswordResetLinkNotification::class, function ($n) use (&$url) {
            $url = $n->url;

            return true;
        });

        return $url;
    }

    private function resetWith(string $url, string $password = 'brand-new-password')
    {
        [$username, $token] = array_slice(explode('/', parse_url($url, PHP_URL_PATH)), -2);

        return $this->post('/reset-password', [
            'username' => rawurldecode($username),
            'token' => $token,
            'password' => $password,
            'password_confirmation' => $password,
        ]);
    }

    public function test_reset_link_uses_app_url_username_and_a_secret_that_is_stored_hashed(): void
    {
        config(['app.url' => 'https://games.example.mw']);
        $user = User::factory()->create(['username' => 'Chikondi']);

        $url = $this->requestResetLink($user);

        $this->assertMatchesRegularExpression('#^https://games\.example\.mw/reset-password/Chikondi/[A-Za-z0-9]{64}$#', $url);
        $secret = basename($url);
        $this->assertDatabaseMissing('password_reset_links', ['token_hash' => $secret]);
        $this->assertDatabaseHas('password_reset_links', ['user_id' => $user->id, 'token_hash' => hash('sha256', $secret)]);
        $this->get(parse_url($url, PHP_URL_PATH))->assertOk();
    }

    public function test_reset_link_works_once_and_signs_in_with_the_new_password(): void
    {
        $user = User::factory()->create();
        $url = $this->requestResetLink($user);

        $this->resetWith($url)->assertRedirect('/login')->assertSessionHasNoErrors();
        $this->assertSame(0, PasswordResetLink::count());

        $this->resetWith($url, 'another-password-1')->assertSessionHasErrors('token');

        $this->post('/login', ['login' => $user->username, 'password' => 'brand-new-password']);
        $this->assertAuthenticatedAs($user);
    }

    public function test_reset_link_expires_and_is_pruned(): void
    {
        $user = User::factory()->create();
        $url = $this->requestResetLink($user);

        $this->travel(61)->minutes();

        $this->resetWith($url)->assertSessionHasErrors('token');
        $this->artisan('games:prune-reset-links')->assertSuccessful();
        $this->assertSame(0, PasswordResetLink::count());
    }

    public function test_reset_link_is_bound_to_the_username(): void
    {
        [$user, $other] = User::factory()->count(2)->create();
        $url = $this->requestResetLink($user);

        $this->post('/reset-password', [
            'username' => $other->username,
            'token' => basename($url),
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertSessionHasErrors('token');
    }

    public function test_unknown_accounts_get_the_same_response(): void
    {
        Notification::fake();

        $this->post('/forgot-password', ['login' => 'nobody_here'])->assertSessionHas('status');
        Notification::assertNothingSent();
    }

    public function test_admins_can_send_reset_links_and_deactivate_accounts(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $player = User::factory()->create();

        $this->actingAs($admin)->post("/admin/players/{$player->id}/password-reset")->assertSessionHasNoErrors();
        Notification::assertSentTo($player, PasswordResetLinkNotification::class, fn ($n) => $n->requestedByAdmin);
        $this->assertDatabaseHas('password_reset_links', ['user_id' => $player->id, 'requested_by' => $admin->id]);

        $this->post("/admin/players/{$player->id}/disable", ['reason' => 'spam'])->assertSessionHasNoErrors();
        $this->assertTrue($player->fresh()->isDisabled());

        $this->assertSame(['players.password-reset', 'players.disable'], AdminActivityLog::orderBy('id')->pluck('action')->all());
    }

    public function test_admins_can_create_admins_up_to_the_limit_of_five(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->admin()->count(3)->create();

        $this->actingAs($admin)->post('/admin/admins', [
            'username' => 'fifth_admin',
            'email' => 'fifth@example.com',
            'password' => 'secret-password-1',
            'password_confirmation' => 'secret-password-1',
            'district' => 'lilongwe',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(User::where('username', 'fifth_admin')->sole()->isAdmin());

        $this->post('/admin/admins', [
            'username' => 'sixth_admin',
            'email' => 'sixth@example.com',
            'password' => 'secret-password-1',
            'password_confirmation' => 'secret-password-1',
        ])->assertSessionHasErrors('admin');

        $player = User::factory()->create();
        $this->post("/admin/players/{$player->id}/promote")->assertSessionHasErrors('admin');
        $this->assertFalse($player->fresh()->isAdmin());

        $this->artisan('games:make-admin', ['email' => $player->email])->assertFailed();
        $this->assertSame(User::MAX_ADMINS, User::where('role', User::ROLE_ADMIN)->count());
    }

    public function test_admins_can_promote_users_and_revoke_other_admins_but_not_themselves(): void
    {
        $admin = User::factory()->admin()->create();
        $player = User::factory()->create();

        $this->actingAs($admin)->post("/admin/players/{$player->id}/promote")->assertSessionHasNoErrors();
        $this->assertTrue($player->fresh()->isAdmin());

        $this->post("/admin/players/{$player->id}/revoke")->assertSessionHasNoErrors();
        $this->assertFalse($player->fresh()->isAdmin());

        $this->post("/admin/players/{$admin->id}/revoke")->assertSessionHasErrors('admin');
        $this->assertTrue($admin->fresh()->isAdmin());
    }

    public function test_players_cannot_create_admins_or_achievements(): void
    {
        $player = User::factory()->create();

        $this->actingAs($player)->post('/admin/admins', ['username' => 'x'])->assertForbidden();
        $this->post("/admin/players/{$player->id}/promote")->assertForbidden();
        $this->post('/admin/achievements', ['name' => 'x'])->assertForbidden();
    }

    public function test_admin_sign_ins_are_logged(): void
    {
        $admin = User::factory()->admin()->create(['username' => 'boss']);

        $this->post('/admin/login', ['login' => 'boss', 'password' => 'wrong'])->assertSessionHasErrors('login');
        $this->post('/admin/login', ['login' => 'boss', 'password' => 'password'])->assertRedirect('/admin');

        $this->assertSame(['login_failed', 'login'], AdminActivityLog::orderBy('id')->pluck('action')->all());
        $this->assertSame($admin->id, AdminActivityLog::where('action', 'login')->value('user_id'));

        foreach (['/admin/admins', '/admin/admins?action=login', "/admin/admins?admin={$admin->id}", '/admin/players?filter=players&district=zomba',
            "/admin/players/{$admin->id}", '/admin/achievements', '/admin/leaderboards?district=lilongwe'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_district_pages_render(): void
    {
        $this->get('/register')->assertOk();
        $this->get('/forgot-password')->assertOk();
        $this->get('/leaderboard?district=mzimba')->assertOk();
        $this->actingAs(User::factory()->create(['district' => 'mzimba']))->get('/profile/edit')->assertOk();
    }

    public function test_admins_can_create_achievements(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/achievements', [
            'name' => 'Marathon Meeple',
            'description' => 'Play 100 ranked games.',
            'icon' => 'Rocket',
            'criteria' => 'games_played',
            'threshold' => 100,
            'is_active' => true,
        ])->assertSessionHasNoErrors();

        $achievement = Achievement::where('key', 'marathon_meeple')->sole();
        $this->assertSame('games_played', $achievement->criteria);
        $this->assertSame(100, $achievement->threshold);

        $this->post('/admin/achievements', [
            'name' => 'Bad', 'description' => 'x', 'icon' => 'NotAnIcon', 'criteria' => 'made_up', 'threshold' => 1, 'is_active' => true,
        ])->assertSessionHasErrors(['icon', 'criteria']);
    }

    public function test_district_is_set_once_then_locked(): void
    {
        $user = User::factory()->create(['username' => 'Thoko', 'district' => null]);

        $this->actingAs($user)->put('/profile', ['username' => 'Thoko', 'email' => $user->email, 'district' => 'atlantis'])
            ->assertSessionHasErrors('district');
        $this->put('/profile', ['username' => 'Thoko', 'email' => $user->email, 'district' => 'nkhata-bay'])->assertSessionHasNoErrors();
        $this->assertSame('nkhata-bay', $user->fresh()->district);

        $this->put('/profile', ['username' => 'Thoko', 'email' => $user->email, 'district' => 'zomba'])->assertSessionHasErrors('district');
        $this->put('/profile', ['username' => 'Thoko', 'email' => $user->email, 'avatar' => 'star'])->assertSessionHasNoErrors();
        $this->assertSame('nkhata-bay', $user->fresh()->district);
    }

    public function test_approved_district_change_resets_progress_and_shows_a_notice_once(): void
    {
        $user = User::factory()->create(['district' => 'zomba']);
        $admin = User::factory()->admin()->create();

        $this->actingAs($user);
        $this->playTo('pattern-peek', 3);
        $this->assertTrue(GameSession::where('user_id', $user->id)->exists());
        $this->assertGreaterThan(0, PlayerProfile::where('user_id', $user->id)->value('total_points'));

        $this->post('/profile/district-request', ['to_district' => 'zomba'])->assertSessionHasErrors('to_district');
        $this->post('/profile/district-request', ['to_district' => 'mzimba', 'reason' => 'Moved for work'])->assertSessionHasNoErrors();
        $this->post('/profile/district-request', ['to_district' => 'dedza'])->assertSessionHasErrors('to_district');
        $request = DistrictChangeRequest::sole();

        $this->post("/admin/district-requests/{$request->id}/approve")->assertForbidden();

        $this->actingAs($admin)->get('/admin/district-requests')->assertOk();
        $this->post("/admin/district-requests/{$request->id}/approve")->assertSessionHasNoErrors();
        $this->post("/admin/district-requests/{$request->id}/approve")->assertSessionHasErrors('request');

        $this->assertSame('mzimba', $user->fresh()->district);
        foreach ([GameSession::class, PlayerStatistic::class, PlayerProfile::class, PlayerAchievement::class] as $model) {
            $this->assertFalse($model::where('user_id', $user->id)->exists(), "{$model} rows remain");
        }
        $this->assertSame('district-requests.approve', AdminActivityLog::latest('id')->value('action'));

        $this->actingAs($user);
        $this->get('/')->assertInertia(fn ($page) => $page->where('districtNotice.status', 'approved')->where('districtNotice.to', 'Mzimba'));
        $this->post("/district-notices/{$request->id}/dismiss");
        $this->get('/')->assertInertia(fn ($page) => $page->where('districtNotice', null));
    }

    public function test_declined_district_change_keeps_progress(): void
    {
        $user = User::factory()->create(['district' => 'zomba']);
        $this->actingAs($user);
        $this->playTo('pattern-peek', 2);
        $this->post('/profile/district-request', ['to_district' => 'mzimba'])->assertSessionHasNoErrors();

        $this->actingAs(User::factory()->admin()->create())
            ->post('/admin/district-requests/'.DistrictChangeRequest::sole()->id.'/reject', ['note' => 'Please show proof of move'])
            ->assertSessionHasNoErrors();

        $this->assertSame('zomba', $user->fresh()->district);
        $this->assertTrue(GameSession::where('user_id', $user->id)->exists());
        $this->actingAs($user)->get('/')->assertInertia(fn ($page) => $page->where('districtNotice.status', 'rejected'));
    }

    public function test_players_can_cancel_a_pending_request(): void
    {
        $user = User::factory()->create(['district' => 'zomba']);

        $this->actingAs($user)->post('/profile/district-request', ['to_district' => 'mzimba']);
        $this->get('/profile/edit')->assertOk();
        $this->delete('/profile/district-request')->assertSessionHasNoErrors();
        $this->assertSame(0, DistrictChangeRequest::count());
    }

    public function test_leaderboards_filter_by_district_or_region_with_positions_within_it(): void
    {
        $blantyre = User::factory()->create(['district' => 'blantyre']);
        $zomba = User::factory()->create(['district' => 'zomba']);
        $mzimba = User::factory()->create(['district' => 'mzimba']);

        $this->actingAs($mzimba);
        $this->playTo('pattern-peek', 5);
        $this->actingAs($zomba);
        $this->playTo('pattern-peek', 4);
        $this->actingAs($blantyre);
        $this->playTo('pattern-peek', 2);

        $game = Game::where('slug', 'pattern-peek')->sole();
        $rows = fn (?string $district, ?string $region = null) => tap(app(LeaderboardService::class), fn () => cache()->flush())
            ->page(new Board($game, 'level', null, $district, $region))['rows'];
        $ranks = fn (array $rows) => array_map(fn ($r) => [$r['user_id'], $r['position']], $rows);

        $this->assertSame([[$mzimba->id, 1], [$zomba->id, 2], [$blantyre->id, 3]], $ranks($rows(null)));
        $this->assertSame([[$zomba->id, 1], [$blantyre->id, 2]], $ranks($rows(null, 'southern')));
        $this->assertSame([[$blantyre->id, 1]], $ranks($rows('blantyre')));
        $this->assertSame('mzimba', $rows(null)[0]['district']);

        $this->get('/leaderboard?game=pattern-peek&board=level&district=blantyre')->assertOk();
        $this->get('/leaderboard?region=northern')->assertOk();
        $this->get('/leaderboard?district=atlantis')->assertSessionHasErrors('district');
        $this->get('/leaderboard?region=western')->assertSessionHasErrors('region');
    }

    public function test_districts_are_alphabetical(): void
    {
        $names = array_column(Districts::options(), 'name');
        $sorted = $names;
        sort($sorted);

        $this->assertCount(28, $names);
        $this->assertSame($sorted, $names);
    }
}
