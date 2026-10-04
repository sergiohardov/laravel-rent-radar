<?php

use App\Filament\Resources\Locations\Pages\ManageLocations;
use App\Location\LocationKind;
use App\Models\Location;
use App\Models\Setting;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('lists the whitelist and finds an entry by its alias', function () {
    $street = Location::factory()->create(['kind' => LocationKind::Street, 'name' => 'вулиця Академіка Заболотного', 'aliases' => ['улица Академика Заболотного']]);
    $complex = Location::factory()->create(['kind' => LocationKind::Complex, 'name' => '54-я Жемчужина', 'aliases' => []]);

    $this->get(ManageLocations::getUrl())->assertOk()->assertSee('Сектор');

    Livewire::test(ManageLocations::class)
        ->assertCanSeeTableRecords([$street, $complex])
        ->searchTable('Академика')
        ->assertCanSeeTableRecords([$street])
        ->assertCanNotSeeTableRecords([$complex]);
});

it('adds an entry with aliases by hand', function () {
    Livewire::test(ManageLocations::class)
        ->callAction('create', data: [
            'kind' => LocationKind::Complex->value,
            'name' => 'ЖК Гулливер',
            'aliases' => ['Гулівер', 'Гулливер'],
        ])
        ->assertHasNoFormErrors();

    expect(Location::query()->sole()->only(['kind', 'name', 'aliases']))->toBe([
        'kind' => LocationKind::Complex,
        'name' => 'ЖК Гулливер',
        'aliases' => ['Гулівер', 'Гулливер'],
    ]);
});

it('allows a name once per kind', function () {
    Location::factory()->create(['kind' => LocationKind::Street, 'name' => 'Марсельська вулиця']);

    Livewire::test(ManageLocations::class)
        ->callAction('create', data: ['kind' => LocationKind::Street->value, 'name' => 'Марсельська вулиця'])
        ->assertHasFormErrors(['name' => 'unique']);

    Livewire::test(ManageLocations::class)
        ->callAction('create', data: ['kind' => LocationKind::District->value, 'name' => 'Марсельська вулиця'])
        ->assertHasNoFormErrors();
});

it('edits the aliases of an entry', function () {
    $location = Location::factory()->create(['aliases' => ['old']]);

    Livewire::test(ManageLocations::class)
        ->callAction(TestAction::make('edit')->table($location), data: ['aliases' => ['old', 'Котовский']])
        ->assertHasNoFormErrors();

    expect($location->fresh()->aliases)->toBe(['old', 'Котовский']);
});

it('imports the whitelist from OpenStreetMap around the point in Settings', function () {
    Setting::current()->update(['center_lat' => 46.57941, 'center_lng' => 30.804915, 'radius_m' => 1000]);
    Http::fake(['overpass-api.de/*' => Http::response(file_get_contents(base_path('tests/Fixtures/osm/overpass-kotovskoho-1km.json')))]);

    Livewire::test(ManageLocations::class)
        ->callAction('importOsm')
        ->assertNotified();

    expect(Location::query()->where('name', 'Селище Котовського')->value('kind'))->toBe(LocationKind::District);
});

it('does not import without a sector point', function () {
    Livewire::test(ManageLocations::class)
        ->mountAction('importOsm')
        ->assertActionMounted('importOsm')
        ->callMountedAction();

    expect(Location::query()->count())->toBe(0);
    Http::assertNothingSent();
});

it('says so when OpenStreetMap does not answer', function () {
    Setting::current()->update(['center_lat' => 46.57941, 'center_lng' => 30.804915, 'radius_m' => 1000]);
    Http::fake(['overpass-api.de/*' => Http::response('busy', 504)]);
    Sleep::fake();

    Livewire::test(ManageLocations::class)
        ->callAction('importOsm')
        ->assertNotified('OpenStreetMap не ответил, попробуйте позже');

    expect(Location::query()->count())->toBe(0);
});
