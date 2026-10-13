<?php

namespace App\Http\Controllers\Admin;

use App\Games\DailyChallengeService;
use App\Games\GameRegistry;
use App\Games\GameSettings;
use App\Http\Controllers\Controller;
use App\Models\DailyChallenge;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ChallengeController extends Controller
{
    public function index(Request $request, GameSettings $settings, GameRegistry $registry, DailyChallengeService $daily): Response
    {
        $slug = $request->query('game', $registry->slugs()[0]);
        $game = $registry->game($slug);
        abort_unless($game, 404);

        $today = $settings->now()->startOfDay();
        $defaults = $settings->game($slug)['once'];

        $existing = DailyChallenge::query()
            ->where('game_id', $game->id)
            ->whereDate('challenge_date', '>=', $today->subDays(14)->toDateString())
            ->whereDate('challenge_date', '<=', $today->addDays(14)->toDateString())
            ->withCount('attempts')
            ->get()
            ->keyBy(fn ($c) => $c->challenge_date->toDateString());

        $days = collect(range(-14, 14))->map(function ($offset) use ($today, $existing, $defaults, $daily) {
            $date = $today->addDays($offset)->toDateString();
            $challenge = $existing->get($date);
            $leader = $challenge ? $daily->standingsQuery($challenge)->select('users.username', 'game_sessions.final_score')->first() : null;

            return [
                'date' => $date,
                'is_today' => $offset === 0,
                'is_past' => $offset < 0,
                'exists' => (bool) $challenge,
                'title' => $challenge?->title,
                'starting_level' => $challenge->starting_level ?? (int) $defaults['starting_level'],
                'max_levels' => $challenge->max_levels ?? (int) $defaults['max_levels'],
                'attempts' => $challenge->attempts_count ?? 0,
                'editable' => $offset >= 0 && ($challenge->attempts_count ?? 0) === 0,
                'rewards_granted' => (bool) $challenge?->rewards_granted_at,
                'leader' => $leader ? ['username' => $leader->username, 'score' => $leader->final_score] : null,
            ];
        })->reverse()->values();

        return Inertia::render('Admin/Challenges', [
            'games' => array_map(fn ($g) => ['slug' => $g['slug'], 'name' => $g['name']], $registry->catalogue()),
            'game' => $slug,
            'days' => $days,
            'timezone' => $settings->timezone(),
        ]);
    }

    public function upsert(Request $request, GameSettings $settings, GameRegistry $registry): RedirectResponse
    {
        $data = $request->validate([
            'game' => ['required', Rule::in($registry->slugs())],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$settings->today()],
            'title' => ['nullable', 'string', 'max:80'],
            'starting_level' => ['required', 'integer', 'min:1', 'max:50'],
            'max_levels' => ['required', 'integer', 'min:1', 'max:200'],
        ]);

        $game = $registry->game($data['game']);
        $challenge = DailyChallenge::where('game_id', $game->id)->whereDate('challenge_date', $data['date'])->withCount('attempts')->first();

        if ($challenge && $challenge->attempts_count > 0) {
            return back()->with('error', 'This Try Once challenge already has ranked attempts, so its rules are locked.');
        }

        $attributes = ['title' => $data['title'], 'starting_level' => $data['starting_level'], 'max_levels' => $data['max_levels']];

        if ($challenge) {
            $challenge->update($attributes);
        } else {
            DailyChallenge::create($attributes + [
                'game_id' => $game->id,
                'challenge_date' => $data['date'],
                'seed' => DailyChallengeService::seed($game->slug, $data['date']),
            ]);
        }

        $label = CarbonImmutable::parse($data['date'])->format('D j M');

        return back()->with('success', "Try Once for {$label} saved.");
    }
}
