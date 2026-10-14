<?php

use App\Games\Definitions\DiceDigits;
use App\Games\Definitions\MeeplePairs;
use App\Games\Definitions\MemoryMaster;
use App\Games\Definitions\PatternPeek;
use App\Games\Definitions\SpotTheSwap;

/*
|--------------------------------------------------------------------------
| Meeple Mania games — default rules
|--------------------------------------------------------------------------
|
| Every game offers three modes:
|   classic  "Level Builder"  progressive levels, a few lives, endless
|   timed    "Timed Rush"     solve as many rounds as possible in 30/45/60 s
|   once     "Try Once"       one ranked attempt per day, same puzzle for all
|
| Everything under "rules" (platform) and each game's "rules" can be
| overridden from Admin → Settings; overrides live in `game_settings` and are
| merged per section by App\Games\GameSettings. Sessions snapshot the rules
| they start with.
|
*/

return [

    // Fixed platform options (not admin-editable).
    'platform' => [
        'timed_durations' => [30, 45, 60],
        'countdown_seconds' => 3,
        // Rounds revealed ahead of the player so gameplay never waits on the network.
        'lookahead_levels' => 3,
        'lookahead_timed_rounds' => 15,
        // Fastest boards only count Level Builder runs reaching this level.
        'fastest_min_level' => 5,
        'leaderboard' => ['per_page' => 20, 'cache_seconds' => 30],
    ],

    // Admin-editable platform sections.
    'rules' => [
        'daily' => [
            // Try Once and daily streaks roll over at midnight in this timezone.
            'timezone' => 'UTC',
        ],
        'fair_play' => [
            'min_tap_interval_ms' => 70,
            'min_first_tap_ms' => 40,
            // Slack when comparing the client's claimed play time with the server clock.
            'latency_allowance_ms' => 3000,
            // Timed Rush: claimed play time may exceed the clock by at most this much.
            'timed_clock_grace_ms' => 2000,
            // Timed Rush: results arriving later than this after the clock ends go to review.
            'timed_sync_grace_seconds' => 30,
            'session_idle_minutes' => 60,
        ],
        'streaks' => [
            'min_rounds_for_qualifying_session' => 1,
        ],
        'presence' => [
            'heartbeat_seconds' => 45,
            'online_threshold_seconds' => 120,
        ],
        'guests' => [
            'nudge_after_plays' => 10,
            'max_plays' => 20,
        ],
    ],

    // Display order = array order.
    'games' => [

        'memory-master' => [
            'class' => MemoryMaster::class,
            'rules' => [
                'classic' => ['lives' => 1, 'starting_level' => 1, 'input_timeout_ms' => 6000],
                'timed' => ['start_level' => 1, 'level_step_every' => 3, 'max_level' => 6],
                'once' => ['lives' => 1, 'starting_level' => 3, 'max_levels' => 20],
                'scoring' => [
                    'base_points' => 100, 'points_per_level' => 50,
                    'speed_target_ms_per_unit' => 800, 'speed_bonus_per_unit' => 20,
                    'timed_base_points' => 50, 'timed_points_per_unit' => 10, 'timed_mistake_penalty' => 25,
                ],
                'game' => [
                    // Sequence length = length_start + (level - 1) * length_step. Sequences extend level to level.
                    'length_start' => 1,
                    'length_step' => 1,
                    // Board grows independently of sequence length: 4 tiles, then 6, then 9 (0 = never).
                    'six_tiles_from_level' => 13,
                    'nine_tiles_from_level' => 0,
                    'flash_ms_start' => 700, 'flash_ms_step' => 30, 'flash_ms_min' => 320,
                    'gap_ms_start' => 300, 'gap_ms_step' => 12, 'gap_ms_min' => 130,
                    'timed_length_start' => 3, 'timed_flash_ms' => 380, 'timed_gap_ms' => 120,
                ],
            ],
        ],

        'meeple-pairs' => [
            'class' => MeeplePairs::class,
            'rules' => [
                'classic' => ['lives' => 3, 'starting_level' => 1, 'input_timeout_ms' => 10000],
                'timed' => ['start_level' => 1, 'level_step_every' => 2, 'max_level' => 6],
                'once' => ['lives' => 3, 'starting_level' => 2, 'max_levels' => 12],
                'scoring' => [
                    'base_points' => 100, 'points_per_level' => 60,
                    'speed_target_ms_per_unit' => 1800, 'speed_bonus_per_unit' => 25,
                    'timed_base_points' => 60, 'timed_points_per_unit' => 12, 'timed_mistake_penalty' => 20,
                ],
                'game' => [
                    // Board preview: ms per pair at level 1, shrinking per level, never below the minimum.
                    'preview_ms_per_pair' => 800,
                    'preview_decay_ms_per_level' => 40,
                    'preview_min_ms_per_pair' => 300,
                    // Mismatches tolerated before the board is lost.
                    'mismatches_allowed' => 2,
                ],
            ],
        ],

        'pattern-peek' => [
            'class' => PatternPeek::class,
            'rules' => [
                'classic' => ['lives' => 3, 'starting_level' => 1, 'input_timeout_ms' => 8000],
                'timed' => ['start_level' => 1, 'level_step_every' => 2, 'max_level' => 8],
                'once' => ['lives' => 3, 'starting_level' => 2, 'max_levels' => 15],
                'scoring' => [
                    'base_points' => 100, 'points_per_level' => 50,
                    'speed_target_ms_per_unit' => 700, 'speed_bonus_per_unit' => 15,
                    'timed_base_points' => 40, 'timed_points_per_unit' => 10, 'timed_mistake_penalty' => 20,
                ],
                'game' => [
                    'cells_start' => 3,
                    'cells_max' => 18,
                    'preview_ms_start' => 1400, 'preview_ms_step' => 40, 'preview_ms_min' => 650,
                    'mistakes_allowed' => 0,
                ],
            ],
        ],

        'dice-digits' => [
            'class' => DiceDigits::class,
            'rules' => [
                'classic' => ['lives' => 3, 'starting_level' => 1, 'input_timeout_ms' => 10000],
                'timed' => ['start_level' => 1, 'level_step_every' => 2, 'max_level' => 7],
                'once' => ['lives' => 3, 'starting_level' => 2, 'max_levels' => 12],
                'scoring' => [
                    'base_points' => 100, 'points_per_level' => 50,
                    'speed_target_ms_per_unit' => 700, 'speed_bonus_per_unit' => 15,
                    'timed_base_points' => 40, 'timed_points_per_unit' => 10, 'timed_mistake_penalty' => 20,
                ],
                'game' => [
                    'length_start' => 3,
                    'length_max' => 14,
                    'preview_ms_per_die_start' => 650, 'preview_ms_per_die_step' => 20, 'preview_ms_per_die_min' => 280,
                ],
            ],
        ],

        'spot-the-swap' => [
            'class' => SpotTheSwap::class,
            'rules' => [
                'classic' => ['lives' => 3, 'starting_level' => 1, 'input_timeout_ms' => 10000],
                'timed' => ['start_level' => 1, 'level_step_every' => 2, 'max_level' => 8],
                'once' => ['lives' => 3, 'starting_level' => 2, 'max_levels' => 15],
                'scoring' => [
                    'base_points' => 100, 'points_per_level' => 40,
                    'speed_target_ms_per_unit' => 350, 'speed_bonus_per_unit' => 10,
                    'timed_base_points' => 40, 'timed_points_per_unit' => 8, 'timed_mistake_penalty' => 20,
                ],
                'game' => [
                    'tokens_start' => 3,
                    'tokens_max' => 16,
                    'preview_ms_start' => 2200, 'preview_ms_step' => 70, 'preview_ms_min' => 900,
                    'blank_ms' => 450,
                    // Before this level only colours change; from it, icons may change too.
                    'icon_changes_from_level' => 4,
                ],
            ],
        ],
    ],
];
