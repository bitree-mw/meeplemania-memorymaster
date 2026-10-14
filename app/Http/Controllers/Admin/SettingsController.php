<?php

namespace App\Http\Controllers\Admin;

use App\Games\GameRegistry;
use App\Games\GameSettings;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Game configuration. Each (scope, section) is validated and stored as a
 * whole; running sessions keep the rules they started with.
 */
class SettingsController extends Controller
{
    public function __construct(private GameRegistry $registry, private GameSettings $settings) {}

    public function edit(Request $request): Response
    {
        $scope = $request->query('scope', GameSettings::PLATFORM);
        abort_unless($scope === GameSettings::PLATFORM || $this->registry->has($scope), 404);

        $sections = $this->schema($scope);
        $values = $scope === GameSettings::PLATFORM ? $this->settings->platform() : $this->settings->game($scope);
        $defaults = $scope === GameSettings::PLATFORM ? config('games.rules') : config("games.games.{$scope}.rules");

        return Inertia::render('Admin/Settings', [
            'scope' => $scope,
            'scopes' => [['key' => GameSettings::PLATFORM, 'name' => 'Platform'], ...array_map(fn ($g) => ['key' => $g['slug'], 'name' => $g['name']], $this->registry->catalogue())],
            'sections' => $sections,
            'values' => collect($sections)->mapWithKeys(fn ($s) => [$s['key'] => $values[$s['key']]]),
            'defaults' => collect($sections)->mapWithKeys(fn ($s) => [$s['key'] => $defaults[$s['key']]]),
            'timezones' => \DateTimeZone::listIdentifiers(),
        ]);
    }

    public function update(Request $request, string $scope, string $section): RedirectResponse
    {
        $schema = collect($this->schema($scope))->firstWhere('key', $section);
        abort_unless($schema, 404);

        $values = $request->validate($this->rules($schema['fields']));
        $values = array_map(fn ($v) => is_numeric($v) ? (int) $v : $v, $values);

        $this->settings->update($scope, $section, $values, $request->user());

        return back()->with('success', "{$schema['title']} saved. New games use it immediately.");
    }

    public function reset(string $scope, string $section): RedirectResponse
    {
        $schema = collect($this->schema($scope))->firstWhere('key', $section);
        abort_unless($schema, 404);

        $this->settings->reset($scope, $section);

        return back()->with('success', "{$schema['title']} restored to defaults.");
    }

    private function rules(array $fields): array
    {
        return collect($fields)->mapWithKeys(fn ($f) => [$f['key'] => match ($f['type']) {
            'timezone' => ['required', 'timezone:all'],
            default => ['required', 'integer', 'min:'.($f['min'] ?? 0), 'max:'.($f['max'] ?? 1000000)],
        }])->all();
    }

    /** Field metadata for the form; also the source of validation rules. */
    private function schema(string $scope): array
    {
        $n = fn (string $key, string $label, int $min, int $max, ?string $unit = null, string $help = '') => compact('key', 'label', 'min', 'max', 'unit', 'help') + ['type' => 'number'];

        if ($scope === GameSettings::PLATFORM) {
            return [
                ['key' => 'daily', 'title' => 'Day rollover', 'description' => 'Try Once challenges and daily streaks reset at midnight in this timezone.', 'fields' => [
                    ['key' => 'timezone', 'label' => 'Timezone', 'type' => 'timezone'],
                ]],
                ['key' => 'fair_play', 'title' => 'Fair play', 'description' => 'Thresholds for flagging implausible results for review.', 'fields' => [
                    $n('min_tap_interval_ms', 'Minimum time between taps', 0, 500, 'ms'),
                    $n('min_first_tap_ms', 'Minimum time to first tap', 0, 1000, 'ms'),
                    $n('latency_allowance_ms', 'Clock comparison allowance', 500, 20000, 'ms'),
                    $n('timed_clock_grace_ms', 'Timed Rush clock grace', 0, 10000, 'ms'),
                    $n('timed_sync_grace_seconds', 'Timed Rush late-sync window', 5, 600, 's', 'Results arriving later than this after the clock ends are sent to review.'),
                    $n('session_idle_minutes', 'Expire idle runs after', 5, 1440, 'min'),
                ]],
                ['key' => 'streaks', 'title' => 'Streak rules', 'description' => 'A day counts toward the daily streak when a ranked run in any game meets this bar.', 'fields' => [
                    $n('min_rounds_for_qualifying_session', 'Rounds needed for a qualifying run', 1, 50),
                ]],
                ['key' => 'presence', 'title' => 'Online presence', 'description' => 'Heartbeat cadence and how long a visitor counts as online.', 'fields' => [
                    $n('heartbeat_seconds', 'Heartbeat interval', 30, 60, 's'),
                    $n('online_threshold_seconds', 'Online threshold', 60, 600, 's'),
                ]],
                ['key' => 'guests', 'title' => 'Guest play', 'description' => 'Visitors without an account can play a limited number of games on each device before they must sign up. A game counts once a round has been played.', 'fields' => [
                    $n('nudge_after_plays', 'Ask guests to sign up after', 1, 100, 'games'),
                    $n('max_plays', 'Require an account after', 1, 200, 'games'),
                ]],
            ];
        }

        abort_unless($this->registry->has($scope), 404);
        $definition = $this->registry->definition($scope);

        return [
            ['key' => 'classic', 'title' => 'Level Builder', 'description' => 'Progressive levels. A failed round costs a life and replays the level with a new puzzle.', 'fields' => [
                $n('lives', 'Lives per run', 1, 9),
                $n('starting_level', 'Starting level', 1, 50),
                $n('input_timeout_ms', 'Idle timeout between taps (also Try Once)', 0, 60000, 'ms', '0 disables the timeout.'),
            ]],
            ['key' => 'timed', 'title' => 'Timed Rush', 'description' => 'Rounds get harder every few rounds, regardless of mistakes.', 'fields' => [
                $n('start_level', 'Starting difficulty level', 1, 50),
                $n('level_step_every', 'Rounds per difficulty step', 1, 50),
                $n('max_level', 'Highest difficulty level', 1, 100),
            ]],
            ['key' => 'once', 'title' => 'Try Once defaults', 'description' => 'Applied when a day’s challenge is first created; adjust single days under Try Once.', 'fields' => [
                $n('lives', 'Lives', 1, 9),
                $n('starting_level', 'Starting level', 1, 50),
                $n('max_levels', 'Levels in the challenge', 1, 200),
            ]],
            ['key' => 'scoring', 'title' => 'Scoring', 'description' => "Level modes: base + per-level bonus × level + speed bonus. Timed Rush: base + per-{$definition->unit()} points + speed bonus, minus a penalty per miss.", 'fields' => [
                $n('base_points', 'Base points per cleared level', 0, 10000),
                $n('points_per_level', 'Bonus per level', 0, 10000),
                $n('speed_target_ms_per_unit', "Speed target per {$definition->unit()}", 50, 10000, 'ms', 'Answers slower than this earn no speed bonus.'),
                $n('speed_bonus_per_unit', "Max speed bonus per {$definition->unit()}", 0, 1000),
                $n('timed_base_points', 'Timed Rush points per cleared round', 0, 10000),
                $n('timed_points_per_unit', "Timed Rush points per {$definition->unit()}", 0, 1000),
                $n('timed_mistake_penalty', 'Timed Rush penalty per miss', 0, 10000),
            ]],
            ['key' => 'game', 'title' => $definition->name().' difficulty', 'description' => $definition->description(), 'fields' => $definition->settingsFields()],
        ];
    }
}
