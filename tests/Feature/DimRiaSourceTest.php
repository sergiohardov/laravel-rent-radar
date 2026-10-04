<?php

use App\Currency\CurrencyCode;
use App\Sources\DimRia\DimRiaSource;
use App\Sources\RawListing;
use App\Sources\SourceName;
use App\Sources\SourceRegistry;
use App\Sources\UnexpectedResponse;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

function dimriaFixture(string $name): array
{
    return json_decode(file_get_contents(base_path("tests/Fixtures/dimria/{$name}.json")), true);
}

/**
 * The search fixture lists 20 ids; three of them have card fixtures, the rest answer 404.
 */
function fakeDimRia(?array $search = null): void
{
    $search ??= dimriaFixture('search-odesa-rent-page-0');

    Http::fake(function (Request $request) use ($search) {
        if (str_contains($request->url(), '/node/searchEngine/v2/')) {
            return $request['page'] === 0 ? Http::response($search) : Http::response(['count' => 0, 'items' => []]);
        }

        $id = basename(parse_url($request->url(), PHP_URL_PATH));
        $file = base_path("tests/Fixtures/dimria/realty-{$id}.json");

        return is_file($file) ? Http::response(file_get_contents($file)) : Http::response('Not Found', 404);
    });
}

/**
 * @return list<RawListing>
 */
function dimriaListings(?int $limit = null): array
{
    $listings = [];
    foreach (app(DimRiaSource::class)->fetch() as $listing) {
        $listings[] = $listing;
        if ($limit !== null && count($listings) >= $limit) {
            break;
        }
    }

    return $listings;
}

it('maps a card in dollars with a street, house and district', function () {
    fakeDimRia();

    $listing = collect(dimriaListings())->firstWhere('externalId', '34998875');

    expect($listing->source)->toBe(SourceName::DimRia)
        ->and($listing->url)->toBe('https://dom.ria.com/uk/realty-dolgosrochnaya-arenda-kvartira-odessa-arkadiya-genuezskaya-ulitsa-34998875.html')
        ->and($listing->title)->toBeNull()
        ->and($listing->description)->toStartWith('Довгострокова оренда без виселення на літо')
        ->and($listing->address)->toBe('вул. Генуезька, 1Ж корпус 1 · Аркадія')
        ->and($listing->price)->toBe(1050)
        ->and($listing->currency)->toBe(CurrencyCode::Usd)
        ->and([$listing->rooms, $listing->floor, $listing->floorsTotal])->toBe([2, 19, 26])
        ->and($listing->area)->toBe(67.0)
        ->and($listing->lat)->toBe(46.43637391)
        ->and($listing->lng)->toBe(30.76090809);
});

it('reads the Kyiv publish time without an offset', function () {
    fakeDimRia();

    $listing = dimriaListings()[0];

    expect($listing->externalId)->toBe('34999109')
        ->and($listing->publishedAt->utc()->toDateTimeString())->toBe('2026-10-03 20:55:16');
});

it('keeps the street when the house number is empty', function () {
    fakeDimRia();

    $listing = collect(dimriaListings())->firstWhere('externalId', '34998903');

    expect($listing->address)->toBe('вул. Академіка Корольова · Таїрова')
        ->and($listing->price)->toBe(11490)
        ->and($listing->currency)->toBe(CurrencyCode::Uah);
});

it('skips a listing removed between the search and its card', function () {
    fakeDimRia();

    // 20 ids in the search, 3 cards exist, 17 answer 404
    expect(collect(dimriaListings())->pluck('externalId')->all())->toBe(['34999109', '34998903', '34998875']);
});

it('asks for Odesa long-term rent, newest first', function () {
    fakeDimRia(['count' => 0, 'items' => []]);

    dimriaListings();

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'searchEngine')
        && $request['operation'] === 3
        && $request['city_id'] === 12
        && $request['sort'] === 'created_at'
        && $request['page'] === 0);
});

it('requests cards one by one only while the pipeline reads', function () {
    fakeDimRia();

    dimriaListings(limit: 1);

    // the search and the first card
    Http::assertSentCount(2);
});

it('stops after a short search page', function () {
    fakeDimRia(['count' => 1, 'items' => [34999109]]);

    expect(dimriaListings())->toHaveCount(1);
    Http::assertSentCount(2);
});

it('fails on a search answer without ids', function () {
    Http::fake(['dom.ria.com/*' => Http::response('<html>Технічні роботи</html>', 200)]);

    dimriaListings();
})->throws(UnexpectedResponse::class, 'Source [dimria] returned an unexpected response (200): Технічні роботи');

it('fails on a blocked search', function () {
    Http::fake(['dom.ria.com/*' => Http::response('Forbidden', 403)]);

    dimriaListings();
})->throws(RequestException::class);

it('is registered as the dimria source', function () {
    expect(app(SourceRegistry::class)->get(SourceName::DimRia))->toBeInstanceOf(DimRiaSource::class);
});

it('builds the main photo URL', function () {
    fakeDimRia();

    expect(dimriaListings()[0]->photoUrl)->toBe('https://cdn.riastatic.com/photos/dom/photo/34634/3463453/346345342/346345342fl.jpg');
});
