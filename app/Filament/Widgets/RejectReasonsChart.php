<?php

namespace App\Filament\Widgets;

use App\Pipeline\PipelineStats;
use Filament\Widgets\ChartWidget;

/**
 * Why listings were rejected in the last 7 days: shows which filter to loosen
 * when few flats come.
 */
class RejectReasonsChart extends ChartWidget
{
    protected static ?int $sort = 6;

    protected ?string $heading = 'Причины отсева за 7 дней';

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '280px';

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $reasons = app(PipelineStats::class)->rejectReasons(now()->subDays(7));

        return [
            'datasets' => [[
                'label' => 'Объявлений',
                'data' => array_values($reasons),
                'backgroundColor' => '#f59e0b',
                'borderColor' => '#f59e0b',
            ]],
            'labels' => array_keys($reasons),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => ['legend' => ['display' => false]],
        ];
    }
}
