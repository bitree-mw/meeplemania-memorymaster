<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    public function test_players_can_register_with_a_unique_username(): void
    {
        $this->post('/register', [
            'username' => 'MeepleFan',
            'email' => 'fan@example.com',
            'password' => 'secret-password-1',
            'password_confirmation' => 'secret-password-1',
            'avatar' => 'dice',
            'district' => 'lilongwe',
        ])->assertRedirect('/');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['username' => 'MeepleFan', 'avatar' => 'dice', 'role' => 'player', 'district' => 'lilongwe']);

        auth()->logout();
        $this->post('/register', [
            'username' => 'MeepleFan',
            'email' => 'other@example.com',
            'password' => 'secret-password-1',
            'password_confirmation' => 'secret-password-1',
        ])->assertSessionHasErrors(['username', 'district']);
    }

    public function test_registration_cannot_escalate_to_admin(): void
    {
        $this->post('/register', [
            'username' => 'Sneaky',
            'email' => 'sneaky@example.com',
            'password' => 'secret-password-1',
            'password_confirmation' => 'secret-password-1',
            'district' => 'zomba',
            'role' => 'admin',
        ]);

        $this->assertSame('player', User::where('username', 'Sneaky')->value('role'));
    }

    public function test_players_can_log_in_and_out(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['login' => $user->email, 'password' => 'password'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();

        $this->post('/login', ['login' => $user->username, 'password' => 'password'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    public function test_admins_sign_in_on_the_admin_login_page(): void
    {
        $this->get('/admin/login')->assertOk();

        $admin = User::factory()->admin()->create();

        $this->post('/admin/login', ['login' => $admin->email, 'password' => 'password'])->assertRedirect('/admin');
        $this->assertAuthenticatedAs($admin);

        $this->post('/logout');
        $this->post('/admin/login', ['login' => $admin->username, 'password' => 'password'])->assertRedirect('/admin');
        $this->assertAuthenticatedAs($admin);
    }

    public function test_players_cannot_sign_in_on_the_admin_login_page(): void
    {
        $user = User::factory()->create();

        $this->post('/admin/login', ['login' => $user->email, 'password' => 'password'])->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_wrong_password_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['login' => $user->email, 'password' => 'nope'])->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_disabled_accounts_cannot_log_in_and_are_signed_out(): void
    {
        $user = User::factory()->disabled()->create();

        $this->post('/login', ['login' => $user->email, 'password' => 'password'])->assertSessionHasErrors('login');
        $this->assertGuest();

        $this->actingAs($user)->get('/profile/edit')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_profile_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/profile', ['username' => 'NewName', 'email' => $user->email, 'avatar' => 'star'])
            ->assertSessionHasNoErrors();

        $this->assertSame('NewName', $user->fresh()->username);
    }

    public function test_public_pages_render(): void
    {
        $this->get('/')->assertOk();
        $this->get('/rules')->assertOk();
        $this->get('/leaderboard')->assertOk();

        foreach (self::GAMES as $game) {
            $this->get("/play/{$game}/classic")->assertOk();
            $this->get("/play/{$game}/timed?duration=45")->assertOk();
        }

        $user = User::factory()->create();
        $this->get("/players/{$user->username}")->assertOk();
        $this->actingAs($user)->get("/players/{$user->username}")->assertOk();
        $this->get('/play/memory-master/once')->assertOk();
    }
}
