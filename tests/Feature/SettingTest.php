<?php

use App\Models\Setting;
use Illuminate\Database\QueryException;

it('creates the settings row with the requirement defaults', function () {
    $settings = Setting::current();

    expect($settings->id)->toBe(1)
        ->and($settings->max_price_uah)->toBe(15000)
        ->and([$settings->rooms_min, $settings->rooms_max])->toBe([2, 2])
        ->and([$settings->floor_min, $settings->floor_max])->toBe([1, 6])
        ->and($settings->allow_unknown_price)->toBeTrue()
        ->and($settings->allow_unknown_generator)->toBeTrue()
        ->and($settings->hasSector())->toBeFalse();
});

it('keeps a single row and returns saved changes', function () {
    Setting::current()->update(['max_price_uah' => 14000, 'center_lat' => 46.39, 'center_lng' => 30.72, 'radius_m' => 2500]);

    $settings = Setting::current();

    expect(Setting::query()->count())->toBe(1)
        ->and($settings->max_price_uah)->toBe(14000)
        ->and($settings->hasSector())->toBeTrue();
});

it('refuses a second settings row', function () {
    Setting::current();

    $second = new Setting;
    $second->id = 2;
    $second->save();
})->throws(QueryException::class, 'settings_single_row');
