<?php

namespace App\Pipeline\Stages;

use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\InternalServerException;
use Anthropic\Core\Exceptions\RateLimitException;
use App\Enrichment\EnrichmentFailed;
use App\Enrichment\ListingEnricher;
use App\Models\Listing;
use App\Pipeline\Stage;
use App\Pipeline\StageResult;

/**
 * The LLM reads the text of a listing that passed the cheap checks. A passing
 * service hiccup fails only this listing; a bad key or a broken request fails
 * the whole run, so the source's error counter and alert see it.
 */
final class EnrichStage implements Stage
{
    public function __construct(
        private readonly ListingEnricher $enricher,
    ) {}

    public function name(): string
    {
        return 'llm_enricher';
    }

    public function handle(Listing $listing): StageResult
    {
        try {
            $enrichment = $this->enricher->enrich($listing);
        } catch (EnrichmentFailed|RateLimitException|InternalServerException|APIConnectionException $e) {
            report($e);

            return StageResult::fail(class_basename($e).': '.$e->getMessage());
        }

        $listing->enrichment = $enrichment->toArray();
        $listing->enriched_at = now();

        return StageResult::pass();
    }
}
