<?php

namespace App\Pipeline;

final readonly class RunReport
{
    public function __construct(
        public int $fetched,
        public int $seen,
        public int $created,
        public int $passed,
        public int $rejected,
        public StopReason $stopReason,
        public int $failed = 0,
        public float $seconds = 0.0,
        public int $peakMemoryMb = 0,
    ) {}
}
