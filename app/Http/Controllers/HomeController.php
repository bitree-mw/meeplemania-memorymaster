<?php

namespace App\Http\Controllers;

use App\Games\Board;
use App\Games\DailyChallengeService;
use App\Games\GameRegistry;
use App\Games\LeaderboardService;
use App\Games\StatisticsService;
use App\Models\DailyChallengeAttempt;
use App\Platform\Presence\PresenceService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(
        Request $request,
        GameRegistry $registry,
        StatisticsService $statistics,
        LeaderboardService $leaderboards,
        DailyChallengeService $daily,
        PresenceService $presence,
    ): Response {
        $user = $request->user();
        $stats = $user ? $statistics->allFor($user->id) : collect();
        $challenges = collect($registry->games())->map(fn ($game) => $daily->today($game));
        $attempts = $user
            ? DailyChallengeAttempt::where('user_id', $user->id)->whereIn('daily_challenge_id', $challenges->pluck('id'))->with('session')->get()->keyBy('daily_challenge_id')
            : collect();

        $games = collect($registry->catalogue())->map(function (array $game) use ($user, $stats, $challenges, $attempts, $leaderboards, $registry) {
            $s = $stats->get($game['id']);
            $challenge = $challenges->get($game['slug']);
            $attempt = $attempts->get($challenge->id);
            // Rankings are for members only.
            $leader = $user ? ($leaderboards->podium(new Board($registry->game($game['slug']), 'once'))[0] ?? null) : null;

            return $game + [
                'best' => [
                    'level' => (int) ($s->best_level ?? 0),
                    'timed' => [30 => (int) ($s->best_timed_30 ?? 0), 45 => (int) ($s->best_timed_45 ?? 0), 60 => (int) ($s->best_timed_60 ?? 0)],
                    'games' => (int) ($s->games_played ?? 0),
                ],
                'daily' => [
                    'attempted' => $attempt !== null,
                    'in_progress' => (bool) $attempt?->session?->isActive(),
                    'my_score' => $attempt && ! $attempt->session->isActive() ? $attempt->session->final_score : null,
                    'starting_level' => $challenge->starting_level,
                    'max_levels' => $challenge->max_levels,
                    'leader' => $leader ? ['username' => $leader['username'], 'score' => (int) $leader['score']] : null,
                ],
            ];
        });

        $me = null;
        if ($user) {
            $profile = $statistics->profile($user->id);
            $me = [
                'total_points' => (int) ($profile->total_points ?? 0),
                'daily_streak' => $statistics->displayDailyStreak($profile),
                'games_played' => (int) ($profile->games_played ?? 0),
                'games_tried' => $stats->filter(fn ($s) => $s->games_played > 0)->count(),
                'rank' => $leaderboards->rankFor(new Board(null, 'all_time'), $user->id),
            ];
        }

        $season = $leaderboards->currentSeason();

        return Inertia::render('Home', [
            'games' => $games,
            'durations' => config('games.platform.timed_durations'),
            'me' => $me,
            'season' => $season ? ['name' => $season->name, 'ends_on' => $season->ends_on->toDateString()] : null,
            'online' => $presence->counts(),
        ]);
    }
}
