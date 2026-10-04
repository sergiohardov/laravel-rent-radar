<?php

use App\Models\Listing;
use App\Models\Source;
use App\Sources\SourceAlerts;
use App\Sources\SourceName;
use App\Sources\SourceRunner;
use App\Telegram\SendAlert;
use Illuminate\Console\Scheduling\Schedule;
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

it('alerts once when a source brings no new listings for a day', function () {
    Source::for(SourceName::Lun)->update(['last_run_at' => now()->subMinutes(5)]);
    Listing::factory()->create(['source' => SourceName::Lun, 'created_at' => now()->subHours(25)]);
    Listing::factory()->create(['source' => SourceName::Olx, 'created_at' => now()->subHour()]);

    $this->artisan('sources:check-silence')
        ->expectsOutput('lun: no new listings, alert sent')
        ->assertSuccessful();
    $this->artisan('sources:check-silence')->doesntExpectOutputToContain('lun')->assertSuccessful();

    expect(queuedAlerts())->toBe([
        "🔇 <b>ЛУН</b>: нет новых объявлений 24 ч\n"
        ."Последнее новое: 03.10 15:55\n"
        .'Последний запуск: 04.10 16:50',
    ]);
});

it('alerts about the next silence after new listings came', function () {
    $state = Source::for(SourceName::Olx);
    $state->update(['last_run_at' => now(), 'silence_alerted_at' => now()->subDays(3)]);
    Listing::factory()->create(['source' => SourceName::Olx, 'created_at' => now()->subDays(2)]);

    expect(app(SourceAlerts::class)->checkSilence($state->refresh(), now()->toImmutable()))->toBeTrue()
        ->and($state->refresh()->silence_alerted_at->equalTo(now()))->toBeTrue();
});

it('counts the silence from the first run of a source that never brought listings', function () {
    $state = Source::for(SourceName::DimRia);
    $state->update(['last_run_at' => now(), 'consecutive_failures' => 7, 'last_error' => 'ConnectionException: timeout']);

    $alerts = app(SourceAlerts::class);
    expect($alerts->checkSilence($state->refresh(), now()->addHours(23)->toImmutable()))->toBeFalse()
        ->and($alerts->checkSilence($state, now()->addHours(24)->toImmutable()))->toBeTrue()
        ->and(queuedAlerts()[0])->toContain("Последнее новое: не было\n")
        ->toEndWith('Ошибок подряд — 7: <code>ConnectionException: timeout</code>');
});

it('ignores disabled sources and sources that never ran', function () {
    Source::for(SourceName::Lun)->update(['enabled' => false, 'last_run_at' => now()->subDays(3)]);
    Source::for(SourceName::Olx);
    $this->travel(3)->days();

    $this->artisan('sources:check-silence')->assertSuccessful();

    expect(queuedAlerts())->toBe([]);
});

it('checks the silence every hour', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command, 'sources:check-silence'));

    expect($event?->expression)->toBe('15 * * * *');
});
