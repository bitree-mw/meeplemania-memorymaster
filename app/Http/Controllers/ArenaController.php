<?php

namespace App\Http\Controllers;

use App\Games\DailyChallengeService;
use App\Games\GameRegistry;
use App\Games\StatisticsService;
use App\Models\GameMode;
use App\Platform\Device;
use App\Platform\GuestPlays;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ArenaController extends Controller
{
    public function __invoke(
        Request $request,
        string $game,
        string $mode,
        GameRegistry $registry,
        StatisticsService $statistics,
        DailyChallengeService $daily,
        GuestPlays $guests,
    ): Response|RedirectResponse {
        $gameModel = $registry->game($game);
        $gameMode = $gameModel ? $registry->mode($gameModel, $mode) : null;

        abort_if(! $gameModel || ! $gameModel->is_active || ! $gameMode || ! $gameMode->is_active || ! $registry->has($game), 404);

        $user = $request->user();

        if ($gameMode->requires_account && ! $user) {
            return redirect()->guest('/login')->with('error', 'Sign in to take part in Try Once.');
        }

        if (! $user && $guests->exhausted(Device::id($request))) {
            return redirect()->guest('/register')->with('error', $guests->limitMessage());
        }

        $durations = config('games.platform.timed_durations');
        $duration = (int) $request->query('duration', $durations[0]);
        if (! in_array($duration, $durations, true)) {
            $duration = $durations[0];
        }

        $stats = $user ? $statistics->for($user->id, $gameModel->id) : null;

        return Inertia::render('Arena', [
            'game' => $registry->definition($game)->toArray() + ['how_to' => $registry->definition($game)->howToPlay()],
            'mode' => ['slug' => $gameMode->slug, 'label' => $gameMode->label()],
            'durations' => $durations,
            'duration' => $gameMode->slug === GameMode::TIMED ? $duration : null,
            'personalBests' => $user ? [
                'level' => (int) ($stats->best_level ?? 0),
                'timed' => [30 => (int) ($stats->best_timed_30 ?? 0), 45 => (int) ($stats->best_timed_45 ?? 0), 60 => (int) ($stats->best_timed_60 ?? 0)],
            ] : null,
            'dailyAttempted' => $gameMode->slug === GameMode::ONCE && $user
                ? $daily->attemptFor($daily->today($gameModel), $user->id) !== null
                : false,
        ]);
    }
}
