<?php

namespace App\Http\Controllers;

use App\Games\Board;
use App\Games\GameRegistry;
use App\Games\LeaderboardService;
use App\Platform\Districts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LeaderboardController extends Controller
{
    public function __invoke(Request $request, LeaderboardService $leaderboards, GameRegistry $registry): Response|RedirectResponse
    {
        // The Hall of Fame is for members only.
        if (! $request->user()) {
            return redirect()->guest('/login')->with('error', 'Sign in or create an account to see the Hall of Fame.');
        }

        [$board, $search, $page] = self::resolveBoard($request, $registry);

        $leaderboards->ensureSnapshot($board);
        $result = $leaderboards->page($board, $page, $search);
        $season = $leaderboards->currentSeason();
        $user = $request->user();
        $me = $user ? $leaderboards->entryFor($board, $user->id) : null;

        // Below the podium: show where they stand, two places either side.
        $around = $me && ! $search && (int) $me['position'] > 3 ? $leaderboards->around($board, (int) $me['position']) : [];

        return Inertia::render('Leaderboard', [
            ...self::boardProps($board, $registry),
            'search' => $search,
            'rows' => $result['rows'],
            'meta' => $result['meta'],
            'podium' => $search ? [] : $leaderboards->podium($board),
            'me' => $me,
            'around' => $around,
            'totalRanked' => $search ? $leaderboards->totalRanked($board) : $result['meta']['total'],
            'season' => $season ? ['name' => $season->name, 'ends_on' => $season->ends_on->toDateString()] : null,
        ]);
    }

    /** @return array{0: Board, 1: ?string, 2: int} */
    public static function resolveBoard(Request $request, GameRegistry $registry): array
    {
        $durations = config('games.platform.timed_durations');

        $data = $request->validate([
            'game' => ['nullable', Rule::in(['all', ...$registry->slugs()])],
            'board' => ['nullable', Rule::in(array_unique([...Board::GAME_CATEGORIES, ...Board::PLATFORM_CATEGORIES]))],
            'duration' => ['nullable', 'integer', Rule::in($durations)],
            'district' => ['nullable', Rule::in(Districts::keys())],
            'region' => ['nullable', Rule::in(array_keys(Districts::regions()))],
            'search' => ['nullable', 'string', 'max:24'],
            'page' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);

        $game = ($data['game'] ?? 'all') === 'all' ? null : $registry->game($data['game']);
        $allowed = $game ? Board::GAME_CATEGORIES : Board::PLATFORM_CATEGORIES;
        $category = in_array($data['board'] ?? null, $allowed, true) ? $data['board'] : ($game ? 'level' : 'all_time');
        $duration = $category === 'timed' ? (int) ($data['duration'] ?? $durations[0]) : null;

        return [
            new Board($game, $category, $duration, $data['district'] ?? null, isset($data['district']) ? null : ($data['region'] ?? null)),
            trim((string) ($data['search'] ?? '')) ?: null,
            (int) ($data['page'] ?? 1),
        ];
    }

    public static function boardProps(Board $board, GameRegistry $registry): array
    {
        $categories = $board->isPlatform() ? Board::PLATFORM_CATEGORIES : Board::GAME_CATEGORIES;

        return [
            'games' => $registry->catalogue(),
            'game' => $board->game?->slug ?? 'all',
            'board' => $board->category,
            'duration' => $board->duration,
            'durations' => config('games.platform.timed_durations'),
            'categories' => array_map(fn ($c) => ['key' => $c, 'label' => Board::LABELS[$c]], $categories),
            'district' => $board->district,
            'districts' => Districts::options(),
            'region' => $board->region,
            'regions' => Districts::regionOptions(),
            'label' => $board->label(),
            'rule' => $board->rule(),
        ];
    }
}
