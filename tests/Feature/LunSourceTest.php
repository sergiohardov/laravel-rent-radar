<?php

use App\Currency\CurrencyCode;
use App\Sources\Lun\LunSource;
use App\Sources\RawListing;
use App\Sources\SourceName;
use App\Sources\SourceRegistry;
use App\Sources\UnexpectedResponse;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->page = json_decode(file_get_contents(base_path('tests/Fixtures/lun/realties-odesa-rent-page-1.json')), true);
});

/**
 * @return list<RawListing>
 */
function lunListings(?int $limit = null): array
{
    $listings = [];
    foreach (app(LunSource::class)->fetch() as $listing) {
        $listings[] = $listing;
        if ($limit !== null && count($listings) >= $limit) {
            break;
        }
    }

    return $listings;
}

it('maps a listing from the LUN response', function () {
    Http::fake(['flatfy.ua/*' => Http::sequence()->push($this->page)->push(['data' => []])]);

    $listing = lunListings()[1];

    expect($listing->source)->toBe(SourceName::Lun)
        ->and($listing->externalId)->toBe('4728181778')
        ->and($listing->url)->toBe('https://rieltor.ua/odessa/flats-rent/view/13099694/')
        ->and($listing->title)->toBe('Новосельського вул., 90')
        ->and($listing->description)->toStartWith('Здам 1-кімнатну кв. Новосельського ріг Преображенської.')
        ->and($listing->address)->toBe('вулиця Новосельського, 90 · Центр · Приморський')
        ->and($listing->price)->toBe(6500)
        ->and($listing->currency)->toBe(CurrencyCode::Uah)
        ->and([$listing->rooms, $listing->floor, $listing->floorsTotal])->toBe([1, 2, 3])
        ->and($listing->area)->toBe(25.0)
        ->and($listing->lat)->toBe(46.479644775390625)
        ->and($listing->lng)->toBe(30.72801971435547)
        ->and($listing->publishedAt->utc()->toDateTimeString())->toBe('2026-10-03 21:03:22')
        ->and($listing->raw['site']['name'])->toBe('rieltor.ua');
});

it('maps a listing without coordinates and with a district only', function () {
    Http::fake(['flatfy.ua/*' => Http::sequence()->push($this->page)->push(['data' => []])]);

    $listing = lunListings()[0];

    expect($listing->price)->toBe(400)
        ->and($listing->currency)->toBe(CurrencyCode::Usd)
        ->and($listing->lat)->toBeNull()
        ->and($listing->address)->toBe('Приморський')
        ->and($listing->url)->toContain('olx.ua');
});

it('maps every listing of the page', function () {
    Http::fake(['flatfy.ua/*' => Http::sequence()->push($this->page)->push(['data' => []])]);

    $listings = lunListings();

    expect($listings)->toHaveCount(24)
        ->and(collect($listings)->pluck('externalId')->unique())->toHaveCount(24);
});

it('asks for Odesa long-term rent, newest first', function () {
    Http::fake(['flatfy.ua/*' => Http::response(['data' => []])]);

    lunListings();

    Http::assertSent(fn (Request $request) => $request['geo_id'] === 10016589
        && $request['section_id'] === 2
        && $request['sort'] === 'insert_time'
        && $request['page'] === 1);
});

it('does not request the next page while the pipeline still reads the first', function () {
    Http::fake(['flatfy.ua/*' => Http::response($this->page)]);

    lunListings(limit: 5);

    Http::assertSentCount(1);
});

it('stops at the page limit', function () {
    config(['sources.lun.max_pages' => 2]);
    Http::fake(['flatfy.ua/*' => Http::response($this->page)]);

    expect(lunListings())->toHaveCount(48);
    Http::assertSentCount(2);
});

it('fails on a Cloudflare challenge instead of reading it as empty', function () {
    Http::fake(['flatfy.ua/*' => Http::response('<!DOCTYPE html><html><head><title>Just a moment...</title></head></html>', 200)]);

    lunListings();
})->throws(UnexpectedResponse::class, 'Source [lun] returned an unexpected response (200): Just a moment...');

it('fails on a blocked request', function () {
    Http::fake(['flatfy.ua/*' => Http::response('Forbidden', 403)]);

    lunListings();
})->throws(RequestException::class);

it('is registered as the lun source', function () {
    expect(app(SourceRegistry::class)->get(SourceName::Lun))->toBeInstanceOf(LunSource::class);
});

it('reads what works during outages from the autonomy fields', function () {
    Http::fake(['flatfy.ua/*' => Http::sequence()->push($this->page)->push(['data' => []])]);

    $listings = collect(lunListings())->keyBy('externalId');

    // autonomy_power is the flat's own power, not required and not read
    expect($listings['4728177689']->sourceHints)->toBe(['generator_water' => true, 'generator_heating' => true, 'generator_elevator' => true])
        ->and($listings['4728177460']->sourceHints)->toBe(['generator_water' => true, 'generator_heating' => true])
        ->and($listings['4728181952']->sourceHints)->toBe([]);
});

it('builds photo URLs up to the album size in the site order', function () {
    Http::fake(['flatfy.ua/*' => Http::sequence()->push($this->page)->push(['data' => []])]);

    $photos = lunListings()[0]->photoUrls;
    expect($photos[0])->toBe('https://market-images.lunstatic.net/lun-ua/1280/960/images/1146984893.jpg')
        ->and(count($photos))->toBeGreaterThan(1)->toBeLessThanOrEqual(RawListing::MAX_PHOTOS)
        ->and($photos)->toBe(array_values(array_unique($photos)));
});
