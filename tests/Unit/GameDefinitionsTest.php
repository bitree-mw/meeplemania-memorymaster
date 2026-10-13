<?php

namespace Tests\Unit;

use App\Games\Definitions\DiceDigits;
use App\Games\Definitions\MeeplePairs;
use App\Games\Definitions\MemoryMaster;
use App\Games\Definitions\PatternPeek;
use App\Games\Definitions\SpotTheSwap;
use App\Games\GameDefinition;
use App\Games\Rng;
use App\Games\ScoreCalculator;
use App\Models\GameRound;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\TestRun;
use Tests\TestCase;

class GameDefinitionsTest extends TestCase
{
    private function rules(string $slug, string $mode = 'classic'): array
    {
        return config("games.games.{$slug}.rules") + ['mode' => $mode];
    }

    /** @return array<string, array{0: class-string<GameDefinition>, 1: string}> */
    public static function games(): array
    {
        return [
            'memory master' => [MemoryMaster::class, 'memory-master'],
            'meeple pairs' => [MeeplePairs::class, 'meeple-pairs'],
            'pattern peek' => [PatternPeek::class, 'pattern-peek'],
            'dice digits' => [DiceDigits::class, 'dice-digits'],
            'spot the swap' => [SpotTheSwap::class, 'spot-the-swap'],
        ];
    }

    #[DataProvider('games')]
    public function test_specs_are_deterministic_per_seed_and_judged_correctly(string $class, string $slug): void
    {
        $game = new $class;
        $seed = Rng::newSeed();

        foreach (['classic', 'once', 'timed'] as $mode) {
            for ($level = 1; $level <= 8; $level++) {
                $spec = $game->spec($this->rules($slug, $mode), $seed, $mode, $level, 0, $level);

                $this->assertSame($spec, $game->spec($this->rules($slug, $mode), $seed, $mode, $level, 0, $level), 'Same seed must give the same round.');
                $this->assertGreaterThan(0, $spec['preview_ms']);
                $this->assertTrue($game->evaluate($spec, TestRun::solve($spec))->cleared, "{$slug} {$mode} L{$level} solution should clear.");
                $this->assertTrue($game->evaluate($spec, TestRun::wrong($spec))->failed, "{$slug} {$mode} L{$level} wrong answer should fail.");
                $this->assertFalse($game->evaluate($spec, [])->isComplete(), 'No inputs means incomplete.');
            }
        }

        $other = $game->spec($this->rules($slug), Rng::newSeed(), 'classic', 8);
        $this->assertNotSame($game->spec($this->rules($slug), $seed, 'classic', 8), $other, 'A different seed gives a different round.');
    }

    public function test_memory_master_sequences_extend_level_to_level(): void
    {
        $game = new MemoryMaster;
        $seed = Rng::newSeed();
        $previous = [];

        for ($level = 1; $level <= 15; $level++) {
            $sequence = $game->spec($this->rules('memory-master'), $seed, 'classic', $level)['sequence'];
            $this->assertCount($level, $sequence);
            $this->assertSame($previous, array_slice($sequence, 0, $level - 1));
            $previous = $sequence;
        }

        $this->assertSame(6, $game->spec($this->rules('memory-master'), $seed, 'classic', 13)['tiles'], 'Board grows independently at level 13.');
    }

    public function test_memory_master_playback_speeds_up(): void
    {
        $game = new MemoryMaster;
        $seed = Rng::newSeed();

        $this->assertSame(700, $game->spec($this->rules('memory-master'), $seed, 'classic', 1)['flash_ms']);
        $this->assertSame(320, $game->spec($this->rules('memory-master'), $seed, 'classic', 50)['flash_ms']);
    }

    public function test_pairs_tolerates_mismatches_up_to_the_budget(): void
    {
        $game = new MeeplePairs;
        $spec = $game->spec($this->rules('meeple-pairs'), Rng::newSeed(), 'classic', 3);
        $other = array_search(true, array_map(fn ($s) => $s !== $spec['cards'][0], $spec['cards']), true);
        $mismatch = [0, $other];

        $twoMisses = $game->evaluate($spec, [...$mismatch, ...$mismatch, ...TestRun::solve($spec)]);
        $this->assertTrue($twoMisses->cleared);
        $this->assertSame(2, $twoMisses->mistakes);
        $this->assertSame(4, $twoMisses->correct);

        $this->assertTrue($game->evaluate($spec, [...$mismatch, ...$mismatch, ...$mismatch])->failed);
        $this->assertSame(1, $game->evaluate($spec, [0, 0])->mistakes, 'Flipping the same card twice counts as a mismatch.');
    }

    public function test_pattern_peek_accepts_any_order(): void
    {
        $game = new PatternPeek;
        $spec = $game->spec($this->rules('pattern-peek'), Rng::newSeed(), 'classic', 6);

        $this->assertTrue($game->evaluate($spec, array_reverse($spec['lit']))->cleared);
        $this->assertCount(8, $spec['lit']);
        $this->assertSame(5, $spec['size']);
    }

    public function test_spot_the_swap_always_changes_exactly_one_token(): void
    {
        $game = new SpotTheSwap;

        for ($i = 0; $i < 30; $i++) {
            $spec = $game->spec($this->rules('spot-the-swap'), Rng::newSeed(), 'classic', 1 + $i % 10);
            $before = $spec['tokens'][$spec['changed']];

            $this->assertNotSame($before, $spec['after']);
            $this->assertNotContains($spec['after'], $spec['tokens'], 'The new token never duplicates another.');
            if ($spec['level'] < 4) {
                $this->assertSame('color', $spec['change']);
            }
        }
    }

    public function test_timed_difficulty_ramps_by_round_number(): void
    {
        $game = new DiceDigits;
        $rules = $this->rules('dice-digits', 'timed');

        $this->assertSame([1, 1, 2, 2, 3], array_map(fn ($n) => $game->timedLevel($rules, $n), [1, 2, 3, 4, 5]));
        $this->assertSame(7, $game->timedLevel($rules, 500));
    }

    public function test_scoring_formulas(): void
    {
        $calc = new ScoreCalculator;
        $scoring = config('games.games.memory-master.rules.scoring');

        // Level 4, 4 tiles in half the 3200ms target: base 100 + 200 + half of the 80 speed bonus.
        $this->assertSame(340, $calc->round($scoring, 'classic', 4, 4, 1600, GameRound::STATUS_CLEARED)['points']);
        $this->assertSame(0, $calc->round($scoring, 'classic', 4, 4, 1600, GameRound::STATUS_FAILED)['points']);
        // Timed: 50 + 10 × 3 + speed (3 × 20 × (1 − 1200/2400)) = 110.
        $this->assertSame(110, $calc->round($scoring, 'timed', 1, 3, 1200, GameRound::STATUS_CLEARED)['points']);
        $this->assertSame(-25, $calc->round($scoring, 'timed', 1, 3, 1200, GameRound::STATUS_FAILED)['points']);
        $this->assertSame(0, $calc->round($scoring, 'timed', 1, 3, 1200, GameRound::STATUS_EXPIRED)['points']);
    }
}
