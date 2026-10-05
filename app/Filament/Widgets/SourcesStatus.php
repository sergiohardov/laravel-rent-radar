<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Sources\SourceResource;
use App\Models\Source;
use App\Sources\SourceName;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * One tile per listing source: works, fails, is switched off, or has not run
 * for a while (the cron may be down).
 */
class SourcesStatus extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'Площадки';

    protected ?string $pollingInterval = '60s';

    /**
     * @return list<Stat>
     */
    protected function getStats(): array
    {
        return array_map(fn (SourceName $name) => $this->tile(Source::for($name)), SourceName::cases());
    }

    private function tile(Source $source): Stat
    {
        $lastRun = $source->last_run_at?->setTimezone('Europe/Kyiv')->format('d.m H:i');
        $summary = SourceResource::reportSummary($source->last_report);
        $failures = $source->consecutive_failures;
        // Three missed windows in a row mean the scheduler itself stopped
        $stale = $source->last_run_at !== null
            && $source->last_run_at->lt(now()->subMinutes($source->interval_max_minutes * 3));

        [$value, $color, $icon] = match (true) {
            ! $source->enabled => ['выключена', 'gray', Heroicon::OutlinedPauseCircle],
            $source->last_run_at === null => ['ещё не запускалась', 'gray', Heroicon::OutlinedClock],
            $stale => ['давно не запускалась', 'danger', Heroicon::OutlinedExclamationTriangle],
            $failures >= (int) config('sources.alert_after_failures') => ["ошибок подряд: {$failures}", 'danger', Heroicon::OutlinedXCircle],
            $failures > 0 => ["ошибок подряд: {$failures}", 'warning', Heroicon::OutlinedExclamationCircle],
            default => ['работает', 'success', Heroicon::OutlinedCheckCircle],
        };

        return Stat::make($source->name->getLabel(), $value)
            ->color($color)
            ->descriptionIcon($icon)
            ->description(implode(' · ', array_filter([$lastRun ? "запуск {$lastRun}" : null, $summary])));
    }
}
