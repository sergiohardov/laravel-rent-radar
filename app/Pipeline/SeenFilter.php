<?php

namespace App\Pipeline;

use App\Models\Listing;
use App\Sources\RawListing;

/**
 * Duplicates are cut only by source + external id, never across sites.
 */
final class SeenFilter
{
    public function isSeen(RawListing $raw): bool
    {
        return Listing::query()
            ->where('source', $raw->source)
            ->where('external_id', $raw->externalId)
            ->exists();
    }
}
