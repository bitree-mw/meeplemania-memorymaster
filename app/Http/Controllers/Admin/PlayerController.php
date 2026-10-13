<?php

namespace App\Http\Controllers\Admin;

use App\Games\GameRegistry;
use App\Games\StatisticsService;
use App\Http\Controllers\Controller;
use App\Models\GameMode;
use App\Models\GameSession;
use App\Models\PlayerAchievement;
use App\Models\User;
use App\Platform\AdminAccounts;
use App\Platform\Districts;
use App\Platform\PasswordResetLinks;
use App\Platform\Presence\PresenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PlayerController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $filter = $request->query('filter');
        $district = in_array($request->query('district'), Districts::keys(), true) ? $request->query('district') : null;
        $like = '%'.addcslashes($search, '%_\\').'%';

        $players = User::query()
            ->leftJoin('player_profiles as pp', 'pp.user_id', '=', 'users.id')
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('users.username', 'like', $like)->orWhere('users.email', 'like', $like)))
            ->when($filter === 'disabled', fn ($q) => $q->whereNotNull('users.disabled_at'))
            ->when($filter === 'admins', fn ($q) => $q->where('users.role', User::ROLE_ADMIN))
            ->when($filter === 'players', fn ($q) => $q->where('users.role', User::ROLE_PLAYER))
            ->when($filter === 'active', fn ($q) => $q->whereNull('users.disabled_at'))
            ->when($district, fn ($q) => $q->where('users.district', $district))
            ->select('users.*', 'pp.total_points', 'pp.games_played', 'pp.current_daily_streak')
            ->orderByDesc('users.created_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (User $u) => [
                'id' => $u->id,
                'username' => $u->username,
                'email' => $u->email,
                'avatar' => $u->avatar ?? 'meeple',
                'role' => $u->role,
                'district' => Districts::name($u->district),
                'disabled' => $u->isDisabled(),
                'joined' => $u->created_at->toDateString(),
                'total_points' => (int) $u->total_points,
                'games_played' => (int) $u->games_played,
            ]);

        return Inertia::render('Admin/Players', [
            'players' => $players,
            'search' => $search,
            'filter' => $filter,
            'district' => $district,
            'districts' => Districts::options(),
        ]);
    }

    public function show(User $player, StatisticsService $statistics, PresenceService $presence, GameRegistry $registry, AdminAccounts $admins): Response
    {
        $profile = $statistics->profile($player->id);
        $stats = $statistics->allFor($player->id);

        $sessions = GameSession::query()
            ->where('user_id', $player->id)
            ->latest('started_at')
            ->paginate(15)
            ->through(fn (GameSession $s) => [
                'uuid' => $s->uuid,
                'game' => $registry->definition($registry->gameById($s->game_id)->slug)->name(),
                'mode' => GameMode::LABELS[$s->rules['mode']].($s->duration_seconds ? " {$s->duration_seconds}s" : ''),
                'status' => $s->status,
                'end_reason' => $s->end_reason,
                'is_ranked' => $s->is_ranked,
                'is_flagged' => $s->is_flagged,
                'flag_reason' => $s->flag_reason,
                'score' => $s->final_score,
                'level' => $s->highest_level,
                'rounds' => $s->rounds_played,
                'accuracy' => $s->accuracy,
                'avg_unit_ms' => $s->avg_unit_ms,
                'started_at' => $s->started_at->toIso8601String(),
                'duration' => $s->durationSeconds(),
            ]);

        return Inertia::render('Admin/PlayerShow', [
            'player' => [
                ...$player->toPublicArray(),
                'email' => $player->email,
                'role' => $player->role,
                'district' => Districts::name($player->district),
                'disabled' => $player->isDisabled(),
                'disabled_reason' => $player->disabled_reason,
                'joined' => $player->created_at->toDateString(),
                'is_online' => $presence->isOnline($player->id),
            ],
            'profile' => $profile ? [
                'games_played' => $profile->games_played,
                'total_points' => $profile->total_points,
                'current_daily_streak' => $statistics->displayDailyStreak($profile),
                'longest_daily_streak' => $profile->longest_daily_streak,
            ] : null,
            'perGame' => collect($registry->catalogue())->map(fn ($g) => [
                'name' => $g['name'],
                'games_played' => (int) ($stats->get($g['id'])->games_played ?? 0),
                'best_level' => (int) ($stats->get($g['id'])->best_level ?? 0),
                'best_timed_60' => (int) ($stats->get($g['id'])->best_timed_60 ?? 0),
                'accuracy' => $stats->get($g['id'])?->accuracy() ?? 0,
            ]),
            'achievements' => PlayerAchievement::where('user_id', $player->id)->with('achievement')->latest('unlocked_at')->get()
                ->map(fn ($pa) => ['name' => $pa->achievement->name, 'unlocked_at' => $pa->unlocked_at->toIso8601String()]),
            'sessions' => $sessions,
            'adminSlotsLeft' => $admins->slotsLeft(),
            'maxAdmins' => User::MAX_ADMINS,
        ]);
    }

    public function disable(Request $request, User $player): RedirectResponse
    {
        abort_if($player->is($request->user()), 422, 'You cannot disable your own account.');

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        $player->forceFill(['disabled_at' => now(), 'disabled_reason' => $data['reason'] ?? null])->save();

        return back()->with('success', "{$player->username} has been disabled and removed from leaderboards.");
    }

    public function enable(User $player): RedirectResponse
    {
        $player->forceFill(['disabled_at' => null, 'disabled_reason' => null])->save();

        return back()->with('success', "{$player->username} has been re-enabled.");
    }

    public function sendPasswordReset(Request $request, User $player, PasswordResetLinks $links): RedirectResponse
    {
        if (! $links->send($player, $request->user())) {
            throw ValidationException::withMessages(['reset' => 'A reset link was sent to this account moments ago. Try again in a minute.']);
        }

        return back()->with('success', "A password reset link has been emailed to {$player->username}. It expires in {$links->expiresInMinutes()} minutes.");
    }
}
