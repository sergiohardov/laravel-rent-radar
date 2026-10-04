<?php

use App\Currency\CurrencyCode;
use App\Currency\ExchangeRates;
use App\Enrichment\Enrichment;
use App\Enrichment\ListingEnricher;
use App\Models\Listing;
use App\Models\Setting;
use App\Pipeline\Decision;
use App\Pipeline\Flag;
use App\Pipeline\Pipeline;
use App\Pipeline\StageResult;
use App\Pipeline\Stages\HardFilter;
use App\Pipeline\Stages\TextHardFilter;
use App\Sources\RawListing;
use App\Sources\SourceName;
use Tests\Fakes\FakeExchangeRates;
use Tests\Fakes\FakeListingEnricher;
use Tests\Fakes\FakeListingSource;

beforeEach(function () {
    app()->instance(ExchangeRates::class, new FakeExchangeRates(usd: 45.0));
});

/**
 * Runs the first hard filter, then the text one, as the pipeline does.
 *
 * @return array{StageResult, Listing}
 */
function textFilter(array $listing, array $text, array $hints = []): array
{
    $model = Listing::factory()->make(array_merge(
        ['rooms' => 2, 'floor' => 3, 'price' => 12000, 'currency' => CurrencyCode::Uah],
        $listing,
        ['source_hints' => $hints],
    ));
    app(HardFilter::class)->handle($model);
    $model->enrichment = (new Enrichment(...$text))->toArray();

    return [app(TextHardFilter::class)->handle($model), $model];
}

it('fills a field the site left empty from the text and drops its ❓', function () {
    [$result, $listing] = textFilter(['rooms' => null], ['rooms' => 2, 'generator' => true]);

    expect($result->passed)->toBeTrue()
        ->and($listing->rooms)->toBe(2)
        ->and($listing->hasFlag(Flag::UnknownRooms))->toBeFalse();
});

it('rejects on a value read from the text', function () {
    [$result] = textFilter(['rooms' => null], ['rooms' => 3, 'generator' => true]);

    expect($result->passed)->toBeFalse()
        ->and($result->reason)->toBe('rooms 3 not in 2–2');
});

it('keeps the site value when the text differs', function () {
    [$result, $listing] = textFilter(['rooms' => 2], ['rooms' => 3, 'generator' => true]);

    expect($result->passed)->toBeTrue()
        ->and($listing->rooms)->toBe(2);
});

it('takes a price in dollars from the text', function () {
    [$result, $listing] = textFilter(['price' => null, 'currency' => null], ['price' => 400, 'currency' => CurrencyCode::Usd, 'generator' => true]);

    expect($result->reason)->toBe('price 400 USD = 18 000 UAH > 15 000 UAH')
        ->and($listing->hasFlag(Flag::UnknownPrice))->toBeFalse();
});

it('rejects when the text says there is no generator', function () {
    [$result] = textFilter([], ['generator' => false]);

    expect($result->passed)->toBeFalse()
        ->and($result->reason)->toBe('no generator');
});

it('passes an unknown generator with ⚡❓', function () {
    [$result, $listing] = textFilter([], []);

    expect($result->passed)->toBeTrue()
        ->and($listing->hasFlag(Flag::UnknownGenerator))->toBeTrue();
});

it('rejects an unknown generator when its switch is off', function () {
    Setting::current()->update(['allow_unknown_generator' => false]);

    [$result] = textFilter([], []);

    expect($result->reason)->toBe('generator unknown');
});

it('fills what the text is silent about from the site fields', function () {
    [$result, $listing] = textFilter([], ['generatorWater' => false], [
        'generator' => true, 'generator_water' => true, 'generator_heating' => true,
    ]);

    expect($result->passed)->toBeTrue()
        ->and($listing->enrichment['generator'])->toBeTrue()
        ->and($listing->enrichment['generator_heating'])->toBeTrue()
        // the text said no: the site does not override it
        ->and($listing->enrichment['generator_water'])->toBeFalse()
        ->and($listing->hasFlag(Flag::UnknownGenerator))->toBeFalse();
});

it('journals a text rejection under its own stage name', function () {
    app()->instance(ListingEnricher::class, new FakeListingEnricher(new Enrichment(generator: false)));

    app(Pipeline::class)->run(new FakeListingSource(SourceName::Olx, [
        new RawListing(SourceName::Olx, '1', 'https://example.test/1', [], price: 12000, currency: CurrencyCode::Uah, rooms: 2, floor: 3),
    ]));

    $listing = Listing::query()->sole();
    expect($listing->decision)->toBe(Decision::Rejected)
        ->and($listing->rejected_by)->toBe('hard_filter_text')
        ->and($listing->reject_reason)->toBe('no generator');
});

it('keeps a listing that passes both filters pending with its flags', function () {
    app()->instance(ListingEnricher::class, new FakeListingEnricher(new Enrichment(floor: 4)));

    app(Pipeline::class)->run(FakeListingSource::withIds(1, 1, SourceName::Olx));

    $listing = Listing::query()->sole();
    expect($listing->decision)->toBe(Decision::Pending)
        ->and($listing->flags->all())->toHaveCount(2)
        ->and($listing->flags->all())->toContain(Flag::UnknownPrice, Flag::UnknownGenerator);
});
