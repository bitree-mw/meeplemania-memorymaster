<?php

namespace Tests\Feature;

use App\Games\GameSettings;
use App\Models\User;
use App\Platform\Device;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GuestAccessTest extends TestCase
{
    private const DEVICE = 'dddddddddddddddddddddddddddddddddddddddd';

    private function limitGuests(int $nudgeAfter, int $max): void
    {
        app(GameSettings::class)->update(GameSettings::PLATFORM, 'guests', ['nudge_after_plays' => $nudgeAfter, 'max_plays' => $max]);
    }

    /** Another browser session on this device: signed out, with only the device cookie (and optionally a session cookie). */
    private function freshSession(?string $sessionId = null): void
    {
        $this->app['auth']->forgetGuards();
        session()->flush();
        $this->defaultCookies = array_filter([Device::COOKIE => self::DEVICE, config('session.cookie') => $sessionId]);
    }

    public function test_guests_are_nudged_then_required_to_create_an_account(): void
    {
        $this->limitGuests(nudgeAfter: 2, max: 3);

        $this->playTo('memory-master', 1);
        $this->begin('pattern-peek'); // opened but no round played: doesn't count
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('guestPlays', ['played' => 1, 'limit' => 3, 'remaining' => 2, 'nudge' => false]));

        $this->playTo('dice-digits', 1);
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('guestPlays.nudge', true)->where('guestPlays.remaining', 1));
        $this->get('/play/memory-master/classic')->assertOk();

        $this->playTo('spot-the-swap', 1);
        $this->postJson('/api/play/sessions', ['game' => 'memory-master', 'mode' => 'classic'])
            ->assertForbidden()->assertJson(['code' => 'guest_limit']);
        $this->get('/play/memory-master/classic')->assertRedirect('/register')->assertSessionHas('error');

        // Players are not limited, and see no guest counter.
        $this->actingAs(User::factory()->create());
        $this->begin('memory-master');
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('guestPlays', null));
    }

    public function test_guests_cannot_see_rankings(): void
    {
        $this->get('/leaderboard')->assertRedirect('/login');
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('games.0.daily.leader', null));
    }

    public function test_a_device_holds_one_signed_in_account_at_a_time(): void
    {
        [$a, $b] = User::factory()->count(2)->create();
        $this->freshSession();

        $this->post('/login', ['login' => $a->email, 'password' => 'password'])->assertRedirect('/');
        $this->assertAuthenticatedAs($a);

        // While A is signed in here, nobody can sign in as or create another account on this device.
        $this->freshSession();
        $this->post('/login', ['login' => $b->email, 'password' => 'password'])->assertSessionHasErrors(['login' => Device::BUSY_MESSAGE]);
        $this->assertGuest();
        $this->post('/register', [
            'username' => 'SecondAccount', 'email' => 'second@example.com',
            'password' => 'secret-password-1', 'password_confirmation' => 'secret-password-1', 'district' => 'lilongwe',
        ])->assertSessionHasErrors(['username' => Device::BUSY_MESSAGE]);
        $this->assertDatabaseMissing('users', ['username' => 'SecondAccount']);

        // A may sign in again; once A signs out, the device is free.
        $this->freshSession();
        $this->post('/login', ['login' => $a->email, 'password' => 'password'])->assertRedirect('/');
        $this->freshSession(session()->getId());
        $this->app['auth']->guard('web')->setUser($a);
        $this->post('/logout')->assertRedirect('/');

        $this->freshSession();
        $this->post('/login', ['login' => $b->email, 'password' => 'password'])->assertRedirect('/');
        $this->assertAuthenticatedAs($b);
    }
}
