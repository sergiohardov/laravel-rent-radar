<?php

namespace App\Sources;

use App\Models\Source;

/**
 * How a listing source is doing, for the dashboard and the group's /status.
 */
enum SourceState: string
{
    case Off = 'off';
    case NeverRun = 'never_run';
    /** Three run windows missed in a row: the scheduler itself stopped */
    case Stalled = 'stalled';
    case Failing = 'failing';
    case Erring = 'erring';
    case Ok = 'ok';

    public static function of(Source $source): self
    {
        $failures = $source->consecutive_failures;

        return match (true) {
            ! $source->enabled => self::Off,
            $source->last_run_at === null => self::NeverRun,
            $source->last_run_at->lt(now()->subMinutes($source->interval_max_minutes * 3)) => self::Stalled,
            $failures >= (int) config('sources.alert_after_failures') => self::Failing,
            $failures > 0 => self::Erring,
            default => self::Ok,
        };
    }

    public function label(Source $source): string
    {
        return match ($this) {
            self::Off => 'выключена',
            self::NeverRun => 'ещё не запускалась',
            self::Stalled => 'давно не запускалась',
            self::Failing, self::Erring => "ошибок подряд: {$source->consecutive_failures}",
            self::Ok => 'работает',
        };
    }

    public function emoji(): string
    {
        return match ($this) {
            self::Off => '⏸',
            self::NeverRun => '🕓',
            self::Stalled, self::Failing => '❌',
            self::Erring => '⚠️',
            self::Ok => '✅',
        };
    }

    /**
     * Filament color name.
     */
    public function color(): string
    {
        return match ($this) {
            self::Off, self::NeverRun => 'gray',
            self::Stalled, self::Failing => 'danger',
            self::Erring => 'warning',
            self::Ok => 'success',
        };
    }
}
