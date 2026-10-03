<?php

use Illuminate\Http\Client\StrayRequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

it('runs against the migrated Postgres test database', function () {
    expect(DB::connection()->getDriverName())->toBe('pgsql')
        ->and(DB::connection()->getDatabaseName())->toBe('rent_radar_test')
        ->and(Schema::hasTable('users'))->toBeTrue();
});

it('blocks real HTTP requests', function () {
    Http::get('https://flatfy.ua/api/realties');
})->throws(StrayRequestException::class);
