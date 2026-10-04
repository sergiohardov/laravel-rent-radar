<?php

use App\Models\Listing;
use App\Models\Source;
use App\Sources\SourceName;
use App\Sources\SourceRegistry;
use Tests\Fakes\FailingListingSource;
use Tests\Fakes\FakeListingSource;

it('runs a source through the pipeline and prints the report', function () {
    app()->instance(SourceRegistry::class, new SourceRegistry([
        FakeListingSource::withIds(12, 3, SourceName::Olx),
    ]));

    $this->artisan('sources:run olx')
        ->expectsOutputToContain('olx: fetched 3, seen 0, new 3, passed 3, rejected 0, failed 0, stop: exhausted, ')
        ->assertSuccessful();

    expect(Listing::query()->where('source', SourceName::Olx)->count())->toBe(3);
});

it('refuses an unknown source name', function () {
    $this->artisan('sources:run avito')
        ->expectsOutput('Unknown source. Use one of: lun, olx, dimria.')
        ->assertExitCode(2);
});

it('reports a failing source without crashing', function () {
    app()->instance(SourceRegistry::class, new SourceRegistry([new FailingListingSource(SourceName::Lun)]));

    $this->artisan('sources:run lun')
        ->expectsOutput('lun: RuntimeException: HTTP request returned status code 403')
        ->assertFailed();

    expect(Source::for(SourceName::Lun)->consecutive_failures)->toBe(1);
});
