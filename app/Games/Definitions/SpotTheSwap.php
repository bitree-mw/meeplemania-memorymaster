<?php

namespace App\Games\Definitions;

use App\Games\Evaluation;
use App\Games\GameDefinition;

/**
 * Change detection: memorize a board of tokens; after a blink one token changes
 * colour or shape. Tap the one that changed.
 */
class SpotTheSwap extends GameDefinition
{
    /** Token shapes and colours available (see palette.js SWAP_ICONS / SWAP_COLORS). */
    public const ICONS = 8;

    public const COLORS = 6;

    public function slug(): string
    {
        return 'spot-the-swap';
    }

    public function name(): string
    {
        return 'Spot the Swap';
    }

    public function tagline(): string
    {
        return 'Change detection';
    }

    public function description(): string
    {
        return 'Memorize the tokens. After a blink, one has changed — find it.';
    }

    public function icon(): string
    {
        return 'Eye';
    }

    public function color(): string
    {
        return 'amethyst';
    }

    public function unit(): string
    {
        return 'token';
    }

    public function howToPlay(): array
    {
        return [
            'Study the board of meeple tokens.',
            'The board blinks, and exactly one token changes colour or shape.',
            'Tap the token that changed. More tokens and shorter looks as you level up.',
        ];
    }

    protected function build(string $seed, array $rules, string $mode, int $level, int $attempt, int $n): array
    {
        $g = $rules['game'];
        $count = min((int) $g['tokens_max'], (int) $g['tokens_start'] + ($level - 1));
        $rng = $this->rng($seed, $this->label($mode, $level, $attempt, $n));

        // Distinct (icon, colour) combinations so every token is unambiguous.
        $combos = $rng->sample(self::ICONS * self::COLORS, $count);
        $tokens = array_map(fn ($c) => [intdiv($c, self::COLORS), $c % self::COLORS], $combos);

        $changed = $rng->int($count);
        [$icon, $color] = $tokens[$changed];
        $change = $level >= (int) $g['icon_changes_from_level'] && $rng->int(2) === 1 ? 'icon' : 'color';

        // Pick a replacement that differs and doesn't duplicate another token.
        $taken = array_flip($combos);
        do {
            $after = $change === 'icon'
                ? [($icon + 1 + $rng->int(self::ICONS - 1)) % self::ICONS, $color]
                : [$icon, ($color + 1 + $rng->int(self::COLORS - 1)) % self::COLORS];
        } while (isset($taken[$after[0] * self::COLORS + $after[1]]));

        $show = $this->decay((int) $g['preview_ms_start'], (int) $g['preview_ms_step'], (int) $g['preview_ms_min'], $level);
        $blank = (int) $g['blank_ms'];

        return [
            'units' => $count,
            'preview_ms' => $show + $blank,
            'input_min' => 0,
            'input_max' => $count,
            'max_inputs' => 1,
            'cols' => $count <= 4 ? 2 : ($count <= 9 ? 3 : 4),
            'tokens' => $tokens,
            'changed' => $changed,
            'after' => $after,
            'change' => $change,
            'show_ms' => $show,
            'blank_ms' => $blank,
        ];
    }

    public function evaluate(array $spec, array $inputs): Evaluation
    {
        if ($inputs === []) {
            return Evaluation::incomplete(0, 1);
        }

        return (int) $inputs[0] === (int) $spec['changed']
            ? Evaluation::cleared(1, 1)
            : Evaluation::failed(0, 1);
    }

    public function settingsFields(): array
    {
        return [
            ['key' => 'tokens_start', 'label' => 'Tokens at level 1', 'type' => 'number', 'min' => 2, 'max' => 16],
            ['key' => 'tokens_max', 'label' => 'Most tokens', 'type' => 'number', 'min' => 2, 'max' => 16],
            ['key' => 'preview_ms_start', 'label' => 'Look time at level 1', 'type' => 'number', 'min' => 300, 'max' => 6000, 'unit' => 'ms'],
            ['key' => 'preview_ms_step', 'label' => 'Look reduction per level', 'type' => 'number', 'min' => 0, 'max' => 500, 'unit' => 'ms'],
            ['key' => 'preview_ms_min', 'label' => 'Shortest look', 'type' => 'number', 'min' => 300, 'max' => 6000, 'unit' => 'ms'],
            ['key' => 'blank_ms', 'label' => 'Blink between boards', 'type' => 'number', 'min' => 100, 'max' => 2000, 'unit' => 'ms'],
            ['key' => 'icon_changes_from_level', 'label' => 'Shape changes from level', 'type' => 'number', 'min' => 1, 'max' => 100, 'help' => 'Before this level only colours change.'],
        ];
    }
}
