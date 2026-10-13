<?php

namespace App\Games;

use App\Models\GameMode;

/**
 * One Meeple Mania mini-game. A definition generates rounds deterministically
 * from a seed and judges a player's inputs; the platform (PlayService) handles
 * sessions, modes, lives, timing checks, scoring and persistence.
 *
 * A round "spec" is what the client renders. Every spec carries:
 *   key, level, units (difficulty size, used for scoring), preview_ms (time the
 *   stimulus is shown before input unlocks), input_min/input_max (allowed input
 *   values, max exclusive), max_inputs, input_timeout_ms, target_ms
 * plus game-specific fields.
 */
abstract class GameDefinition
{
    abstract public function slug(): string;

    abstract public function name(): string;

    abstract public function tagline(): string;

    abstract public function description(): string;

    /** Lucide icon name. */
    abstract public function icon(): string;

    /** Palette colour key (see resources/js/game/palette.js). */
    abstract public function color(): string;

    /** Singular noun for what a round is measured in, e.g. "tile". */
    abstract public function unit(): string;

    /** @return string[] Short how-to-play steps. */
    abstract public function howToPlay(): array;

    /**
     * Game-specific part of a round. Must be deterministic for its arguments.
     *
     * @param  int  $attempt  Lives lost so far (level modes); 0 in Timed Rush.
     * @param  int  $n  Round number in Timed Rush; 0 in level modes.
     */
    abstract protected function build(string $seed, array $rules, string $mode, int $level, int $attempt, int $n): array;

    /** Judge the inputs for a spec (inputs after the decisive one are ignored). */
    abstract public function evaluate(array $spec, array $inputs): Evaluation;

    /**
     * Admin form fields for this game's "game" rules section.
     *
     * @return array<int, array{key:string, label:string, type:string, min?:int, max?:int, unit?:string, help?:string}>
     */
    abstract public function settingsFields(): array;

    public function spec(array $rules, string $seed, string $mode, int $level, int $attempt = 0, int $n = 0): array
    {
        $payload = $this->build($seed, $rules, $mode, $level, $attempt, $n);
        $scoring = $rules['scoring'];

        return [
            'key' => self::key($mode, $level, $attempt, $n),
            'level' => $level,
            ...$payload,
            // Level modes share the Level Builder idle timeout; Timed Rush has its own clock.
            'input_timeout_ms' => $mode === GameMode::TIMED ? 0 : (int) $rules['classic']['input_timeout_ms'],
            'target_ms' => $payload['units'] * (int) $scoring['speed_target_ms_per_unit'],
        ];
    }

    public static function key(string $mode, int $level, int $attempt, int $n): string
    {
        return $mode === GameMode::TIMED ? "N{$n}" : "L{$level}A{$attempt}";
    }

    /** Difficulty level for Timed Rush round $n. */
    public function timedLevel(array $rules, int $n): int
    {
        $t = $rules['timed'];

        return min((int) $t['max_level'], (int) $t['start_level'] + intdiv($n - 1, max(1, (int) $t['level_step_every'])));
    }

    protected function rng(string $seed, string $label): Rng
    {
        return new Rng($seed, $this->slug().':'.$label);
    }

    /** Label shared by every round of one spec key. */
    protected function label(string $mode, int $level, int $attempt, int $n): string
    {
        return $mode.':'.self::key($mode, $level, $attempt, $n);
    }

    /** Linear decay from $start by $step per level, floored at $min. */
    protected function decay(int $start, int $step, int $min, int $level): int
    {
        return max($min, $start - ($level - 1) * $step);
    }

    /** Prefix-matching evaluation shared by ordered-recall games. */
    protected function evaluateOrdered(array $expected, array $inputs): Evaluation
    {
        $correct = 0;

        foreach ($inputs as $i => $value) {
            if (! array_key_exists($i, $expected)) {
                break;
            }
            if ((int) $value !== (int) $expected[$i]) {
                return Evaluation::failed($correct, count($expected));
            }
            $correct++;
        }

        return $correct === count($expected)
            ? Evaluation::cleared($correct, count($expected))
            : Evaluation::incomplete($correct, count($expected));
    }

    /** Public metadata shared with the frontend. */
    public function toArray(): array
    {
        return [
            'slug' => $this->slug(),
            'name' => $this->name(),
            'tagline' => $this->tagline(),
            'description' => $this->description(),
            'icon' => $this->icon(),
            'color' => $this->color(),
            'unit' => $this->unit(),
        ];
    }
}
