<?php

namespace App\Pipeline;

use App\Models\Listing;

/**
 * One pipeline step. It may fill fields on the listing; the pipeline saves them.
 */
interface Stage
{
    /** Stable name written to listings.rejected_by, e.g. "hard_filter". */
    public function name(): string;

    public function handle(Listing $listing): StageResult;
}
