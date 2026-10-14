<?php

namespace App\Http\Controllers\Admin;

use App\Games\GameRegistry;
use App\Games\LeaderboardService;
use App\Games\StatisticsService;
use App\Http\Controllers\Controller;
use App\Models\GameMode;
use App\Models\GameSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Suspicious results. Runs the verifier flagged are held out of statistics and
 * leaderboards until an admin approves them; any finished run can be voided.
 */
class ReviewController extends Controller
{
    public function index(Request $request, GameRegistry $registry): Response
    {
        $showReviewed = $request->boolean('reviewed');

        $sessions = GameSession::query()
            ->where(fn ($q) => $q->where('is_flagged', true)->orWhere('status', GameSession::STATUS_VOIDED))
            ->when(! $showReviewed, fn ($q) => $q->whereNull('reviewed_at'))
            ->when($showReviewed, fn ($q) => $q->whereNotNull('reviewed_at'))
            ->with(['user:id,username', 'rounds'])
            ->latest('ended_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (GameSession $s) => [
                'uuid' => $s->uuid,
                'player' => $s->user?->username,
                'player_id' => $s->user_id,
                'game' => $registry->definition($registry->gameById($s->game_id)->slug)->name(),
                'mode' => GameMode::LABELS[$s->rules['mode']],
                'status' => $s->status,
                'flag_reason' => $s->flag_reason,
                'score' => $s->final_score,
                'level' => $s->highest_level,
                'claimed_ms' => $s->claimed_ms,
                'wall_ms' => $s->ended_at ? $s->started_at->diffInMilliseconds($s->ended_at) : null,
                'reviewed_at' => $s->reviewed_at?->toIso8601String(),
                'ended_at' => $s->ended_at?->toIso8601String(),
                'rounds' => $s->rounds->map(fn ($r) => [
                    'number' => $r->round_number,
                    'key' => $r->key,
                    'status' => $r->status,
                    'units' => $r->units,
                    'preview_ms' => $r->preview_ms,
                    'timings' => $r->input_timings,
                    'points' => $r->points,
                ]),
            ]);

        return Inertia::render('Admin/Reviews', ['sessions' => $sessions, 'showReviewed' => $showReviewed]);
    }

    public function approve(Request $request, GameSession $session, StatisticsService $statistics, LeaderboardService $leaderboards): RedirectResponse
    {
        abort_unless($session->is_flagged && $session->status !== GameSession::STATUS_VOIDED, 422);

        $this->review($request, $session, ['is_flagged' => false], $statistics, $leaderboards);

        return back()->with('success', 'Result approved and counted towards the rankings.');
    }

    public function void(Request $request, GameSession $session, StatisticsService $statistics, LeaderboardService $leaderboards): RedirectResponse
    {
        abort_if($session->isActive(), 422, 'Active runs cannot be voided.');

        $this->review($request, $session, ['status' => GameSession::STATUS_VOIDED], $statistics, $leaderboards);

        return back()->with('success', 'Result voided and removed from statistics and leaderboards.');
    }

    private function review(Request $request, GameSession $session, array $changes, StatisticsService $statistics, LeaderboardService $leaderboards): void
    {
        DB::transaction(function () use ($request, $session, $changes, $statistics) {
            $session->forceFill([...$changes, 'reviewed_at' => now(), 'reviewed_by' => $request->user()->id])->save();

            if ($session->user) {
                $statistics->rebuild($session->user);
            }
        });

        $leaderboards->forgetCache();
    }
}
