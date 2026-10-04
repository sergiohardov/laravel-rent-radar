<?php

namespace App\Enrichment;

use App\Models\Listing;

/**
 * Reads address, flags and missing fields from a listing's text.
 */
interface ListingEnricher
{
    /**
     * @throws EnrichmentFailed when no usable answer came back
     */
    public function enrich(Listing $listing): Enrichment;
}
