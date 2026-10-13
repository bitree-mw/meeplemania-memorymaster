<?php

namespace App\Http\Controllers;

use App\Games\Board;
use App\Games\GameRegistry;
use App\Games\GameSettings;
use App\Models\Achievement;
use App\Platform\AchievementService;
use Inertia\Inertia;
use Inertia\Response;

class RulesController extends Controller
{
    /** The rulebook always reflects the live, admin-configured values. */
    public function __invoke(GameSettings $settings, GameRegistry $registry): Response
    {
        $games = collect($registry->catalogue())->map(function (array $game) use ($registry, $settings) {
            $rules = $settings->game($game['slug']);

            return $game + [
                'how_to' => $registry->definition($game['slug'])->howToPlay(),
                'rules' => [
                    'lives' => (int) $rules['classic']['lives'],
                    'starting_level' => (int) $rules['classic']['starting_level'],
                    'input_timeout_ms' => (int) $rules['classic']['input_timeout_ms'],
                    'once_lives' => (int) $rules['once']['lives'],
                    'once_starting_level' => (int) $rules['once']['starting_level'],
                    'once_max_levels' => (int) $rules['once']['max_levels'],
                    'scoring' => $rules['scoring'],
                ],
            ];
        });

        return Inertia::render('Rules', [
            'games' => $games,
            'durations' => config('games.platform.timed_durations'),
            'countdown' => (int) config('games.platform.countdown_seconds'),
            'timezone' => $settings->timezone(),
            'fastestMinLevel' => (int) config('games.platform.fastest_min_level'),
            'achievements' => Achievement::where('is_active', true)->orderBy('sort_order')->get()->map(fn ($a) => AchievementService::present($a)),
            'boards' => collect(Board::LABELS)->map(fn ($label, $key) => ['label' => $label, 'rule' => Board::RULES[$key]])->values(),
        ]);
    }
}
