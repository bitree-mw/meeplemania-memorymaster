<?php

namespace App\Games\Definitions;

use App\Games\Evaluation;
use App\Games\GameDefinition;

/**
 * Spatial recall: some cells of a grid light up together for a moment; tap
 * every lit cell from memory, in any order.
 */
class PatternPeek extends GameDefinition
{
    public function slug(): string
    {
        return 'pattern-peek';
    }

    public function name(): string
    {
        return 'Pattern Peek';
    }

    public function tagline(): string
    {
        return 'Spatial memory';
    }

    public function description(): string
    {
        return 'A pattern lights up for a blink. Tap every lit cell from memory.';
    }

    public function icon(): string
    {
        return 'LayoutGrid';
    }

    public function color(): string
    {
        return 'sapphire';
    }

    public function unit(): string
    {
        return 'cell';
    }

    public function howToPlay(): array
    {
        return [
            'Several cells light up at once, then go dark.',
            'Tap every cell that was lit, in any order.',
            'A wrong cell loses the round. Patterns grow, and the grid gets bigger as you level up.',
        ];
    }

    protected function build(string $seed, array $rules, string $mode, int $level, int $attempt, int $n): array
    {
        $g = $rules['game'];
        $cells = min((int) $g['cells_max'], (int) $g['cells_start'] + ($level - 1));
        $size = match (true) {
            $cells <= 4 => 3,
            $cells <= 7 => 4,
            $cells <= 11 => 5,
            default => 6,
        };
        $rng = $this->rng($seed, $this->label($mode, $level, $attempt, $n));
        $lit = $rng->sample($size * $size, $cells);
        sort($lit);

        return [
            'units' => $cells,
            'preview_ms' => $this->decay((int) $g['preview_ms_start'], (int) $g['preview_ms_step'], (int) $g['preview_ms_min'], $level),
            'input_min' => 0,
            'input_max' => $size * $size,
            'max_inputs' => $size * $size,
            'size' => $size,
            'lit' => $lit,
            'mistakes_allowed' => (int) $g['mistakes_allowed'],
        ];
    }

    public function evaluate(array $spec, array $inputs): Evaluation
    {
        $targets = array_flip($spec['lit']);
        $found = [];
        $mistakes = 0;
        $total = count($targets);

        foreach ($inputs as $cell) {
            $cell = (int) $cell;

            if (isset($found[$cell])) {
                continue; // Tapping a found cell again changes nothing.
            }

            if (isset($targets[$cell])) {
                $found[$cell] = true;
                if (count($found) === $total) {
                    return Evaluation::cleared($total, $total, $mistakes);
                }
            } elseif (++$mistakes > $spec['mistakes_allowed']) {
                return Evaluation::failed(count($found), $total, $mistakes);
            }
        }

        return Evaluation::incomplete(count($found), $total, $mistakes);
    }

    public function settingsFields(): array
    {
        return [
            ['key' => 'cells_start', 'label' => 'Lit cells at level 1', 'type' => 'number', 'min' => 1, 'max' => 20],
            ['key' => 'cells_max', 'label' => 'Most lit cells', 'type' => 'number', 'min' => 1, 'max' => 30, 'help' => 'The grid grows from 3×3 up to 6×6 as patterns get larger.'],
            ['key' => 'preview_ms_start', 'label' => 'Peek time at level 1', 'type' => 'number', 'min' => 200, 'max' => 5000, 'unit' => 'ms'],
            ['key' => 'preview_ms_step', 'label' => 'Peek reduction per level', 'type' => 'number', 'min' => 0, 'max' => 500, 'unit' => 'ms'],
            ['key' => 'preview_ms_min', 'label' => 'Shortest peek', 'type' => 'number', 'min' => 200, 'max' => 5000, 'unit' => 'ms'],
            ['key' => 'mistakes_allowed', 'label' => 'Wrong cells allowed per round', 'type' => 'number', 'min' => 0, 'max' => 10],
        ];
    }
}
