<?php

namespace Tests\Fakes;

use App\Enrichment\Enrichment;
use App\Enrichment\ListingEnricher;
use App\Models\Listing;
use Throwable;

/**
 * Returns a fixed enrichment, or throws, without calling the LLM.
 */
final class FakeListingEnricher implements ListingEnricher
{
    /** @var list<string> */
    public array $enriched = [];

    public function __construct(
        private readonly Enrichment|Throwable $answer = new Enrichment,
    ) {}

    public function enrich(Listing $listing): Enrichment
    {
        $this->enriched[] = $listing->external_id;

        if ($this->answer instanceof Throwable) {
            throw $this->answer;
        }

        return $this->answer;
    }
}
