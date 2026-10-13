<?php

namespace Tests;

use Database\Seeders\GamesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Carbon;
use Tests\Support\TestRun;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    public const GAMES = ['memory-master', 'meeple-pairs', 'pattern-peek', 'dice-digits', 'spot-the-swap'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(GamesSeeder::class);
        Carbon::setTestNow(Carbon::parse('2026-10-13 12:00:00'));
    }

    protected function begin(string $game = 'memory-master', string $mode = 'classic', ?int $duration = null): TestRun
    {
        return new TestRun($this, $this->postJson('/api/play/sessions', array_filter(['game' => $game, 'mode' => $mode, 'duration' => $duration])));
    }

    /** Play a Level Builder run clearing $levels levels, then lose every life. */
    protected function playTo(string $game, int $levels, string $mode = 'classic'): TestRun
    {
        $run = $this->begin($game, $mode);

        for ($i = 0; $i < $levels; $i++) {
            $run->clear();
        }

        while ($run->summary === null) {
            $run->fail();
        }

        return $run;
    }
}
