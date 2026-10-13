<?php

namespace App\Http\Controllers;

use App\Games\Board;
use App\Games\GameRegistry;
use App\Games\LeaderboardService;
use App\Games\StatisticsService;
use App\Models\Achievement;
use App\Models\DistrictChangeRequest;
use App\Models\GameMode;
use App\Models\GameSession;
use App\Models\PlayerAchievement;
use App\Models\User;
use App\Platform\AchievementService;
use App\Platform\DistrictChanges;
use App\Platform\Districts;
use App\Platform\Presence\PresenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
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
                'district' => Districts::name($player->district),
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

    public function edit(Request $request, DistrictChanges $changes): Response
    {
        $user = $request->user();
        $pending = $changes->pendingFor($user);
        $lastDeclined = DistrictChangeRequest::where('user_id', $user->id)->where('status', DistrictChangeRequest::REJECTED)
            ->where('reviewed_at', '>', now()->subDays(30))->latest('reviewed_at')->first();

        return Inertia::render('Profile/Edit', [
            'avatars' => User::AVATARS,
            'districts' => Districts::options(),
            'districtName' => Districts::name($user->district),
            'pendingRequest' => $pending ? [
                'to' => Districts::name($pending->to_district),
                'reason' => $pending->reason,
                'requested_at' => $pending->created_at->toIso8601String(),
            ] : null,
            'declinedRequest' => ! $pending && $lastDeclined ? [
                'to' => Districts::name($lastDeclined->to_district),
                'note' => $lastDeclined->review_note,
                'reviewed_at' => $lastDeclined->reviewed_at->toIso8601String(),
            ] : null,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:24', 'regex:/^[A-Za-z0-9_]+$/', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'avatar' => ['nullable', Rule::in(User::AVATARS)],
            'district' => ['nullable', Rule::in(Districts::keys())],
        ], [
            'username.regex' => 'Usernames may only contain letters, numbers and underscores.',
        ]);

        // A district is chosen once; after that only an admin can change it (see requestDistrictChange).
        if ($user->district !== null) {
            if (($data['district'] ?? $user->district) !== $user->district) {
                throw ValidationException::withMessages(['district' => 'Your district is locked. Ask an admin to change it.']);
            }

            unset($data['district']);
        }

        $user->update($data);

        return back()->with('success', 'Profile updated.');
    }

    public function requestDistrictChange(Request $request, DistrictChanges $changes): RedirectResponse
    {
        $data = $request->validate([
            'to_district' => ['required', Rule::in(Districts::keys())],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $changes->request($request->user(), $data['to_district'], $data['reason'] ?? null);

        return back()->with('success', 'Request sent. An admin will review it soon.');
    }

    public function cancelDistrictChange(Request $request, DistrictChanges $changes): RedirectResponse
    {
        $changes->pendingFor($request->user())?->delete();

        return back()->with('success', 'District change request cancelled.');
    }

    public function dismissDistrictNotice(Request $request, DistrictChangeRequest $districtRequest, DistrictChanges $changes): RedirectResponse
    {
        $changes->dismiss($request->user(), $districtRequest->id);

        return back();
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
