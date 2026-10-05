<?php

namespace App\Filament\Widgets;

use App\Models\Listing;
use App\Pipeline\Decision;
use App\Pipeline\PipelineStats;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * LLM calls and their estimated cost (services.anthropic.cost_per_call_usd).
 */
class LlmUsage extends StatsOverviewWidget
{
    protected static ?int $sort = 4;

    protected ?string $heading = 'Расход LLM';

    protected ?string $pollingInterval = '60s';

    protected int|array|null $columns = 3;

    /**
     * @return list<Stat>
     */
    protected function getStats(): array
    {
        $stats = app(PipelineStats::class);
        $today = $stats->llmCalls(now('Europe/Kyiv')->startOfDay());
        $month = $stats->llmCalls(now()->subDays(30));
        $failed = Listing::query()->where('created_at', '>=', now()->subDay())->where('decision', Decision::Failed)->count();

        return [
            Stat::make('Сегодня', $today.' '.self::calls($today))->description('≈ '.self::usd($today).', с полуночи по Киеву'),
            Stat::make('За 30 дней', $month.' '.self::calls($month))->description('≈ '.self::usd($month)),
            Stat::make('Сбои LLM за сутки', $failed)
                ->description('повторяются раз в час')
                ->color($failed > 0 ? 'warning' : 'success'),
        ];
    }

    private static function usd(int $calls): string
    {
        return '$'.number_format($calls * (float) config('services.anthropic.cost_per_call_usd'), 2, '.', ' ');
    }

    private static function calls(int $count): string
    {
        $mod10 = $count % 10;
        $mod100 = $count % 100;

        return match (true) {
            $mod10 === 1 && $mod100 !== 11 => 'вызов',
            $mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14) => 'вызова',
            default => 'вызовов',
        };
    }
}
