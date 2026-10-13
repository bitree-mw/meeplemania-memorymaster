<?php

use App\Games\Board;
use App\Games\DailyChallengeService;
use App\Games\GameRegistry;
use App\Games\LeaderboardService;
use App\Games\PlayService;
use App\Models\User;
use App\Platform\AdminAccounts;
use App\Platform\AdminActivity;
use App\Platform\PasswordResetLinks;
use App\Platform\Presence\PresenceService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

Artisan::command('games:expire-sessions', function (PlayService $play) {
    $this->info('Closed '.$play->expireIdleSessions().' idle or timed-out run(s).');
})->purpose('Finalize runs left idle and Timed Rush runs past their clock');

Artisan::command('games:daily-rollover', function (DailyChallengeService $daily, LeaderboardService $leaderboards, GameRegistry $registry) {
    foreach ($registry->games() as $game) {
        $daily->today($game);
    }
    $this->info('Settled '.$daily->grantPendingRewards().' Try Once challenge(s).');

    $durations = config('games.platform.timed_durations');
    foreach ($registry->games() as $game) {
        foreach (Board::GAME_CATEGORIES as $category) {
            $category === 'timed'
                ? array_map(fn ($d) => $leaderboards->ensureSnapshot(new Board($game, 'timed', $d)), $durations)
                : $leaderboards->ensureSnapshot(new Board($game, $category));
        }
    }
    foreach (Board::PLATFORM_CATEGORIES as $category) {
        $leaderboards->ensureSnapshot(new Board(null, $category));
    }
    $this->info('Leaderboard snapshots taken.');
})->purpose("Create today's Try Once challenges, award yesterday's badges and snapshot leaderboards");

Artisan::command('games:prune-presence', function (PresenceService $presence) {
    $this->info('Pruned '.$presence->prune().' presence record(s).');
})->purpose('Remove stale online presence rows');

Artisan::command('games:prune-reset-links', function (PasswordResetLinks $links) {
    $this->info('Deleted '.$links->prune().' expired password reset link(s).');
})->purpose('Delete expired password reset links');

Artisan::command('games:make-admin {email}', function (string $email, AdminAccounts $admins, AdminActivity $activity) {
    $user = User::where('email', $email)->first();

    if (! $user) {
        $this->error("No user with email {$email}.");

        return 1;
    }

    try {
        $admins->promote($user);
    } catch (ValidationException $e) {
        $this->error($e->getMessage());

        return 1;
    }

    $activity->record('players.promote', "Granted admin access to {$user->username} (console)");
    $this->info("{$user->username} is now an administrator.");
})->purpose('Grant administrator access to an existing account (max '.User::MAX_ADMINS.' admins)');

// The password is prompted for (never passed as an argument) and stored hashed via the User cast.
Artisan::command('games:create-admin {username} {email}', function (string $username, string $email, AdminAccounts $admins, AdminActivity $activity) {
    $password = $this->secret('Password');

    $validator = Validator::make(compact('username', 'email', 'password'), [
        'username' => ['required', 'string', 'min:3', 'max:24', 'regex:/^[A-Za-z0-9_]+$/', Rule::unique('users', 'username')],
        'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')],
        'password' => ['required', Password::defaults()],
    ]);

    try {
        $admins->create($validator->validate());
    } catch (ValidationException $e) {
        foreach ($e->validator->errors()->all() as $error) {
            $this->error($error);
        }

        return 1;
    }

    $activity->record('admins.store', "Created admin account {$username} (console)");
    $this->info("Administrator {$username} created.");
})->purpose('Create a new administrator account (max '.User::MAX_ADMINS.' admins)');

Schedule::command('games:expire-sessions')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('games:prune-presence')->hourly();
Schedule::command('games:prune-reset-links')->hourly();
// Idempotent, so running hourly picks up midnight in whatever timezone admins configure.
Schedule::command('games:daily-rollover')->hourly()->withoutOverlapping();
