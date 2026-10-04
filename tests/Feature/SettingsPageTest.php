<?php

use App\Filament\Pages\Settings;
use App\Models\Setting;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('shows the current settings', function () {
    Setting::current()->update(['max_price_uah' => 14000, 'center_lat' => 46.57941, 'radius_m' => 1000, 'center_lng' => 30.804915]);

    $this->get(Settings::getUrl())->assertOk()->assertSee('Настройки поиска');

    Livewire::test(Settings::class)
        ->assertSchemaStateSet([
            'max_price_uah' => 14000,
            'rooms_min' => 2,
            'floor_max' => 6,
            'allow_unknown_generator' => true,
            'center_lat' => 46.57941,
            'radius_m' => 1000,
        ]);
});

it('saves the settings', function () {
    Livewire::test(Settings::class)
        ->fillForm([
            'max_price_uah' => 17000,
            'rooms_min' => 1,
            'rooms_max' => 3,
            'floor_min' => 2,
            'floor_max' => 9,
            'allow_unknown_floor' => false,
            'allow_euro_two_room' => false,
            'center_lat' => 46.48,
            'center_lng' => 30.73,
            'radius_m' => 1500,
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect(Setting::current()->only(['max_price_uah', 'rooms_min', 'rooms_max', 'floor_min', 'floor_max', 'allow_unknown_floor', 'allow_unknown_price', 'allow_euro_two_room', 'center_lat', 'center_lng', 'radius_m']))
        ->toBe([
            'max_price_uah' => 17000,
            'rooms_min' => 1,
            'rooms_max' => 3,
            'floor_min' => 2,
            'floor_max' => 9,
            'allow_unknown_floor' => false,
            'allow_unknown_price' => true,
            'allow_euro_two_room' => false,
            'center_lat' => 46.48,
            'center_lng' => 30.73,
            'radius_m' => 1500,
        ]);
});

it('rejects a range whose start is above its end', function () {
    Livewire::test(Settings::class)
        ->fillForm(['rooms_min' => 3, 'rooms_max' => 2, 'floor_min' => 7, 'floor_max' => 6])
        ->call('save')
        ->assertHasFormErrors(['rooms_min' => 'lte', 'floor_min' => 'lte']);

    expect(Setting::current()->rooms_min)->toBe(2);
});

it('needs the whole sector point or none of it', function () {
    Livewire::test(Settings::class)
        ->fillForm(['center_lat' => 46.5, 'center_lng' => null, 'radius_m' => null])
        ->call('save')
        ->assertHasFormErrors(['center_lng' => 'required_with', 'radius_m' => 'required_with']);

    Livewire::test(Settings::class)
        ->fillForm(['center_lat' => null, 'center_lng' => null, 'radius_m' => null])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Setting::current()->hasSector())->toBeFalse();
});

it('requires the hard filters', function () {
    Livewire::test(Settings::class)
        ->fillForm(['max_price_uah' => null])
        ->call('save')
        ->assertHasFormErrors(['max_price_uah' => 'required']);
});

it('saves quiet hours', function () {
    Livewire::test(Settings::class)
        ->fillForm(['quiet_hours_enabled' => true, 'quiet_from' => '22:30', 'quiet_to' => '07:00'])
        ->call('save')
        ->assertHasNoFormErrors();

    $settings = Setting::current();
    expect($settings->quiet_hours_enabled)->toBeTrue()
        ->and(substr($settings->quiet_from, 0, 5))->toBe('22:30')
        ->and(substr($settings->quiet_to, 0, 5))->toBe('07:00');
});

it('rejects quiet hours that start and end at the same time', function () {
    Livewire::test(Settings::class)
        ->fillForm(['quiet_hours_enabled' => true, 'quiet_from' => '08:00', 'quiet_to' => '08:00'])
        ->call('save')
        ->assertHasFormErrors(['quiet_from' => 'different']);
});
