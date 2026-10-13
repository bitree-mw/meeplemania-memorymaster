<?php

namespace App\Games;

/**
 * Deterministic, unpredictable random stream: HMAC-SHA256(seed, label#counter),
 * read as 32-bit unsigned integers. The same (seed, label) always yields the
 * same stream, so the server can regenerate any round to verify it, while the
 * seed itself never leaves the server.
 */
final class Rng
{
    private int $counter = 0;

    /** @var int[] */
    private array $buffer = [];

    public function __construct(private readonly string $seed, private readonly string $label) {}

    public static function newSeed(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function next(): int
    {
        if ($this->buffer === []) {
            $block = hash_hmac('sha256', $this->label.'#'.$this->counter++, $this->seed, true);
            $this->buffer = array_values(unpack('N8', $block));
        }

        return array_shift($this->buffer);
    }

    /** Integer in [0, $max). */
    public function int(int $max): int
    {
        return $this->next() % $max;
    }

    public function shuffle(array $items): array
    {
        $items = array_values($items);

        for ($i = count($items) - 1; $i > 0; $i--) {
            $j = $this->int($i + 1);
            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        }

        return $items;
    }

    /** $count distinct integers from [0, $range), in random order. */
    public function sample(int $range, int $count): array
    {
        return array_slice($this->shuffle(range(0, $range - 1)), 0, $count);
    }
}
