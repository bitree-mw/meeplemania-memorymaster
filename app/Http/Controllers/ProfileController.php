<?php

namespace App\Http\Controllers;

use App\Games\Board;
use App\Games\GameRegistry;
use App\Games\LeaderboardService;
use App\Games\StatisticsService;
use App\Models\Achievement;
use App\Models\GameMode;
use App\Models\GameSession;
use App\Models\PlayerAchievement;
use App\Models\User;
use App\Platform\AchievementService;
use App\Platform\Presence\PresenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function me(Request $request): RedirectResponse
    {
        return redirect('/players/'.$request->user()->username);
    }

    public function show(
        Request $request,
        string $username,
        StatisticsService $statistics,
        LeaderboardService $leaderboards,
        PresenceService $presence,
        GameRegistry $registry,
    ): Response {
        $player = User::where('username', $username)->whereNull('disabled_at')->firstOrFail();
        $isSelf = $request->user()?->id === $player->id;
        $profile = $statistics->profile($player->id);
        $stats = $statistics->allFor($player->id);

        $unlocked = PlayerAchievement::where('user_id', $player->id)->get()->keyBy('achievement_id');
        $achievements = Achievement::where('is_active', true)->orderBy('sort_order')->get()
            ->map(fn (Achievement $a) => AchievementService::present($a, $unlocked->get($a->id)?->unlocked_at));

        $perGame = collect($registry->catalogue())->map(function (array $game) use ($stats) {
            $s = $stats->get($game['id']);

            return $game + [
                'games_played' => (int) ($s->games_played ?? 0),
                'total_points' => (int) ($s->total_points ?? 0),
                'best_level' => (int) ($s->best_level ?? 0),
                'best_score' => (int) ($s->best_score ?? 0),
                'best_timed' => [30 => (int) ($s->best_timed_30 ?? 0), 45 => (int) ($s->best_timed_45 ?? 0), 60 => (int) ($s->best_timed_60 ?? 0)],
                'best_avg_unit_ms' => $s?->best_avg_unit_ms,
                'accuracy' => $s?->accuracy() ?? 0,
                'best_session_streak' => (int) ($s->best_session_streak ?? 0),
            ];
        });

        $totals = [
            'correct' => (int) $stats->sum('correct_inputs'),
            'expected' => (int) $stats->sum('expected_inputs'),
        ];

        $recent = GameSession::query()
            ->where('user_id', $player->id)
            ->where('status', '!=', GameSession::STATUS_ACTIVE)
            ->where('rounds_played', '>', 0)
            ->unless($isSelf, fn ($q) => $q->countsForRanking())
            ->latest('ended_at')
            ->limit(12)
            ->get()
            ->map(fn (GameSession $s) => [
                'uuid' => $s->uuid,
                'game' => $registry->gameById($s->game_id)?->slug,
                'mode' => $s->rules['mode'],
                'mode_label' => GameMode::LABELS[$s->rules['mode']],
                'duration' => $s->duration_seconds,
                'status' => $s->status,
                'end_reason' => $s->end_reason,
                'is_ranked' => $s->is_ranked,
                'is_flagged' => $s->is_flagged,
                'score' => $s->final_score,
                'level' => $s->highest_level,
                'rounds_cleared' => $s->rounds_cleared,
                'accuracy' => $s->accuracy,
                'ended_at' => $s->ended_at?->toIso8601String(),
            ]);

        $bestFastest = $stats->pluck('best_avg_unit_ms')->filter()->min();

        return Inertia::render('Profile/Show', [
            'player' => [
                ...$player->toPublicArray(),
                'joined' => $player->created_at->toDateString(),
                'is_online' => $presence->isOnline($player->id),
            ],
            'isSelf' => $isSelf,
            'stats' => [
                'total_points' => (int) ($profile->total_points ?? 0),
                'games_played' => (int) ($profile->games_played ?? 0),
                'rounds_cleared' => (int) ($profile->rounds_cleared ?? 0),
                'best_level' => (int) $stats->max('best_level'),
                'accuracy' => $totals['expected'] ? round($totals['correct'] / $totals['expected'] * 100, 1) : 0,
                'best_avg_unit_ms' => $bestFastest ? (int) $bestFastest : null,
                'best_session_streak' => (int) $stats->max('best_session_streak'),
                'current_daily_streak' => $statistics->displayDailyStreak($profile),
                'longest_daily_streak' => (int) ($profile->longest_daily_streak ?? 0),
                'games_tried' => $stats->filter(fn ($s) => $s->games_played > 0)->count(),
            ],
            'perGame' => $perGame,
            'rank' => $leaderboards->rankFor(new Board(null, 'all_time'), $player->id),
            'achievements' => $achievements,
            'recent' => $recent,
        ]);
    }

    public function edit(): Response
    {
        return Inertia::render('Profile/Edit', ['avatars' => User::AVATARS]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:24', 'regex:/^[A-Za-z0-9_]+$/', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'avatar' => ['nullable', Rule::in(User::AVATARS)],
        ], [
            'username.regex' => 'Usernames may only contain letters, numbers and underscores.',
        ]);

        $user->update($data);

        return back()->with('success', 'Profile updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $request->user()->update(['password' => $data['password']]);

        return back()->with('success', 'Password changed.');
    }
}
