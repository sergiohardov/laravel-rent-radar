<?php

use App\Models\Source;
use App\Sources\SourceName;
use App\Sources\SourceRunner;
use App\Telegram\SendAlert;
use Illuminate\Support\Facades\Queue;
use Tests\Fakes\FailingListingSource;
use Tests\Fakes\FakeListingSource;

beforeEach(function () {
    Queue::fake();
    $this->travelTo('2026-10-04 13:55:00');
});

/**
 * @return list<string> texts of the queued alerts
 */
function queuedAlerts(): array
{
    return Queue::pushed(SendAlert::class)->map(fn (SendAlert $job) => $job->text)->values()->all();
}

function failRuns(SourceName $name, int $times, string $message = 'HTTP request returned status code 403'): void
{
    foreach (range(1, $times) as $_) {
        try {
            app(SourceRunner::class)->run(new FailingListingSource($name, $message));
        } catch (RuntimeException) {
        }
    }
}

it('alerts once when a source fails three runs in a row', function () {
    Source::for(SourceName::Olx)->update(['last_success_at' => '2026-10-04 12:10:00+00']);

    failRuns(SourceName::Olx, 2);
    expect(queuedAlerts())->toBe([]);

    failRuns(SourceName::Olx, 3, 'Server error: <503>');

    expect(queuedAlerts())->toBe([
        "⚠️ <b>OLX</b> не работает: ошибок подряд — 3\n"
        ."<code>RuntimeException: Server error: &lt;503&gt;</code>\n"
        .'Последний успешный запуск: 04.10 15:10',
    ]);
});

it('says when a source never succeeded', function () {
    failRuns(SourceName::Lun, 3);

    expect(queuedAlerts()[0])->toEndWith('Последний успешный запуск: не было');
});

it('tells when a failing source works again', function () {
    failRuns(SourceName::DimRia, 4);

    app(SourceRunner::class)->run(FakeListingSource::withIds(1, 1, SourceName::DimRia));
    app(SourceRunner::class)->run(FakeListingSource::withIds(2, 1, SourceName::DimRia));

    expect(queuedAlerts())->toHaveCount(2)
        ->and(queuedAlerts()[1])->toBe('✅ <b>DIM.RIA</b> снова работает после ошибок подряд: 4');
});

it('stays quiet when a source recovers before the alert', function () {
    failRuns(SourceName::Lun, 2);

    app(SourceRunner::class)->run(FakeListingSource::withIds(1, 1, SourceName::Lun));

    expect(queuedAlerts())->toBe([]);
});
