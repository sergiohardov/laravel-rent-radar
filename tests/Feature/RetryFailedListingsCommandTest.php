<?php

use App\Enrichment\Enrichment;
use App\Enrichment\ListingEnricher;
use App\Models\Listing;
use App\Pipeline\Decision;
use Illuminate\Console\Scheduling\Schedule;
use Tests\Fakes\FakeListingEnricher;

function failedListing(array $attributes = []): Listing
{
    return Listing::factory()->create(array_merge([
        'rooms' => 2, 'floor' => 3, 'price' => 12000,
        'decision' => Decision::Failed,
        'rejected_by' => 'llm_enricher',
        'reject_reason' => 'RateLimitException: rate limited',
    ], $attributes));
}

it('runs a failed listing through the pipeline again', function () {
    app()->instance(ListingEnricher::class, new FakeListingEnricher(new Enrichment(generator: true)));
    $listing = failedListing();

    $this->artisan('listings:retry-failed')
        ->expectsOutput('retried 1: passed 1, rejected 0, failed 0')
        ->assertSuccessful();

    $listing->refresh();
    expect($listing->decision)->toBe(Decision::Pending)
        ->and($listing->rejected_by)->toBeNull()
        ->and($listing->reject_reason)->toBeNull()
        ->and($listing->enrichment['generator'])->toBeTrue();
});

it('leaves old failures and other decisions alone', function () {
    $enricher = new FakeListingEnricher;
    app()->instance(ListingEnricher::class, $enricher);
    failedListing(['created_at' => now()->subDays(2)]);
    Listing::factory()->rejected('hard_filter', 'rooms 1 not in 2–2')->create();
    Listing::factory()->create();

    $this->artisan('listings:retry-failed')
        ->expectsOutput('retried 0: passed 0, rejected 0, failed 0')
        ->assertSuccessful();

    expect($enricher->enriched)->toBe([]);
});

it('stops when the time budget is spent', function () {
    config(['sources.time_budget_seconds' => 0]);
    failedListing();

    $this->artisan('listings:retry-failed')->expectsOutput('retried 0: passed 0, rejected 0, failed 0');
});

it('is scheduled hourly without overlapping', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command, 'listings:retry-failed'));

    expect($event->expression)->toBe('30 * * * *')
        ->and($event->withoutOverlapping)->toBeTrue();
});
