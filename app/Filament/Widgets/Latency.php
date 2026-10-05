<?php

namespace App\Filament\Widgets;

use App\Pipeline\PipelineStats;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * How fast the bot is: the goal is a card within about ten minutes of publishing.
 */
class Latency extends StatsOverviewWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Скорость';

    protected ?string $pollingInterval = '60s';

    protected int|array|null $columns = 2;

    /**
     * @return list<Stat>
     */
    protected function getStats(): array
    {
        $stats = app(PipelineStats::class);
        $detection = $stats->medianDetectionMinutes(now()->subDay());
        $card = $stats->medianCardMinutes(now()->subDays(30));

        return [
            Stat::make('От публикации до бота', self::minutes($detection))
                ->description('медиана по опубликованным за 24 часа')
                ->color(self::color($detection)),
            Stat::make('От публикации до карточки', self::minutes($card))
                ->description('медиана за 30 дней, по отправленным')
                ->color(self::color($card)),
        ];
    }

    private static function minutes(?float $minutes): string
    {
        return match (true) {
            $minutes === null => 'нет данных',
            $minutes < 90 => rtrim(rtrim(number_format($minutes, 1, ',', ' '), '0'), ',').' мин',
            default => rtrim(rtrim(number_format($minutes / 60, 1, ',', ' '), '0'), ',').' ч',
        };
    }

    private static function color(?float $minutes): string
    {
        return match (true) {
            $minutes === null => 'gray',
            $minutes <= 15 => 'success',
            $minutes <= 60 => 'warning',
            default => 'danger',
        };
    }
}
