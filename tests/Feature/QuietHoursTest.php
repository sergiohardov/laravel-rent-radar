<?php

use App\Models\Setting;
use App\Telegram\QuietHours;
use Carbon\CarbonImmutable;

/**
 * A moment given in Kyiv time.
 */
function kyiv(string $time): CarbonImmutable
{
    return CarbonImmutable::parse("2026-10-05 {$time}", 'Europe/Kyiv');
}

it('is off unless switched on', function () {
    expect(app(QuietHours::class)->isNow(kyiv('03:00')))->toBeFalse();
});

it('covers a window across midnight, the end excluded', function (string $time, bool $quiet) {
    Setting::current()->update(['quiet_hours_enabled' => true, 'quiet_from' => '23:00', 'quiet_to' => '08:00']);

    expect(app(QuietHours::class)->isNow(kyiv($time)))->toBe($quiet);
})->with([
    'before' => ['22:59', false],
    'start' => ['23:00', true],
    'night' => ['03:30', true],
    'last minute' => ['07:59', true],
    'end' => ['08:00', false],
    'day' => ['14:00', false],
]);

it('covers a window inside one day', function (string $time, bool $quiet) {
    Setting::current()->update(['quiet_hours_enabled' => true, 'quiet_from' => '13:00', 'quiet_to' => '15:00']);

    expect(app(QuietHours::class)->isNow(kyiv($time)))->toBe($quiet);
})->with([
    ['12:59', false],
    ['13:00', true],
    ['14:30', true],
    ['15:00', false],
]);

it('reads the clock in Kyiv time', function () {
    Setting::current()->update(['quiet_hours_enabled' => true, 'quiet_from' => '23:00', 'quiet_to' => '08:00']);

    // 21:30 UTC is 00:30 in Kyiv in October
    expect(app(QuietHours::class)->isNow(CarbonImmutable::parse('2026-10-05 21:30', 'UTC')))->toBeTrue()
        ->and(app(QuietHours::class)->isNow(CarbonImmutable::parse('2026-10-05 19:30', 'UTC')))->toBeFalse();
});

it('treats an empty window as no quiet hours', function () {
    Setting::current()->update(['quiet_hours_enabled' => true, 'quiet_from' => '08:00', 'quiet_to' => '08:00']);

    expect(app(QuietHours::class)->isNow(kyiv('08:00')))->toBeFalse();
});
