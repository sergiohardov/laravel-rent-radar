<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Sources\SourceResource;
use App\Models\Source;
use App\Sources\SourceName;
use App\Sources\SourceState;
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
        $state = SourceState::of($source);
        $lastRun = $source->last_run_at?->setTimezone('Europe/Kyiv')->format('d.m H:i');

        return Stat::make($source->name->getLabel(), $state->label($source))
            ->color($state->color())
            ->descriptionIcon(match ($state) {
                SourceState::Off => Heroicon::OutlinedPauseCircle,
                SourceState::NeverRun => Heroicon::OutlinedClock,
                SourceState::Stalled => Heroicon::OutlinedExclamationTriangle,
                SourceState::Failing => Heroicon::OutlinedXCircle,
                SourceState::Erring => Heroicon::OutlinedExclamationCircle,
                SourceState::Ok => Heroicon::OutlinedCheckCircle,
            })
            ->description(implode(' · ', array_filter([
                $lastRun ? "запуск {$lastRun}" : null,
                SourceResource::reportSummary($source->last_report),
            ])));
    }
}
