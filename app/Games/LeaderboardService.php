<?php

namespace App\Games;

use App\Models\GameSeason;
use App\Models\GameSession;
use App\Models\LeaderboardSnapshot;
use App\Platform\Districts;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Leaderboards. Every board has a single deterministic ordering (ROW_NUMBER
 * over fully specified tie-breakers), described in Board::RULES. Only ranked,
 * unflagged, finished sessions from active accounts contribute.
 */
class LeaderboardService
{
    public function __construct(
        private GameSettings $settings,
        private DailyChallengeService $daily,
    ) {}

    /**
     * Rows: user_id, username, avatar, position, plus board-specific values
     * (level, score, points, games, avg_unit_ms, accuracy, achieved_at).
     */
    public function rankedQuery(Board $board): ?Builder
    {
        // Filtering happens before ROW_NUMBER(), so district boards rank players within the district.
        $query = $this->baseQuery($board)
            ?->when($board->district, fn ($q, $district) => $q->where('u.district', $district))
            ->when(! $board->district && $board->region, fn ($q) => $q->whereIn('u.district', Districts::inRegion($board->region)));

        if ($query) {
            $query->addSelect('u.district');
            $query->when($query->groups, fn ($q) => $q->groupBy('u.district'));
        }

        return $query;
    }

    private function baseQuery(Board $board): ?Builder
    {
        $gameId = $board->game?->id;

        if ($board->isPlatform()) {
            return match ($board->category) {
                'all_time' => $this->profileQuery(),
                'weekly' => $this->periodQuery(null, ...$this->weekBounds()),
                'season' => ($season = $this->currentSeason()) ? $this->periodQuery(null, seasonId: $season->id) : null,
                default => null,
            };
        }

        return match ($board->category) {
            'level' => $this->statsQuery($gameId, 'ps.best_level > 0', 'ps.best_level DESC, ps.best_level_at ASC, ps.user_id ASC',
                ['ps.best_level as level', 'ps.best_level_at as achieved_at', 'ps.best_avg_unit_ms as avg_unit_ms', 'ps.correct_inputs', 'ps.expected_inputs']),
            'fastest' => $this->statsQuery($gameId, 'ps.best_avg_unit_ms IS NOT NULL', 'ps.best_avg_unit_ms ASC, ps.best_avg_unit_at ASC, ps.user_id ASC',
                ['ps.best_avg_unit_ms as avg_unit_ms', 'ps.best_avg_unit_at as achieved_at', 'ps.best_level as level']),
            'timed' => $this->timedQuery($gameId, (int) $board->duration),
            'all_time' => $this->statsQuery($gameId, 'ps.total_points > 0', 'ps.total_points DESC, ps.games_played ASC, u.created_at ASC, ps.user_id ASC',
                ['ps.total_points as points', 'ps.best_level as level', 'ps.games_played as games']),
            'once' => $this->onceQuery($board),
            'weekly' => $this->periodQuery($gameId, ...$this->weekBounds()),
            default => null,
        };
    }

    /** @return array{rows: array, meta: array} */
    public function page(Board $board, int $page = 1, ?string $search = null): array
    {
        $resolver = function () use ($board, $page, $search) {
            $query = $this->rankedQuery($board);

            if (! $query) {
                return ['rows' => [], 'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 0, 'per_page' => $this->perPage()]];
            }

            $paginator = DB::query()->fromSub($query, 'ranked')
                ->when($search, fn ($q) => $q->where('username', 'like', '%'.addcslashes($search, '%_\\').'%'))
                ->orderBy('position')
                ->paginate($this->perPage(), ['*'], 'page', $page);

            return [
                'rows' => $this->withMovement($board, collect($paginator->items()))->all(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'total' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                ],
            ];
        };

        return $search ? $resolver() : Cache::remember($this->cacheKey($board, "page:{$page}"), $this->cacheSeconds(), $resolver);
    }

    public function podium(Board $board): array
    {
        return Cache::remember($this->cacheKey($board, 'podium'), $this->cacheSeconds(), function () use ($board) {
            $query = $this->rankedQuery($board);

            return $query ? DB::query()->fromSub($query, 'ranked')->orderBy('position')->limit(3)->get()->map(fn ($r) => (array) $r)->all() : [];
        });
    }

    public function entryFor(Board $board, int $userId): ?array
    {
        $query = $this->rankedQuery($board);
        $row = $query ? DB::query()->fromSub($query, 'ranked')->where('user_id', $userId)->first() : null;

        return $row ? $this->withMovement($board, collect([$row]))->first() : null;
    }

    /** The players ranked within `radius` places of `position` (inclusive), in order. */
    public function around(Board $board, int $position, int $radius = 2): array
    {
        $query = $this->rankedQuery($board);

        if (! $query) {
            return [];
        }

        $rows = DB::query()->fromSub($query, 'ranked')
            ->whereBetween('position', [max(1, $position - $radius), $position + $radius])
            ->orderBy('position')
            ->get();

        return $this->withMovement($board, $rows)->all();
    }

    public function rankFor(Board $board, int $userId): ?int
    {
        $query = $this->rankedQuery($board);
        $position = $query ? DB::query()->fromSub($query, 'ranked')->where('user_id', $userId)->value('position') : null;

        return $position === null ? null : (int) $position;
    }

    public function totalRanked(Board $board): int
    {
        $query = $this->rankedQuery($board);

        return $query ? DB::query()->fromSub($query, 'ranked')->count() : 0;
    }

    /** Store today's top ranks for movement arrows. Idempotent per day. */
    public function snapshot(Board $board, int $limit = 1000): int
    {
        $query = $this->rankedQuery($board);

        if (! $query || ! $board->tracksMovement()) {
            return 0;
        }

        $now = now();
        $records = DB::query()->fromSub($query, 'ranked')->orderBy('position')->limit($limit)->get(['user_id', 'position'])
            ->map(fn ($r) => [
                'board' => $board->key(),
                'user_id' => $r->user_id,
                'rank' => $r->position,
                'snapshot_on' => $this->settings->today(),
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

        foreach (array_chunk($records, 500) as $chunk) {
            LeaderboardSnapshot::upsert($chunk, ['board', 'user_id', 'snapshot_on'], ['rank', 'updated_at']);
        }

        return count($records);
    }

    /** Take today's snapshot once, the first time a board is viewed each day. */
    public function ensureSnapshot(Board $board): void
    {
        if (! $board->tracksMovement()) {
            return;
        }

        $key = $this->cacheKey($board, 'snapshot:'.$this->settings->today());

        if (Cache::has($key)) {
            return;
        }

        Cache::lock($key.':lock', 30)->get(function () use ($board, $key) {
            $exists = LeaderboardSnapshot::where('board', $board->key())->whereDate('snapshot_on', $this->settings->today())->exists();

            if (! $exists) {
                $this->snapshot($board);
            }

            Cache::put($key, true, now()->addDay());
        });
    }

    public function currentSeason(): ?GameSeason
    {
        return GameSeason::covering($this->settings->today());
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} UTC bounds of the current week in the game timezone. */
    public function weekBounds(): array
    {
        $start = $this->settings->now()->startOfWeek(CarbonImmutable::MONDAY);

        return [$start->utc(), $start->addWeek()->utc()];
    }

    public function forgetCache(): void
    {
        Cache::increment('leaderboard:version');
    }

    private function statsQuery(int $gameId, string $where, string $order, array $columns): Builder
    {
        return DB::table('player_statistics as ps')
            ->join('users as u', 'u.id', '=', 'ps.user_id')
            ->where('ps.game_id', $gameId)
            ->whereNull('u.disabled_at')
            ->whereRaw($where)
            ->select(array_merge(['ps.user_id', 'u.username', 'u.avatar'], $columns))
            ->selectRaw("ROW_NUMBER() OVER (ORDER BY {$order}) as position");
    }

    private function timedQuery(int $gameId, int $duration): Builder
    {
        abort_unless(in_array($duration, config('games.platform.timed_durations'), true), 404);
        $col = "ps.best_timed_{$duration}";

        return $this->statsQuery($gameId, "{$col} > 0", "{$col} DESC, {$col}_at ASC, ps.user_id ASC",
            ["{$col} as score", "{$col}_at as achieved_at"]);
    }

    private function profileQuery(): Builder
    {
        return DB::table('player_profiles as pp')
            ->join('users as u', 'u.id', '=', 'pp.user_id')
            ->whereNull('u.disabled_at')
            ->where('pp.total_points', '>', 0)
            ->select(['pp.user_id', 'u.username', 'u.avatar', 'pp.total_points as points', 'pp.games_played as games', 'pp.current_daily_streak as streak'])
            ->selectRaw('ROW_NUMBER() OVER (ORDER BY pp.total_points DESC, pp.games_played ASC, u.created_at ASC, pp.user_id ASC) as position');
    }

    private function onceQuery(Board $board): Builder
    {
        $challenge = $this->daily->today($board->game);

        return $this->rankedSessions($board->game->id)
            ->where('s.daily_challenge_id', $challenge->id)
            ->select(['s.user_id', 'u.username', 'u.avatar', 's.final_score as score', 's.highest_level as level',
                's.total_response_ms', 's.accuracy', 's.ended_at as achieved_at'])
            ->selectRaw('ROW_NUMBER() OVER (ORDER BY s.final_score DESC, s.highest_level DESC, s.total_response_ms ASC, s.ended_at ASC, s.id ASC) as position');
    }

    private function periodQuery(?int $gameId, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null, ?int $seasonId = null): Builder
    {
        return $this->rankedSessions($gameId)
            ->when($from, fn ($q) => $q->where('s.ended_at', '>=', $from)->where('s.ended_at', '<', $to))
            ->when($seasonId, fn ($q) => $q->where('s.game_season_id', $seasonId))
            ->groupBy('s.user_id', 'u.username', 'u.avatar', 'u.created_at')
            ->select(['s.user_id', 'u.username', 'u.avatar'])
            ->selectRaw('SUM(s.final_score) as points, COUNT(*) as games, MAX(s.highest_level) as level')
            ->selectRaw('ROW_NUMBER() OVER (ORDER BY SUM(s.final_score) DESC, COUNT(*) ASC, u.created_at ASC, s.user_id ASC) as position');
    }

    private function rankedSessions(?int $gameId): Builder
    {
        return DB::table('game_sessions as s')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->when($gameId, fn ($q) => $q->where('s.game_id', $gameId))
            ->whereNull('u.disabled_at')
            ->where('s.is_ranked', true)
            ->where('s.is_flagged', false)
            ->whereIn('s.status', GameSession::FINISHED_STATUSES)
            ->where('s.rounds_played', '>', 0);
    }

    private function withMovement(Board $board, Collection $rows): Collection
    {
        $rows = $rows->map(fn ($r) => (array) $r);

        if (! $board->tracksMovement() || $rows->isEmpty()) {
            return $rows->map(fn ($r) => $r + ['movement' => null]);
        }

        // Weekly and season boards restart from zero, so a snapshot from the previous week or
        // season is not a fair comparison: no arrows show until the period has its own snapshot.
        $periodStart = $this->periodStart($board);

        $previousDate = LeaderboardSnapshot::where('board', $board->key())
            ->whereDate('snapshot_on', '<', $this->settings->today())
            ->when($periodStart, fn ($q) => $q->whereDate('snapshot_on', '>=', $periodStart))
            ->max('snapshot_on');

        $previous = $previousDate
            ? LeaderboardSnapshot::where('board', $board->key())
                ->whereDate('snapshot_on', $previousDate)
                ->whereIn('user_id', $rows->pluck('user_id'))
                ->pluck('rank', 'user_id')
            : collect();

        return $rows->map(function ($r) use ($previous, $previousDate) {
            $before = $previous->get($r['user_id']);
            $r['position'] = (int) $r['position'];
            $r['movement'] = $previousDate === null ? null : ($before === null ? 'new' : (int) $before - $r['position']);

            return $r;
        });
    }

    /** First local date of the period a resetting board covers (Y-m-d), or null for boards that never reset. */
    private function periodStart(Board $board): ?string
    {
        return match ($board->category) {
            'weekly' => $this->settings->now()->startOfWeek(CarbonImmutable::MONDAY)->toDateString(),
            'season' => $this->currentSeason()?->starts_on->toDateString(),
            default => null,
        };
    }

    private function perPage(): int
    {
        return (int) config('games.platform.leaderboard.per_page', 20);
    }

    private function cacheSeconds(): int
    {
        return (int) config('games.platform.leaderboard.cache_seconds', 30);
    }

    private function cacheKey(Board $board, string $suffix): string
    {
        return 'leaderboard:v'.Cache::get('leaderboard:version', 0).':'.$board->key().':'.$suffix;
    }
}
