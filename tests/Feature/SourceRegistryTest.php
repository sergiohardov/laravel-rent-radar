<?php

use App\Sources\SourceName;
use App\Sources\SourceRegistry;
use Tests\Fakes\FakeListingSource;

it('finds a source by name', function () {
    $olx = new FakeListingSource(SourceName::Olx);
    $registry = new SourceRegistry([new FakeListingSource(SourceName::Lun), $olx]);

    expect($registry->get(SourceName::Olx))->toBe($olx)
        ->and($registry->all())->toHaveCount(2);
});

it('fails on a source that is not registered', function () {
    (new SourceRegistry([]))->get(SourceName::DimRia);
})->throws(InvalidArgumentException::class, 'Source [dimria] is not registered.');

it('is resolved from the container', function () {
    expect(app(SourceRegistry::class))->toBe(app(SourceRegistry::class));
});
