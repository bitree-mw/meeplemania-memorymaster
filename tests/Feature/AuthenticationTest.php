<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
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
        ])->assertRedirect('/');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['username' => 'MeepleFan', 'avatar' => 'dice', 'role' => 'player']);

        auth()->logout();
        $this->post('/register', [
            'username' => 'MeepleFan',
            'email' => 'other@example.com',
            'password' => 'secret-password-1',
            'password_confirmation' => 'secret-password-1',
        ])->assertSessionHasErrors('username');
    }

    public function test_registration_cannot_escalate_to_admin(): void
    {
        $this->post('/register', [
            'username' => 'Sneaky',
            'email' => 'sneaky@example.com',
            'password' => 'secret-password-1',
            'password_confirmation' => 'secret-password-1',
            'role' => 'admin',
        ]);

        $this->assertSame('player', User::where('username', 'Sneaky')->value('role'));
    }

    public function test_players_can_log_in_and_out(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_wrong_password_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'nope'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_disabled_accounts_cannot_log_in_and_are_signed_out(): void
    {
        $user = User::factory()->disabled()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->actingAs($user)->get('/profile/edit')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_password_reset_flow(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'brand-new-password',
                'password_confirmation' => 'brand-new-password',
            ])->assertRedirect('/login');

            return true;
        });

        $this->post('/login', ['email' => $user->email, 'password' => 'brand-new-password']);
        $this->assertAuthenticatedAs($user);
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
