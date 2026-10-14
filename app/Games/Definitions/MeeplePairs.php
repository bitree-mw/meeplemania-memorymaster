<?php

namespace App\Games\Definitions;

use App\Games\Evaluation;
use App\Games\GameDefinition;

/**
 * Concentration with a twist: the whole board is shown face-up for a moment,
 * then flipped. Match every pair from memory; too many mismatches loses the board.
 */
class MeeplePairs extends GameDefinition
{
    /** Pairs per level; the last entry repeats. */
    public const PAIRS_BY_LEVEL = [2, 3, 4, 6, 6, 8, 8, 10, 10, 12, 12, 15];

    /** Board shape (cols, rows) per number of pairs. */
    public const LAYOUTS = [2 => [2, 2], 3 => [3, 2], 4 => [4, 2], 6 => [4, 3], 8 => [4, 4], 10 => [5, 4], 12 => [6, 4], 15 => [6, 5]];

    /** Number of distinct card faces available (see palette.js PAIR_SYMBOLS). */
    public const SYMBOLS = 18;

    public function slug(): string
    {
        return 'meeple-pairs';
    }

    public function name(): string
    {
        return 'Meeple Pairs';
    }

    public function tagline(): string
    {
        return 'Location memory';
    }

    public function description(): string
    {
        return 'Memorise the board before it flips, then match every pair.';
    }

    public function icon(): string
    {
        return 'Layers';
    }

    public function color(): string
    {
        return 'jade';
    }

    public function unit(): string
    {
        return 'pair';
    }

    public function howToPlay(): array
    {
        return [
            'All the cards are shown face up for a moment. Memorise where each picture is.',
            'Cards flip face-down. Tap two cards to find a matching pair.',
            'Match every pair to clear the board. Make too many mismatches and you lose the round.',
        ];
    }

    protected function build(string $seed, array $rules, string $mode, int $level, int $attempt, int $n): array
    {
        $g = $rules['game'];
        $pairs = self::PAIRS_BY_LEVEL[min($level, count(self::PAIRS_BY_LEVEL)) - 1];
        [$cols, $rows] = self::LAYOUTS[$pairs];
        $rng = $this->rng($seed, $this->label($mode, $level, $attempt, $n));

        $symbols = $rng->sample(self::SYMBOLS, $pairs);
        $cards = $rng->shuffle([...$symbols, ...$symbols]);
        $perPair = $this->decay((int) $g['preview_ms_per_pair'], (int) $g['preview_decay_ms_per_level'], (int) $g['preview_min_ms_per_pair'], $level);
        $allowed = (int) $g['mismatches_allowed'];

        return [
            'units' => $pairs,
            'preview_ms' => $pairs * $perPair,
            'input_min' => 0,
            'input_max' => $pairs * 2,
            'max_inputs' => ($pairs + $allowed + 1) * 2,
            'cols' => $cols,
            'rows' => $rows,
            'cards' => $cards,
            'mismatches_allowed' => $allowed,
        ];
    }

    public function evaluate(array $spec, array $inputs): Evaluation
    {
        $cards = $spec['cards'];
        $pairs = intdiv(count($cards), 2);
        $matched = [];
        $mismatches = 0;

        for ($i = 0; $i + 1 < count($inputs); $i += 2) {
            [$a, $b] = [(int) $inputs[$i], (int) $inputs[$i + 1]];

            if ($a !== $b && ! isset($matched[$a]) && ! isset($matched[$b]) && $cards[$a] === $cards[$b]) {
                $matched[$a] = $matched[$b] = true;
            } else {
                $mismatches++;
            }

            $found = intdiv(count($matched), 2);

            if ($mismatches > $spec['mismatches_allowed']) {
                return Evaluation::failed($found, $found + $mismatches, $mismatches);
            }
            if ($found === $pairs) {
                return Evaluation::cleared($found, $found + $mismatches, $mismatches);
            }
        }

        $found = intdiv(count($matched), 2);

        return Evaluation::incomplete($found, max($pairs, $found + $mismatches), $mismatches);
    }

    public function settingsFields(): array
    {
        return [
            ['key' => 'preview_ms_per_pair', 'label' => 'Preview time per pair at level 1', 'type' => 'number', 'min' => 100, 'max' => 5000, 'unit' => 'ms'],
            ['key' => 'preview_decay_ms_per_level', 'label' => 'Preview reduction per level', 'type' => 'number', 'min' => 0, 'max' => 1000, 'unit' => 'ms'],
            ['key' => 'preview_min_ms_per_pair', 'label' => 'Shortest preview per pair', 'type' => 'number', 'min' => 100, 'max' => 5000, 'unit' => 'ms'],
            ['key' => 'mismatches_allowed', 'label' => 'Mismatches allowed per board', 'type' => 'number', 'min' => 0, 'max' => 20],
        ];
    }
}
