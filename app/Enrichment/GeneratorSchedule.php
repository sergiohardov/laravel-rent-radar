<?php

namespace App\Enrichment;

enum GeneratorSchedule: string
{
    case RoundTheClock = 'round_the_clock';
    case Daytime = 'daytime';
    case Scheduled = 'scheduled';
    case Unknown = 'unknown';
}
