<?php

use App\Location\LocationKind;
use App\Models\Location;
use Illuminate\Database\UniqueConstraintViolationException;

it('stores a whitelist entry with its aliases', function () {
    Location::query()->create([
        'kind' => LocationKind::Complex,
        'name' => 'ЖК 32 Перлина',
        'aliases' => ['32 Жемчужина', '32 перлина', 'Тридцять друга перлина'],
    ]);

    $location = Location::query()->sole();
    expect($location->kind)->toBe(LocationKind::Complex)
        ->and($location->aliases)->toBe(['32 Жемчужина', '32 перлина', 'Тридцять друга перлина']);
});

it('refuses the same name twice within a kind', function () {
    Location::factory()->create(['kind' => LocationKind::District, 'name' => 'Аркадія']);

    Location::factory()->create(['kind' => LocationKind::District, 'name' => 'Аркадія']);
})->throws(UniqueConstraintViolationException::class);
