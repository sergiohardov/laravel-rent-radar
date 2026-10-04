<?php

use App\Models\Source;
use App\Sources\SourceName;
use App\Sources\SourceRunner;
use Tests\Fakes\FailingListingSource;
use Tests\Fakes\FakeListingSource;

beforeEach(function () {
    $this->freezeSecond();
});

it('records a successful run and schedules the next one inside the interval', function () {
    app(SourceRunner::class)->run(FakeListingSource::withIds(5, 2, SourceName::Olx));

    $state = Source::for(SourceName::Olx);
    expect($state->last_run_at->equalTo(now()))->toBeTrue()
        ->and($state->last_success_at->equalTo(now()))->toBeTrue()
        ->and($state->last_report)->toMatchArray(['created' => 2, 'stopReason' => 'exhausted'])
        ->and($state->consecutive_failures)->toBe(0)
        ->and($state->next_run_at->between(now()->addMinutes(5), now()->addMinutes(10)))->toBeTrue();
});

it('records a failure, counts failures in a row and rethrows', function () {
    $runner = app(SourceRunner::class);

    foreach ([1, 2] as $attempt) {
        expect(fn () => $runner->run(new FailingListingSource(SourceName::Lun)))
            ->toThrow(RuntimeException::class);
    }

    $state = Source::for(SourceName::Lun);
    expect($state->consecutive_failures)->toBe(2)
        ->and($state->last_error)->toBe('RuntimeException: HTTP request returned status code 403')
        ->and($state->last_success_at)->toBeNull()
        ->and($state->next_run_at)->not->toBeNull();
});

it('resets the failure streak after a success', function () {
    Source::for(SourceName::Lun)->update(['consecutive_failures' => 3, 'last_error' => 'old']);

    app(SourceRunner::class)->run(FakeListingSource::withIds(1, 1, SourceName::Lun));

    expect(Source::for(SourceName::Lun)->consecutive_failures)->toBe(0)
        ->and(Source::for(SourceName::Lun)->last_error)->toBe('old');
});

it('creates the state row with interval defaults', function () {
    $state = Source::for(SourceName::DimRia);

    expect($state->enabled)->toBeTrue()
        ->and([$state->interval_min_minutes, $state->interval_max_minutes])->toBe([5, 10])
        ->and($state->isDue(now()->toImmutable()))->toBeTrue();
});

it('stores the failure without the API token', function () {
    expect(fn () => app(SourceRunner::class)->run(new FailingListingSource(SourceName::Olx, 'cURL error 28 for https://api.scrape.do/?token=t0k3n&url=x')))
        ->toThrow(RuntimeException::class);

    expect(Source::for(SourceName::Olx)->last_error)->toBe('RuntimeException: cURL error 28 for https://api.scrape.do/?token=***&url=x');
});
