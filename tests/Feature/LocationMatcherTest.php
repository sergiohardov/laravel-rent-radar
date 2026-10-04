<?php

use App\Enrichment\Enrichment;
use App\Enrichment\ListingEnricher;
use App\Location\LocationKind;
use App\Location\LocationMatcher;
use App\Models\Listing;
use App\Models\Location;
use App\Pipeline\Decision;
use App\Pipeline\Flag;
use App\Pipeline\Pipeline;
use App\Sources\SourceName;
use Tests\Fakes\FakeListingEnricher;
use Tests\Fakes\FakeListingSource;

beforeEach(function () {
    $this->street = Location::factory()->create(['kind' => LocationKind::Street, 'name' => 'вулиця Академіка Заболотного']);
    $this->complex = Location::factory()->create(['kind' => LocationKind::Complex, 'name' => 'ЖК Олімпійський']);
    $this->district = Location::factory()->create(['kind' => LocationKind::District, 'name' => 'селище Котовського']);
});

function matchLocation(array $enrichment): array
{
    $listing = Listing::factory()->make(['enrichment' => array_merge((new Enrichment)->toArray(), $enrichment)]);

    return [app(LocationMatcher::class)->handle($listing), $listing];
}

it('puts a listing on a whitelisted street in the sector', function () {
    [$result, $listing] = matchLocation(['location_id' => $this->street->id, 'street' => 'Заболотного']);

    expect($result->passed)->toBeTrue()
        ->and($listing->enrichment['sector_match'])->toBe(['id' => $this->street->id, 'kind' => 'street', 'name' => 'вулиця Академіка Заболотного'])
        ->and($listing->hasFlag(Flag::DistrictOnly))->toBeFalse();
});

it('puts a listing in a whitelisted complex in the sector', function () {
    expect(matchLocation(['location_id' => $this->complex->id])[0]->passed)->toBeTrue();
});

it('passes a district-only match with 📍?', function () {
    [$result, $listing] = matchLocation(['location_id' => $this->district->id, 'district' => 'Котовського']);

    expect($result->passed)->toBeTrue()
        ->and($listing->hasFlag(Flag::DistrictOnly))->toBeTrue();
});

it('rejects a listing outside the sector with what the LLM read', function () {
    [$result] = matchLocation(['location_id' => null, 'street' => 'Бреуса', 'district' => 'Малиновський']);

    expect($result->passed)->toBeFalse()
        ->and($result->reason)->toBe('not in sector: Бреуса · Малиновський');
});

it('rejects a location id the whitelist does not have', function () {
    [$result] = matchLocation(['location_id' => 999999]);

    expect($result->reason)->toBe('not in sector: no place named (unknown location_id 999999)');
});

it('lets everything through while the whitelist is empty', function () {
    Location::query()->delete();

    expect(matchLocation(['location_id' => null])[0]->passed)->toBeTrue();
});

it('runs last and journals out-of-sector listings', function () {
    app()->instance(ListingEnricher::class, new FakeListingEnricher(new Enrichment(generator: true, street: 'Бреуса')));

    app(Pipeline::class)->run(FakeListingSource::withIds(1, 1, SourceName::Olx));

    $listing = Listing::query()->sole();
    expect($listing->decision)->toBe(Decision::Rejected)
        ->and($listing->rejected_by)->toBe('location_matcher')
        ->and($listing->reject_reason)->toBe('not in sector: Бреуса');
});
