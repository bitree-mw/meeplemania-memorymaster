<?php

namespace App\Games;

/** The server's verdict on a player's inputs for one round. */
final class Evaluation
{
    public function __construct(
        public readonly bool $cleared,
        public readonly bool $failed,
        /** Correct answers given, for accuracy. */
        public readonly int $correct,
        /** Answers expected, for accuracy. */
        public readonly int $expected,
        public readonly int $mistakes,
    ) {}

    public static function cleared(int $correct, int $expected, int $mistakes = 0): self
    {
        return new self(true, false, $correct, $expected, $mistakes);
    }

    public static function failed(int $correct, int $expected, int $mistakes = 1): self
    {
        return new self(false, true, $correct, $expected, $mistakes);
    }

    /** The player stopped before finishing (idle timeout or the clock ran out). */
    public static function incomplete(int $correct, int $expected, int $mistakes = 0): self
    {
        return new self(false, false, $correct, $expected, $mistakes);
    }

    public function isComplete(): bool
    {
        return $this->cleared || $this->failed;
    }
}
