<?php

use App\Models\Listing;
use App\Models\Source;
use App\Sources\SourceName;
use App\Sources\SourceRegistry;
use Illuminate\Console\Scheduling\Schedule;
use Tests\Fakes\FakeListingSource;

beforeEach(function () {
    $this->freezeSecond();

    app()->instance(SourceRegistry::class, new SourceRegistry([
        FakeListingSource::withIds(10, 1, SourceName::Lun),
        FakeListingSource::withIds(20, 1, SourceName::Olx),
        FakeListingSource::withIds(30, 1, SourceName::DimRia),
    ]));
});

it('runs only the most overdue enabled source', function () {
    Source::for(SourceName::Lun)->update(['next_run_at' => now()->subMinutes(2)]);
    Source::for(SourceName::Olx)->update(['next_run_at' => now()->subMinutes(7), 'enabled' => false]);
    Source::for(SourceName::DimRia)->update(['next_run_at' => now()->subMinutes(5)]);

    $this->artisan('sources:run-due')
        ->expectsOutputToContain('dimria: fetched 1, seen 0, new 1, passed 1, rejected 0, failed 0, stop: exhausted, ')
        ->assertSuccessful();

    expect(Listing::query()->pluck('source')->all())->toBe([SourceName::DimRia])
        ->and(Source::for(SourceName::DimRia)->next_run_at->isFuture())->toBeTrue();
});

it('runs a source that never ran before one that is merely overdue', function () {
    Source::for(SourceName::Lun)->update(['next_run_at' => now()->subMinutes(30)]);
    Source::for(SourceName::Olx)->update(['next_run_at' => now()->addMinute()]);

    $this->artisan('sources:run-due')->assertSuccessful();

    expect(Listing::query()->pluck('source')->all())->toBe([SourceName::DimRia]);
});

it('does nothing when no source is due', function () {
    foreach (SourceName::cases() as $name) {
        Source::for($name)->update(['next_run_at' => now()->addMinutes(3)]);
    }

    $this->artisan('sources:run-due')
        ->expectsOutput('No source is due.')
        ->assertSuccessful();

    expect(Listing::query()->count())->toBe(0);
});

it('is scheduled every minute without overlapping', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command, 'sources:run-due'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('* * * * *')
        ->and($event->withoutOverlapping)->toBeTrue();
});
