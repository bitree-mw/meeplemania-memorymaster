<?php

namespace App\Games;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A rule violation during gameplay, rendered as JSON with a stable code the
 * frontend can react to.
 */
class GameException extends RuntimeException
{
    public function __construct(string $message, public readonly string $errorCode, public readonly int $status = 409, public readonly array $extra = [])
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'code' => $this->errorCode, ...$this->extra], $this->status);
    }
}
