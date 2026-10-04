<?php

use App\Location\LocationKind;
use App\Location\OsmLocationMapper;
use App\Models\Location;
use App\Models\Setting;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->overpass = json_decode(file_get_contents(base_path('tests/Fixtures/osm/overpass-kotovskoho-1km.json')), true);
});

function osmEntry(array $entries, string $name): ?array
{
    return collect($entries)->firstWhere('name', $name);
}

it('maps streets with their Russian and old names', function () {
    $entries = (new OsmLocationMapper)->map($this->overpass['elements']);

    // aliases from every OSM segment of the street are merged
    expect(osmEntry($entries, 'вулиця Владислава Бувалкіна')['kind'])->toBe(LocationKind::Street)
        ->and(osmEntry($entries, 'вулиця Владислава Бувалкіна')['aliases'])->toContain('вулиця Генерала Бочарова', 'улица Владислава Бувалкина')
        ->and(osmEntry($entries, 'Марсельська вулиця')['aliases'])->toBe(['Марсельская улица']);
});

it('maps complexes and the suburb, and skips shops and planning names', function () {
    $entries = collect((new OsmLocationMapper)->map($this->overpass['elements']));

    expect(osmEntry($entries->all(), 'Житловий комплекс "Гулівер"'))->toMatchArray(['kind' => LocationKind::Complex])
        ->and(osmEntry($entries->all(), 'Житловий комплекс "Гулівер"')['aliases'])->toContain('Жилой комплекс "Гулливер"', 'ЖК «на вул. Академіка Заболотного, 34»')
        ->and(osmEntry($entries->all(), '20-я Жемчужина')['kind'])->toBe(LocationKind::Complex)
        ->and(osmEntry($entries->all(), 'Селище Котовського'))->toMatchArray([
            'kind' => LocationKind::District,
            'aliases' => ['Посёлок Котовского', 'Селище Кисельово'],
        ])
        ->and($entries->pluck('name'))->not->toContain('АТБ-Маркет', 'ТП-2502', 'Мікрорайон "ІІІ-3-2"', 'Ліски');
});

it('imports the whitelist around a point and saves the point to Settings', function () {
    Http::fake(['overpass-api.de/*' => Http::response($this->overpass)]);

    $this->artisan('locations:import-osm --lat=46.579410 --lng=30.804915 --radius=1000')
        ->expectsOutputToContain('within 1000 m:')
        ->assertSuccessful();

    $settings = Setting::current();
    expect([$settings->center_lat, $settings->center_lng, $settings->radius_m])->toBe([46.57941, 30.804915, 1000])
        ->and(Location::query()->where('kind', LocationKind::Street)->count())->toBeGreaterThan(10)
        ->and(Location::query()->where('name', 'Селище Котовського')->value('kind'))->toBe(LocationKind::District);
    Http::assertSent(fn (Request $request) => str_contains($request['data'], 'around:1000,46.57941,30.804915')
        && $request->hasHeader('User-Agent', 'laravel-rent-radar (personal flat search)'));
});

it('keeps aliases added by hand on a second import', function () {
    Http::fake(['overpass-api.de/*' => Http::response($this->overpass)]);
    $this->artisan('locations:import-osm --lat=46.579410 --lng=30.804915 --radius=1000');
    $suburb = Location::query()->where('name', 'Селище Котовського')->sole();
    $suburb->update(['aliases' => [...$suburb->aliases, 'Котовский']]);
    $count = Location::query()->count();

    $this->artisan('locations:import-osm')->expectsOutputToContain(', 0 new.');

    expect(Location::query()->count())->toBe($count)
        ->and($suburb->fresh()->aliases)->toContain('Котовский', 'Посёлок Котовского');
});

it('asks for a point when Settings have none', function () {
    $this->artisan('locations:import-osm')
        ->expectsOutput('Set the sector point and radius first: --lat= --lng= --radius=')
        ->assertExitCode(2);
});
