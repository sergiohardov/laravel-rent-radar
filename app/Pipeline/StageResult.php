<?php

namespace App\Pipeline;

final readonly class StageResult
{
    private function __construct(
        public bool $passed,
        public ?string $reason = null,
    ) {}

    public static function pass(): self
    {
        return new self(true);
    }

    public static function reject(string $reason): self
    {
        return new self(false, $reason);
    }
}
