<?php

namespace App\Http\Controllers\Admin;

use App\Games\GameRegistry;
use App\Games\GameSettings;
use App\Http\Controllers\Controller;
use App\Models\GameMode;
use App\Models\GameSession;
use App\Models\PlayerStatistic;
use App\Models\User;
use App\Platform\Presence\PresenceService;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(GameSettings $settings, GameRegistry $registry, PresenceService $presence): Response
    {
        $tzNow = $settings->now();
        $todayStart = $tzNow->startOfDay()->utc();
        $weekAgo = now()->subDays(7);
        $presenceCounts = $presence->counts();
        $played = fn () => GameSession::query()->where('rounds_played', '>', 0);
        $gameName = fn ($id) => $registry->definition($registry->gameById($id)->slug)->name();

        $avgDuration = $played()->whereNotNull('ended_at')->where('started_at', '>=', $weekAgo)
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, started_at, ended_at)) as avg_seconds')
            ->value('avg_seconds');

        $topLevel = PlayerStatistic::query()->where('best_level', '>', 0)
            ->orderByDesc('best_level')->orderBy('best_level_at')
            ->with('user:id,username')->first();

        // Usage by game and mode over the last 7 days.
        $usage = $played()->where('started_at', '>=', $weekAgo)
            ->join('game_modes', 'game_modes.id', '=', 'game_sessions.game_mode_id')
            ->select('game_sessions.game_id', 'game_modes.slug as mode', DB::raw('COUNT(*) as games'))
            ->groupBy('game_sessions.game_id', 'game_modes.slug')
            ->get();

        $gameUsage = $usage->groupBy('game_id')
            ->map(fn ($rows, $gameId) => [
                'game' => $gameName($gameId),
                'games' => (int) $rows->sum('games'),
                'modes' => collect(GameMode::ALL)->mapWithKeys(fn ($m) => [GameMode::LABELS[$m] => (int) $rows->where('mode', $m)->sum('games')]),
            ])
            ->sortByDesc('games')->values();

        // Games per day for 14 days, bucketed by the timezone's current UTC offset.
        $offsetMinutes = (int) $tzNow->utcOffset();
        $from = $tzNow->subDays(13)->startOfDay();
        $daily = $played()->where('started_at', '>=', $from->utc())
            ->selectRaw('DATE(DATE_ADD(started_at, INTERVAL ? MINUTE)) as day, COUNT(*) as games', [$offsetMinutes])
            ->groupBy('day')->pluck('games', 'day');

        $series = collect(range(0, 13))->map(fn ($i) => [
            'day' => $day = $from->addDays($i)->toDateString(),
            'games' => (int) ($daily[$day] ?? 0),
        ]);

        $recent = GameSession::query()->where('rounds_played', '>', 0)->where('status', '!=', GameSession::STATUS_ACTIVE)
            ->with('user:id,username')->latest('ended_at')->limit(8)->get()
            ->map(fn (GameSession $s) => [
                'uuid' => $s->uuid,
                'player' => $s->user?->username,
                'game' => $gameName($s->game_id),
                'mode' => GameMode::LABELS[$s->rules['mode']].($s->duration_seconds ? " {$s->duration_seconds}s" : ''),
                'score' => $s->final_score,
                'level' => $s->highest_level,
                'status' => $s->status,
                'is_ranked' => $s->is_ranked,
                'is_flagged' => $s->is_flagged,
                'ended_at' => $s->ended_at?->toIso8601String(),
            ]);

        return Inertia::render('Admin/Dashboard', [
            'metrics' => [
                'registered_players' => User::count(),
                'online_players' => $presenceCounts['players'],
                'online_guests' => $presenceCounts['guests'],
                'playing_now' => $presenceCounts['playing'],
                'games_today' => $played()->where('started_at', '>=', $todayStart)->count(),
                'daily_active_players' => $played()->where('started_at', '>=', $todayStart)->whereNotNull('user_id')->distinct()->count('user_id'),
                'avg_session_seconds' => $avgDuration !== null ? (int) round($avgDuration) : null,
                'highest_level' => $topLevel ? ['level' => $topLevel->best_level, 'player' => $topLevel->user->username, 'game' => $gameName($topLevel->game_id)] : null,
                'popular_game' => $gameUsage->first(),
                'pending_reviews' => GameSession::where('is_flagged', true)->whereNull('reviewed_at')->count(),
                'disabled_accounts' => User::whereNotNull('disabled_at')->count(),
            ],
            'gameUsage' => $gameUsage,
            'series' => $series,
            'recent' => $recent,
            'timezone' => $settings->timezone(),
        ]);
    }
}
