<?php

namespace App\Games\Definitions;

use App\Games\Evaluation;
use App\Games\GameDefinition;

/**
 * Number span: a row of dice is rolled and shown briefly; enter the values back
 * in order on the dice keypad.
 */
class DiceDigits extends GameDefinition
{
    public function slug(): string
    {
        return 'dice-digits';
    }

    public function name(): string
    {
        return 'Dice Digits';
    }

    public function tagline(): string
    {
        return 'Number memory';
    }

    public function description(): string
    {
        return 'Memorise the roll, then enter every die in order.';
    }

    public function icon(): string
    {
        return 'Dices';
    }

    public function color(): string
    {
        return 'ruby';
    }

    public function unit(): string
    {
        return 'die';
    }

    public function howToPlay(): array
    {
        return [
            'A row of dice is rolled and shown for a short moment.',
            'Enter each die value from left to right on the keypad (or keys 1–6).',
            'One wrong value loses the round. Every level adds a die and shortens the look.',
        ];
    }

    protected function build(string $seed, array $rules, string $mode, int $level, int $attempt, int $n): array
    {
        $g = $rules['game'];
        $length = min((int) $g['length_max'], (int) $g['length_start'] + ($level - 1));
        $rng = $this->rng($seed, $this->label($mode, $level, $attempt, $n));
        $dice = array_map(fn () => 1 + $rng->int(6), range(1, $length));
        $perDie = $this->decay((int) $g['preview_ms_per_die_start'], (int) $g['preview_ms_per_die_step'], (int) $g['preview_ms_per_die_min'], $level);

        return [
            'units' => $length,
            'preview_ms' => $length * $perDie,
            'input_min' => 1,
            'input_max' => 7,
            'max_inputs' => $length,
            'dice' => $dice,
        ];
    }

    public function evaluate(array $spec, array $inputs): Evaluation
    {
        return $this->evaluateOrdered($spec['dice'], $inputs);
    }

    public function settingsFields(): array
    {
        return [
            ['key' => 'length_start', 'label' => 'Dice at level 1', 'type' => 'number', 'min' => 1, 'max' => 12],
            ['key' => 'length_max', 'label' => 'Most dice', 'type' => 'number', 'min' => 1, 'max' => 20],
            ['key' => 'preview_ms_per_die_start', 'label' => 'Look time per die at level 1', 'type' => 'number', 'min' => 100, 'max' => 3000, 'unit' => 'ms'],
            ['key' => 'preview_ms_per_die_step', 'label' => 'Look reduction per level', 'type' => 'number', 'min' => 0, 'max' => 500, 'unit' => 'ms'],
            ['key' => 'preview_ms_per_die_min', 'label' => 'Shortest look per die', 'type' => 'number', 'min' => 100, 'max' => 3000, 'unit' => 'ms'],
        ];
    }
}
