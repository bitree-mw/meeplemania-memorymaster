<?php

use App\Games\Board;
use App\Games\DailyChallengeService;
use App\Games\GameRegistry;
use App\Games\LeaderboardService;
use App\Games\PlayService;
use App\Models\User;
use App\Platform\Presence\PresenceService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

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

Artisan::command('games:make-admin {email}', function (string $email) {
    $user = User::where('email', $email)->first();

    if (! $user) {
        $this->error("No user with email {$email}.");

        return 1;
    }

    $user->forceFill(['role' => User::ROLE_ADMIN])->save();
    $this->info("{$user->username} is now an administrator.");
})->purpose('Grant administrator access to an existing account');

Schedule::command('games:expire-sessions')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('games:prune-presence')->hourly();
// Idempotent, so running hourly picks up midnight in whatever timezone admins configure.
Schedule::command('games:daily-rollover')->hourly()->withoutOverlapping();
