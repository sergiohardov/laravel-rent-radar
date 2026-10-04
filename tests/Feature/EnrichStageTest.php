<?php

use Anthropic\Core\Exceptions\AuthenticationException;
use App\Enrichment\AnthropicListingEnricher;
use App\Enrichment\Enrichment;
use App\Enrichment\EnrichmentFailed;
use App\Enrichment\ListingEnricher;
use App\Models\Listing;
use App\Pipeline\Decision;
use App\Pipeline\Pipeline;
use App\Pipeline\SeenFilter;
use App\Pipeline\Stages\EnrichStage;
use App\Pipeline\Stages\HardFilter;
use App\Sources\SourceName;
use Tests\Fakes\FakeListingEnricher;
use Tests\Fakes\FakeListingSource;

function enrichPipeline(FakeListingEnricher $enricher): Pipeline
{
    return new Pipeline(new SeenFilter, [new EnrichStage($enricher)], stopAfterSeen: 10, timeBudgetSeconds: 30);
}

it('stores the enrichment on the listing', function () {
    $enricher = new FakeListingEnricher(new Enrichment(street: 'вулиця Пастера', generator: true, locationId: 3));

    enrichPipeline($enricher)->run(FakeListingSource::withIds(1, 1));

    $listing = Listing::query()->sole();
    expect($listing->enrichment)->toMatchArray(['street' => 'вулиця Пастера', 'generator' => true, 'location_id' => 3])
        ->and($listing->enriched_at)->not->toBeNull()
        ->and($listing->decision)->toBe(Decision::Pending);
});

it('marks a listing failed and goes on when the LLM fails on it', function () {
    $enricher = new FakeListingEnricher(new EnrichmentFailed('The model refused: null'));

    $report = enrichPipeline($enricher)->run(FakeListingSource::withIds(2, 2));

    expect($report->failed)->toBe(2)
        ->and($report->passed)->toBe(0)
        ->and(Listing::query()->pluck('decision')->unique()->all())->toBe([Decision::Failed])
        ->and(Listing::query()->first()->rejected_by)->toBe('llm_enricher')
        ->and(Listing::query()->first()->reject_reason)->toBe('EnrichmentFailed: The model refused: null');
});

function realEnrichPipeline(): Pipeline
{
    return new Pipeline(new SeenFilter, [new EnrichStage(app(AnthropicListingEnricher::class))], stopAfterSeen: 10, timeBudgetSeconds: 30);
}

it('fails the whole run on a bad API key', function () {
    $this->anthropic->push(['type' => 'error', 'error' => ['type' => 'authentication_error', 'message' => 'invalid x-api-key']], 401);

    realEnrichPipeline()->run(FakeListingSource::withIds(2, 2));
})->throws(AuthenticationException::class);

it('marks the listing failed when the API is down', function () {
    $this->anthropic->push(['type' => 'error', 'error' => ['type' => 'api_error', 'message' => 'Internal server error']], 500);

    $report = realEnrichPipeline()->run(FakeListingSource::withIds(1, 1));

    expect($report->failed)->toBe(1)
        ->and(Listing::query()->sole()->reject_reason)->toStartWith('InternalServerException');
});

it('runs after the hard filter, so rejected listings never reach the LLM', function () {
    $enricher = new FakeListingEnricher;
    app()->instance(ListingEnricher::class, $enricher);
    $source = new FakeListingSource(SourceName::Olx, [
        ...FakeListingSource::withIds(1, 1, SourceName::Olx)->fetch(),
        new \App\Sources\RawListing(SourceName::Olx, '9', 'https://example.test/9', [], rooms: 1),
    ]);

    app(Pipeline::class)->run($source);

    expect($enricher->enriched)->toBe(['1'])
        ->and(Listing::query()->where('external_id', '9')->value('rejected_by'))->toBe('hard_filter');
});

it('is a registered stage between the two hard filters', function () {
    $stages = (fn () => $this->stages)->call(app(Pipeline::class));

    expect(array_map(fn ($stage) => $stage::class, $stages))->toBe([HardFilter::class, EnrichStage::class, \App\Pipeline\Stages\TextHardFilter::class, \App\Location\LocationMatcher::class]);
});
