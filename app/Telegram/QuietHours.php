<?php

namespace App\Telegram;

use App\Models\Setting;
use Carbon\CarbonInterface;

/**
 * The window from Settings when posts to the group come without sound.
 * Times are Kyiv time; a window like 23:00–08:00 crosses midnight.
 */
final class QuietHours
{
    public const TIMEZONE = 'Europe/Kyiv';

    public function isNow(?CarbonInterface $now = null): bool
    {
        $settings = Setting::current();
        if (! $settings->quiet_hours_enabled) {
            return false;
        }

        $time = ($now ?? now())->copy()->setTimezone(self::TIMEZONE)->format('H:i');
        $from = substr((string) $settings->quiet_from, 0, 5);
        $to = substr((string) $settings->quiet_to, 0, 5);

        return match (true) {
            $from === $to => false,
            $from < $to => $time >= $from && $time < $to,
            default => $time >= $from || $time < $to,
        };
    }
}
