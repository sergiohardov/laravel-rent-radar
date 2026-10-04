<?php

use App\Models\Listing;
use App\Pipeline\Decision;
use App\Pipeline\Pipeline;
use App\Pipeline\SeenFilter;
use App\Pipeline\StageResult;
use App\Pipeline\StopReason;
use App\Sources\SourceName;
use Tests\Fakes\FakeListingSource;
use Tests\Fakes\FakeStage;

function pipeline(array $stages = [], int $stopAfterSeen = 3, float $timeBudget = 30): Pipeline
{
    return new Pipeline(new SeenFilter, $stages, $stopAfterSeen, $timeBudget);
}

it('stores new listings as pending when every stage passes', function () {
    $report = pipeline([FakeStage::passing()])->run(FakeListingSource::withIds(105, 3));

    expect($report->created)->toBe(3)
        ->and($report->passed)->toBe(3)
        ->and($report->stopReason)->toBe(StopReason::Exhausted)
        ->and(Listing::query()->pluck('decision')->unique()->all())->toBe([Decision::Pending]);
});

it('records the rejecting stage and reason and skips later stages', function () {
    $priceCheck = new FakeStage('hard_filter', fn (Listing $l) => $l->external_id === '104'
        ? StageResult::reject('price 25000 UAH > 15000 UAH')
        : StageResult::pass());
    $later = FakeStage::passing('later');

    $report = pipeline([$priceCheck, $later])->run(FakeListingSource::withIds(105, 3));

    $rejected = Listing::query()->where('external_id', '104')->sole();
    expect($report->rejected)->toBe(1)
        ->and($report->passed)->toBe(2)
        ->and($rejected->decision)->toBe(Decision::Rejected)
        ->and($rejected->rejected_by)->toBe('hard_filter')
        ->and($rejected->reject_reason)->toBe('price 25000 UAH > 15000 UAH')
        ->and($later->handled)->toBe(['105', '103']);
});

it('saves fields a stage fills in', function () {
    $enricher = new FakeStage('enricher', function (Listing $listing) {
        $listing->enrichment = ['generator' => true];

        return StageResult::pass();
    });

    pipeline([$enricher])->run(FakeListingSource::withIds(1, 1));

    expect(Listing::query()->sole()->enrichment)->toBe(['generator' => true]);
});

it('does not process an already seen listing again', function () {
    pipeline()->run(FakeListingSource::withIds(102, 2));
    $stage = FakeStage::passing();

    $report = pipeline([$stage])->run(FakeListingSource::withIds(103, 3));

    expect($report->seen)->toBe(2)
        ->and($report->created)->toBe(1)
        ->and($stage->handled)->toBe(['103'])
        ->and(Listing::query()->count())->toBe(3);
});

it('stops after enough seen listings in a row without pulling the rest', function () {
    pipeline()->run(FakeListingSource::withIds(100, 50));
    $source = FakeListingSource::withIds(102, 52);

    $report = pipeline(stopAfterSeen: 3)->run($source);

    expect($report->stopReason)->toBe(StopReason::SeenInRow)
        ->and($report->created)->toBe(2)
        ->and($source->pulled)->toBe(5);
});

it('resets the seen streak when a new listing comes between seen ones', function () {
    // 103 and 101 are seen, 102 is a bumped-over gap, then everything older is seen
    pipeline()->run(FakeListingSource::withIds(103, 1));
    pipeline()->run(FakeListingSource::withIds(101, 10));
    $source = FakeListingSource::withIds(104, 14);

    $report = pipeline(stopAfterSeen: 2)->run($source);

    expect(Listing::query()->where('external_id', '102')->exists())->toBeTrue()
        ->and($report->created)->toBe(2)
        ->and($report->stopReason)->toBe(StopReason::SeenInRow);
});

it('stops when the time budget is spent', function () {
    $report = pipeline(timeBudget: 0)->run(FakeListingSource::withIds(10, 5));

    expect($report->stopReason)->toBe(StopReason::TimeBudget)
        ->and($report->fetched)->toBe(0)
        ->and(Listing::query()->count())->toBe(0);
});

it('is resolved from the container with config limits', function () {
    config(['sources.stop_after_seen' => 1]);
    pipeline()->run(FakeListingSource::withIds(2, 2, SourceName::Olx));

    $report = app(Pipeline::class)->run(FakeListingSource::withIds(2, 2, SourceName::Olx));

    expect($report->stopReason)->toBe(StopReason::SeenInRow)
        ->and($report->fetched)->toBe(1);
});
