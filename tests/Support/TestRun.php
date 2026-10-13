<?php

namespace Tests\Support;

use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Plays a run through the real HTTP API like a browser would: keeps the specs
 * the server revealed, answers rounds correctly or wrongly, and advances the
 * clock by the time each round would take a human.
 */
class TestRun
{
    public string $uuid;

    public array $session;

    /** @var array<string, array> */
    public array $specs = [];

    public ?array $summary = null;

    public TestResponse $last;

    public function __construct(private TestCase $test, TestResponse $start)
    {
        $start->assertCreated();
        $this->absorb($start->json());
        $this->uuid = $this->session['uuid'];
        $this->last = $start;
    }

    public function spec(): array
    {
        return $this->specs[$this->session['next_key']] ?? throw new \RuntimeException("Spec {$this->session['next_key']} was not revealed.");
    }

    public function clear(int $intervalMs = 300): array
    {
        return $this->sync([$this->result($this->spec(), self::solve($this->spec()), $intervalMs)]);
    }

    public function fail(): array
    {
        return $this->sync([$this->result($this->spec(), self::wrong($this->spec()))]);
    }

    /** Build a result entry for a spec. */
    public function result(array $spec, array $inputs, int $intervalMs = 300, ?array $timings = null): array
    {
        return [
            'key' => $spec['key'],
            'inputs' => $inputs,
            'timings' => $timings ?? array_map(fn ($i) => ($i + 1) * $intervalMs, array_keys($inputs)),
        ];
    }

    /** Send results, advancing the clock by the play time they claim. */
    public function sync(array $results, bool $advance = true): array
    {
        if ($advance) {
            $this->advanceFor($results);
        }

        $this->last = $this->test->postJson("/api/play/sessions/{$this->uuid}/sync", ['results' => $results]);
        $this->last->assertOk();

        return $this->absorb($this->last->json());
    }

    public function finish(string $reason = 'ended', array $results = []): array
    {
        $this->advanceFor($results);
        $this->last = $this->test->postJson("/api/play/sessions/{$this->uuid}/finish", ['results' => $results, 'reason' => $reason]);
        $this->last->assertOk();

        return $this->absorb($this->last->json());
    }

    public function advanceFor(array $results): void
    {
        foreach ($results as $r) {
            $spec = $this->specs[$r['key']] ?? ['preview_ms' => 0];
            $ms = $spec['preview_ms'] + ($r['timings'] === [] ? 0 : end($r['timings'])) + 400;
            Carbon::setTestNow(Carbon::now()->addMilliseconds($ms));
        }
    }

    private function absorb(array $json): array
    {
        foreach ($json['specs'] ?? [] as $spec) {
            $this->specs[$spec['key']] = $spec;
        }
        $this->session = $json['session'] ?? $this->session;
        $this->summary = $json['summary'] ?? $this->summary;

        return $json;
    }

    /** Correct inputs for any game's spec. */
    public static function solve(array $spec): array
    {
        return match (true) {
            isset($spec['sequence']) => $spec['sequence'],
            isset($spec['cards']) => self::pairFlips($spec['cards']),
            isset($spec['lit']) => $spec['lit'],
            isset($spec['dice']) => $spec['dice'],
            isset($spec['tokens']) => [$spec['changed']],
        };
    }

    /** Inputs that lose the round as quickly as possible. */
    public static function wrong(array $spec): array
    {
        return match (true) {
            isset($spec['sequence']) => [($spec['sequence'][0] + 1) % $spec['tiles']],
            isset($spec['cards']) => self::mismatches($spec['cards'], $spec['mismatches_allowed'] + 1),
            isset($spec['lit']) => [current(array_diff(range(0, $spec['size'] ** 2 - 1), $spec['lit']))],
            isset($spec['dice']) => [$spec['dice'][0] % 6 + 1],
            isset($spec['tokens']) => [($spec['changed'] + 1) % count($spec['tokens'])],
        };
    }

    private static function pairFlips(array $cards): array
    {
        $bySymbol = [];
        foreach ($cards as $i => $symbol) {
            $bySymbol[$symbol][] = $i;
        }

        return array_merge(...array_values($bySymbol));
    }

    private static function mismatches(array $cards, int $count): array
    {
        $flips = [];
        $other = fn ($i) => array_search(true, array_map(fn ($s) => $s !== $cards[$i], $cards), true);

        for ($n = 0; $n < $count; $n++) {
            $flips[] = 0;
            $flips[] = $other(0);
        }

        return $flips;
    }
}
