<?php

namespace App\Filament\Widgets;

use App\Pipeline\PipelineStats;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * How many of the listings found in the last 24 hours got past each step.
 */
class Funnel extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Воронка за 24 часа';

    protected ?string $pollingInterval = '60s';

    protected int|array|null $columns = 5;

    /**
     * @return list<Stat>
     */
    protected function getStats(): array
    {
        $funnel = app(PipelineStats::class)->funnel(now()->subDay());

        return [
            Stat::make('Найдено', $funnel['found'])->description('новые объявления на площадках'),
            Stat::make('Прошли фильтры', $funnel['passed_fields'])->description('комнаты, этаж, цена'),
            Stat::make('Прочитал LLM', $funnel['enriched'])->description('генератор, адрес, флаги'),
            Stat::make('В секторе', $funnel['in_sector'])->description('прошли все проверки'),
            Stat::make('Отправлено', $funnel['sent'])->description('карточки в группе')->color($funnel['sent'] > 0 ? 'success' : 'gray'),
        ];
    }
}
