<?php

use App\Currency\CurrencyCode;
use App\Sources\Olx\OlxSource;
use App\Sources\RawListing;
use App\Sources\SourceName;
use App\Sources\SourceRegistry;
use App\Sources\UnexpectedResponse;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->page = json_decode(file_get_contents(base_path('tests/Fixtures/olx/offers-odesa-rent-page-1.json')), true);
    $this->lastPage = ['data' => [], 'metadata' => ['source' => ['promoted' => [], 'organic' => []]], 'links' => []];
});

/**
 * @return list<RawListing>
 */
function olxListings(?int $limit = null): array
{
    $listings = [];
    foreach (app(OlxSource::class)->fetch() as $listing) {
        $listings[] = $listing;
        if ($limit !== null && count($listings) >= $limit) {
            break;
        }
    }

    return $listings;
}

it('skips paid promotions and keeps the organic order', function () {
    Http::fake(['www.olx.ua/*' => Http::sequence()->push($this->page)->push($this->lastPage)]);

    $ids = collect(olxListings())->pluck('externalId');

    expect($ids)->toHaveCount(10)
        ->and($ids->first())->toBe('903720947')
        ->and($ids)->not->toContain('933141228', '934409359', '936117326');
});

it('maps an offer from the OLX response', function () {
    Http::fake(['www.olx.ua/*' => Http::sequence()->push($this->page)->push($this->lastPage)]);

    $listing = olxListings()[0];

    expect($listing->source)->toBe(SourceName::Olx)
        ->and($listing->url)->toStartWith('https://www.olx.ua/d/uk/obyavlenie/')
        ->and($listing->title)->toBe('Сдам 2ую квартиру ЖК Фонтан, Макаренко 2а/Паркинг, Генератор, теплосче')
        ->and($listing->description)->toStartWith('Сдам дизайнерскую квартиру кухня студия и две раздельные комнаты')
        ->and($listing->address)->toBe('Київський')
        ->and($listing->price)->toBe(20000)
        ->and($listing->currency)->toBe(CurrencyCode::Uah)
        ->and([$listing->rooms, $listing->floor, $listing->floorsTotal])->toBe([2, 9, 16])
        ->and($listing->area)->toBe(90.0)
        ->and($listing->publishedAt->utc()->toDateTimeString())->toBe('2025-10-17 22:42:55');
});

it('drops a map point that is only a district centre', function () {
    Http::fake(['www.olx.ua/*' => Http::sequence()->push($this->page)->push($this->lastPage)]);

    $listings = collect(olxListings())->keyBy('externalId');

    // radius 7 km: district centre
    expect($listings['903720947']->lat)->toBeNull()
        // radius 1 km: kept
        ->and($listings['936758180']->lat)->toBe(46.467)
        ->and($listings['936758180']->lng)->toBe(30.745);
});

it('turns the HTML description into plain text', function () {
    Http::fake(['www.olx.ua/*' => Http::sequence()->push($this->page)->push($this->lastPage)]);

    $description = collect(olxListings())->firstWhere('externalId', '936758180')->description;

    expect($description)->toStartWith("Здається стильна та світла квартира з сучасним ремонтом.\n\nПростора кухня-вітальня")
        ->and($description)->not->toContain('<br');
});

it('reads a price marked as negotiable', function () {
    Http::fake(['www.olx.ua/*' => Http::sequence()->push($this->page)->push($this->lastPage)]);

    $listing = collect(olxListings())->firstWhere('externalId', '936758180');

    expect($listing->price)->toBe(400)
        ->and($listing->currency)->toBe(CurrencyCode::Usd);
});

it('asks for Odesa long-term rent, newest first, 50 per page', function () {
    Http::fake(['www.olx.ua/*' => Http::response($this->lastPage)]);

    olxListings();

    Http::assertSent(fn (Request $request) => $request['category_id'] === 1760
        && $request['city_id'] === 62
        && $request['sort_by'] === 'created_at:desc'
        && $request['offset'] === 0
        && $request['limit'] === 50);
});

it('pages by offset while there is a next page', function () {
    config(['sources.olx.max_pages' => 3]);
    Http::fake(['www.olx.ua/*' => Http::sequence()->push($this->page)->push($this->page)->push($this->lastPage)]);

    olxListings();

    Http::assertSentCount(3);
    Http::assertSent(fn (Request $request) => $request['offset'] === 50);
});

it('does not request the next page while the pipeline still reads the first', function () {
    Http::fake(['www.olx.ua/*' => Http::response($this->page)]);

    olxListings(limit: 3);

    Http::assertSentCount(1);
});

it('fails on a non-JSON answer', function () {
    Http::fake(['www.olx.ua/*' => Http::response('<HTML><HEAD><TITLE>ERROR: The request could not be satisfied</TITLE>', 200)]);

    olxListings();
})->throws(UnexpectedResponse::class, 'Source [olx] returned an unexpected response (200)');

it('fails on a blocked request', function () {
    Http::fake(['www.olx.ua/*' => Http::response('Request blocked.', 403)]);

    olxListings();
})->throws(RequestException::class);

it('is registered as the olx source', function () {
    expect(app(SourceRegistry::class)->get(SourceName::Olx))->toBeInstanceOf(OlxSource::class);
});

it('reads ticked blackout items as outage hints', function () {
    Http::fake(['www.olx.ua/*' => Http::sequence()->push($this->page)->push($this->lastPage)]);

    $listings = collect(olxListings())->keyBy('externalId');

    expect($listings['901204760']->sourceHints)->toBe([
        'generator' => true, 'generator_water' => true, 'generator_heating' => true, 'generator_elevator' => true,
    ])
        // unticked items stay unknown, not "no"
        ->and($listings['936755882']->sourceHints)->toBe(['generator_water' => true, 'generator_heating' => true])
        ->and($listings['903720947']->sourceHints)->toBe([]);
});
