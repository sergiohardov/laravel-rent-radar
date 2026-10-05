<?php

namespace App\Filament\Widgets;

use App\Currency\CurrencyCode;
use App\Currency\ExchangeRates;
use App\Models\Setting;
use App\Telegram\QuietHours;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

/**
 * Housekeeping: the card queue, failed jobs, today's NBU rates and quiet hours.
 */
class Service extends StatsOverviewWidget
{
    protected static ?int $sort = 5;

    protected ?string $heading = 'Служебное';

    protected ?string $pollingInterval = '60s';

    protected int|array|null $columns = 4;

    /**
     * @return list<Stat>
     */
    protected function getStats(): array
    {
        $queued = DB::table('jobs')->count();
        $failed = DB::table('failed_jobs')->count();
        $rates = app(ExchangeRates::class);
        $settings = Setting::current();

        return [
            Stat::make('В очереди', $queued)
                ->description('карточки и алерты ждут отправки')
                ->color($queued > 20 ? 'warning' : 'gray'),
            Stat::make('Упавшие задачи', $failed)
                ->description('failed_jobs')
                ->color($failed > 0 ? 'danger' : 'success'),
            Stat::make('Курс НБУ', self::rate($rates->uahPer(CurrencyCode::Usd), '$'))
                ->description(self::rate($rates->uahPer(CurrencyCode::Eur), '€')),
            Stat::make('Тихие часы', match (true) {
                ! $settings->quiet_hours_enabled => 'выключены',
                app(QuietHours::class)->isNow() => 'сейчас без звука',
                default => 'сейчас со звуком',
            })->description($settings->quiet_hours_enabled
                ? substr((string) $settings->quiet_from, 0, 5).'–'.substr((string) $settings->quiet_to, 0, 5).' по Киеву'
                : 'включаются в «Настройках»'),
        ];
    }

    private static function rate(?float $uah, string $sign): string
    {
        return $uah === null ? "{$sign} — нет курса" : '1 '.$sign.' = '.number_format($uah, 2, ',', ' ').' грн';
    }
}
