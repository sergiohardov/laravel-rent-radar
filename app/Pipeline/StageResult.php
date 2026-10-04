<?php

namespace App\Pipeline;

final readonly class StageResult
{
    private function __construct(
        public bool $passed,
        public ?string $reason = null,
        public bool $failed = false,
    ) {}

    public static function pass(): self
    {
        return new self(true);
    }

    public static function reject(string $reason): self
    {
        return new self(false, $reason);
    }

    /**
     * The stage could not decide, e.g. a service was down; the listing is kept as failed.
     */
    public static function fail(string $reason): self
    {
        return new self(false, $reason, failed: true);
    }
}
