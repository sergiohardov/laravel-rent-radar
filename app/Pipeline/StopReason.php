<?php

namespace App\Pipeline;

enum StopReason: string
{
    /** The source has no more listings. */
    case Exhausted = 'exhausted';

    /** Enough already seen listings in a row: the rest is older. */
    case SeenInRow = 'seen_in_row';

    /** The run is close to the hosting's 60 s limit. */
    case TimeBudget = 'time_budget';
}
