<?php

use App\Currency\CurrencyCode;
use App\Currency\ExchangeRates;
use App\Models\Listing;
use App\Models\Setting;
use App\Pipeline\Decision;
use App\Pipeline\Flag;
use App\Pipeline\Pipeline;
use App\Pipeline\Stages\HardFilter;
use App\Sources\RawListing;
use App\Sources\SourceName;
use Tests\Fakes\FakeExchangeRates;
use Tests\Fakes\FakeListingSource;

beforeEach(function () {
    app()->instance(ExchangeRates::class, new FakeExchangeRates(usd: 45.0));
});

/**
 * Defaults from REQUIREMENTS: 2 rooms, floors 1–6, up to 15 000 UAH.
 */
function hardFilter(array $listing): array
{
    $model = Listing::factory()->make(array_merge(
        ['rooms' => 2, 'floor' => 3, 'price' => 12000, 'currency' => CurrencyCode::Uah],
        $listing,
    ));
    $result = app(HardFilter::class)->handle($model);

    return [$result, $model];
}

it('passes a listing that fits and stores the price in hryvnias', function () {
    [$result, $listing] = hardFilter(['price' => 300, 'currency' => CurrencyCode::Usd]);

    expect($result->passed)->toBeTrue()
        ->and($listing->price_uah)->toBe(13500)
        ->and($listing->flags)->toBeNull();
});

it('rejects with a readable reason', function (array $listing, string $reason) {
    [$result] = hardFilter($listing);

    expect($result->passed)->toBeFalse()
        ->and($result->reason)->toBe($reason);
})->with([
    'one room' => [['rooms' => 1], 'rooms 1 not in 2–2, no euro two-room in the text'],
    'three rooms' => [['rooms' => 3], 'rooms 3 not in 2–2'],
    'ninth floor' => [['floor' => 9], 'floor 9 not in 1–6'],
    'price in hryvnias' => [['price' => 25000], 'price 25 000 UAH > 15 000 UAH'],
    'price in dollars' => [['price' => 600, 'currency' => CurrencyCode::Usd], 'price 600 USD = 27 000 UAH > 15 000 UAH'],
]);

it('lets an unknown value through with a flag', function () {
    [$result, $listing] = hardFilter(['rooms' => null, 'floor' => null]);

    expect($result->passed)->toBeTrue()
        ->and($listing->hasFlag(Flag::UnknownRooms))->toBeTrue()
        ->and($listing->hasFlag(Flag::UnknownFloor))->toBeTrue()
        ->and($listing->hasFlag(Flag::UnknownPrice))->toBeFalse();
});

it('rejects an unknown value when its switch is off', function () {
    Setting::current()->update(['allow_unknown_floor' => false]);

    [$result] = hardFilter(['floor' => null]);

    expect($result->reason)->toBe('floor unknown');
});

it('treats a price as unknown when the exchange rate is unavailable', function () {
    app()->instance(ExchangeRates::class, new FakeExchangeRates(usd: null));

    [$result, $listing] = hardFilter(['price' => 400, 'currency' => CurrencyCode::Usd]);

    expect($result->passed)->toBeTrue()
        ->and($listing->price_uah)->toBeNull()
        ->and($listing->hasFlag(Flag::UnknownPrice))->toBeTrue();
});

it('uses the saved settings', function () {
    Setting::current()->update(['rooms_min' => 1, 'max_price_uah' => 10000]);

    expect(hardFilter(['rooms' => 1, 'price' => 9500])[0]->passed)->toBeTrue()
        ->and(hardFilter(['rooms' => 1, 'price' => 10500])[0]->reason)->toBe('price 10 500 UAH > 10 000 UAH');
});

it('runs as a pipeline stage and fills the journal', function () {
    $source = new FakeListingSource(SourceName::Olx, [
        new RawListing(SourceName::Olx, '1', 'https://example.test/1', [], price: 14000, currency: CurrencyCode::Uah, rooms: 2, floor: 4),
        new RawListing(SourceName::Olx, '2', 'https://example.test/2', [], price: 14000, currency: CurrencyCode::Uah, rooms: 3, floor: 4),
        new RawListing(SourceName::Olx, '3', 'https://example.test/3', [], rooms: 2, floor: 4),
    ]);

    app(Pipeline::class)->run($source);

    $listings = Listing::query()->orderBy('external_id')->get();
    // passed every stage; the test queue is sync, so the card is already sent
    expect($listings[0]->decision)->toBe(Decision::Notified)
        ->and($listings[0]->price_uah)->toBe(14000)
        ->and($listings[1]->decision)->toBe(Decision::Rejected)
        ->and($listings[1]->rejected_by)->toBe('hard_filter')
        ->and($listings[1]->reject_reason)->toBe('rooms 3 not in 2–2')
        ->and($listings[2]->fresh()->flags->all())->toContain(Flag::UnknownPrice);
});

it('passes a one-room listing whose text describes a euro two-room flat', function () {
    [$result, $listing] = hardFilter(['rooms' => 1, 'title' => 'Сдам евродвушку на Палия', 'description' => 'Новый дом']);

    expect($result->passed)->toBeTrue()
        ->and($listing->hasFlag(Flag::EuroTwoRoom))->toBeTrue();
});

it('rejects a plain one-room listing and says the text has no euro two-room', function () {
    [$result] = hardFilter(['rooms' => 1, 'title' => 'Однокомнатная с евроремонтом', 'description' => 'Студия']);

    expect($result->passed)->toBeFalse()
        ->and($result->reason)->toBe('rooms 1 not in 2–2, no euro two-room in the text');
});

it('checks floor and price before reading the text for a euro two-room flat', function () {
    [$result] = hardFilter(['rooms' => 1, 'floor' => 9, 'title' => 'Евродвушка']);

    expect($result->reason)->toBe('floor 9 not in 1–6');
});

it('rejects one room as before when euro two-room flats are switched off', function () {
    Setting::current()->update(['allow_euro_two_room' => false]);

    [$result, $listing] = hardFilter(['rooms' => 1, 'title' => 'Евродвушка']);

    expect($result->reason)->toBe('rooms 1 not in 2–2')
        ->and($listing->hasFlag(Flag::EuroTwoRoom))->toBeFalse();
});

it('looks for euro two-room flats only when two rooms is the lower bound', function () {
    Setting::current()->update(['rooms_min' => 3, 'rooms_max' => 3]);

    [$result] = hardFilter(['rooms' => 1, 'title' => 'Евродвушка']);

    expect($result->reason)->toBe('rooms 1 not in 3–3');
});

it('keeps the euro two-room flag when the filter runs again after the LLM', function () {
    $listing = Listing::factory()->make(['rooms' => 1, 'floor' => 3, 'price' => 12000, 'currency' => CurrencyCode::Uah, 'description' => 'Кухня-студия и спальня']);
    $filter = app(HardFilter::class);

    $filter->handle($listing);
    $result = $filter->handle($listing);

    expect($result->passed)->toBeTrue()
        ->and($listing->flags->all())->toBe([Flag::EuroTwoRoom]);
});
