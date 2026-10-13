<?php

namespace App\Games\Definitions;

use App\Games\Evaluation;
use App\Games\GameDefinition;
use App\Models\GameMode;

/**
 * Sequence recall: tiles flash one after another; repeat them in order.
 *
 * Level modes use the "extend" rule: level N replays level N-1's sequence and
 * appends tiles. Each tile index is drawn once from a stream that depends only
 * on the seed and the attempt chain, so earlier tiles never change.
 * Timed Rush uses a fresh sequence every round.
 */
class MemoryMaster extends GameDefinition
{
    public function slug(): string
    {
        return 'memory-master';
    }

    public function name(): string
    {
        return 'Memory Master';
    }

    public function tagline(): string
    {
        return 'Sequence recall';
    }

    public function description(): string
    {
        return 'Watch the tiles flash, then repeat the sequence in exact order.';
    }

    public function icon(): string
    {
        return 'Crown';
    }

    public function color(): string
    {
        return 'amber';
    }

    public function unit(): string
    {
        return 'tile';
    }

    public function howToPlay(): array
    {
        return [
            'Tiles flash one after another, each with its own tone. Tapping is locked while they play.',
            'Tap the same tiles in the same order. One wrong tile ends the round.',
            'Each level replays the previous pattern and adds a tile, and the flashes speed up.',
        ];
    }

    protected function build(string $seed, array $rules, string $mode, int $level, int $attempt, int $n): array
    {
        $g = $rules['game'];

        if ($mode === GameMode::TIMED) {
            $length = (int) $g['timed_length_start'] + ($level - 1);
            $tiles = 4;
            $rng = $this->rng($seed, $this->label($mode, $level, $attempt, $n));
            $sequence = array_map(fn () => $rng->int($tiles), range(1, $length));
            $flash = (int) $g['timed_flash_ms'];
            $gap = (int) $g['timed_gap_ms'];
        } else {
            $length = $this->lengthFor($g, $level);
            $tiles = $this->tilesFor($g, $level);
            // One stream per attempt chain, so the sequence extends level to level.
            $rng = $this->rng($seed, "{$mode}:seq:A{$attempt}");
            $sequence = [];
            for ($i = 0; $i < $length; $i++) {
                $sequence[] = $rng->next() % $this->tilesFor($g, $this->levelIntroducing($g, $i));
            }
            $flash = $this->decay((int) $g['flash_ms_start'], (int) $g['flash_ms_step'], (int) $g['flash_ms_min'], $level);
            $gap = $this->decay((int) $g['gap_ms_start'], (int) $g['gap_ms_step'], (int) $g['gap_ms_min'], $level);
        }

        return [
            'units' => $length,
            'preview_ms' => $length * $flash + ($length - 1) * $gap,
            'input_min' => 0,
            'input_max' => $tiles,
            'max_inputs' => $length,
            'tiles' => $tiles,
            'sequence' => $sequence,
            'flash_ms' => $flash,
            'gap_ms' => $gap,
        ];
    }

    public function evaluate(array $spec, array $inputs): Evaluation
    {
        return $this->evaluateOrdered($spec['sequence'], $inputs);
    }

    public function settingsFields(): array
    {
        return [
            ['key' => 'length_start', 'label' => 'Sequence length at level 1', 'type' => 'number', 'min' => 1, 'max' => 20],
            ['key' => 'length_step', 'label' => 'Tiles added per level', 'type' => 'number', 'min' => 1, 'max' => 5],
            ['key' => 'six_tiles_from_level', 'label' => 'Use 6 tiles from level', 'type' => 'number', 'min' => 0, 'max' => 500, 'help' => '0 keeps 4 tiles. Independent of sequence length.'],
            ['key' => 'nine_tiles_from_level', 'label' => 'Use 9 tiles from level', 'type' => 'number', 'min' => 0, 'max' => 500, 'help' => '0 never uses 9 tiles.'],
            ['key' => 'flash_ms_start', 'label' => 'Flash at level 1', 'type' => 'number', 'min' => 150, 'max' => 2000, 'unit' => 'ms'],
            ['key' => 'flash_ms_step', 'label' => 'Flash speed-up per level', 'type' => 'number', 'min' => 0, 'max' => 200, 'unit' => 'ms'],
            ['key' => 'flash_ms_min', 'label' => 'Fastest flash', 'type' => 'number', 'min' => 120, 'max' => 2000, 'unit' => 'ms'],
            ['key' => 'gap_ms_start', 'label' => 'Gap at level 1', 'type' => 'number', 'min' => 50, 'max' => 1500, 'unit' => 'ms'],
            ['key' => 'gap_ms_step', 'label' => 'Gap speed-up per level', 'type' => 'number', 'min' => 0, 'max' => 200, 'unit' => 'ms'],
            ['key' => 'gap_ms_min', 'label' => 'Shortest gap', 'type' => 'number', 'min' => 50, 'max' => 1500, 'unit' => 'ms'],
            ['key' => 'timed_length_start', 'label' => 'Timed Rush starting length', 'type' => 'number', 'min' => 1, 'max' => 12],
            ['key' => 'timed_flash_ms', 'label' => 'Timed Rush flash', 'type' => 'number', 'min' => 150, 'max' => 1500, 'unit' => 'ms'],
            ['key' => 'timed_gap_ms', 'label' => 'Timed Rush gap', 'type' => 'number', 'min' => 50, 'max' => 1000, 'unit' => 'ms'],
        ];
    }

    private function lengthFor(array $g, int $level): int
    {
        return max(1, (int) $g['length_start'] + ($level - 1) * (int) $g['length_step']);
    }

    private function tilesFor(array $g, int $level): int
    {
        $nine = (int) $g['nine_tiles_from_level'];
        $six = (int) $g['six_tiles_from_level'];

        return match (true) {
            $nine > 0 && $level >= $nine => 9,
            $six > 0 && $level >= $six => 6,
            default => 4,
        };
    }

    /** The first level whose sequence includes index $i. */
    private function levelIntroducing(array $g, int $i): int
    {
        $level = 1;
        while ($this->lengthFor($g, $level) <= $i) {
            $level++;
        }

        return $level;
    }
}
