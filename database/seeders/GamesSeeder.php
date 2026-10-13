<?php

namespace Database\Seeders;

use App\Models\Achievement;
use App\Models\Game;
use App\Models\GameMode;
use App\Models\GameSeason;
use Illuminate\Database\Seeder;

/**
 * Catalogue data only: games, their modes, achievements and a first season.
 * No players, scores or rankings are seeded.
 */
class GamesSeeder extends Seeder
{
    public function run(): void
    {
        $order = 0;

        foreach (config('games.games') as $slug => $entry) {
            $definition = app($entry['class']);

            $game = Game::updateOrCreate(['slug' => $slug], ['name' => $definition->name(), 'is_active' => true, 'sort_order' => $order++]);

            foreach (GameMode::ALL as $mode) {
                GameMode::updateOrCreate(
                    ['game_id' => $game->id, 'slug' => $mode],
                    ['is_ranked' => true, 'requires_account' => $mode === GameMode::ONCE, 'is_active' => true],
                );
            }
        }

        $achievements = [
            ['first_victory', 'First Victory', 'Clear your first round in any game.', 'Trophy', 'rounds_cleared_total', 1],
            ['five_levels', 'Five Levels Cleared', 'Reach level 5 in any Level Builder.', 'Layers', 'best_level', 5],
            ['memory_champion', 'Memory Champion', 'Clear level 15 in any Level Builder.', 'Crown', 'best_level', 15],
            ['speed_runner', 'Speed Runner', 'Score 1,000 points in one Timed Rush.', 'Zap', 'timed_score', 1000],
            ['perfect_recall', 'Perfect Recall', 'Clear 10 rounds in a row in one run.', 'Sparkles', 'session_streak', 10],
            ['game_explorer', 'Game Explorer', 'Finish a ranked run in all five games.', 'Compass', 'games_tried', 5],
            ['seven_day_streak', 'Seven-Day Streak', 'Play on 7 consecutive days.', 'Flame', 'daily_streak', 7],
            ['daily_champion', 'Try Once Champion', 'Finish first in any Try Once challenge.', 'Medal', 'daily_rank', 1],
            ['daily_podium', 'Try Once Podium', 'Finish in the top 3 of any Try Once challenge.', 'Award', 'daily_rank', 3],
            ['top_100', 'Top 100 Player', 'Reach the all-time top 100.', 'TrendingUp', 'all_time_rank', 100],
            ['dedicated', 'Dedicated Meeple', 'Play 25 ranked games.', 'Gamepad2', 'games_played', 25],
        ];

        foreach ($achievements as $i => [$key, $name, $description, $icon, $criteria, $threshold]) {
            Achievement::updateOrCreate(
                ['key' => $key],
                ['name' => $name, 'description' => $description, 'icon' => $icon, 'criteria' => $criteria, 'threshold' => $threshold, 'is_active' => true, 'sort_order' => $i],
            );
        }

        if (! GameSeason::exists()) {
            $start = now()->startOfMonth();
            GameSeason::create([
                'name' => 'Season 1',
                'starts_on' => $start->toDateString(),
                'ends_on' => $start->copy()->addMonths(3)->subDay()->toDateString(),
            ]);
        }
    }
}
