<?php

namespace App\Pipeline\Stages;

use App\Currency\CurrencyCode;
use App\Currency\PriceConverter;
use App\Models\Listing;
use App\Models\Setting;
use App\Pipeline\Flag;
use App\Pipeline\Stage;
use App\Pipeline\StageResult;

/**
 * Rooms, floor and price against Settings. An unknown value passes with a ❓ flag
 * unless its "pass when unknown" switch is off.
 */
final class HardFilter implements Stage
{
    private ?Setting $settings = null;

    public function __construct(
        private readonly PriceConverter $converter,
    ) {}

    public function name(): string
    {
        return 'hard_filter';
    }

    public function handle(Listing $listing): StageResult
    {
        $settings = $this->settings ??= Setting::current();

        return $this->checkRange($listing, 'rooms', $listing->rooms, $settings->rooms_min, $settings->rooms_max, $settings->allow_unknown_rooms, Flag::UnknownRooms)
            ?? $this->checkRange($listing, 'floor', $listing->floor, $settings->floor_min, $settings->floor_max, $settings->allow_unknown_floor, Flag::UnknownFloor)
            ?? $this->checkPrice($listing, $settings)
            ?? StageResult::pass();
    }

    private function checkRange(Listing $listing, string $field, ?int $value, int $min, int $max, bool $allowUnknown, Flag $unknownFlag): ?StageResult
    {
        if ($value === null) {
            return $this->unknown($listing, $field, $allowUnknown, $unknownFlag);
        }

        return $value < $min || $value > $max
            ? StageResult::reject("{$field} {$value} not in {$min}–{$max}")
            : null;
    }

    private function checkPrice(Listing $listing, Setting $settings): ?StageResult
    {
        $listing->price_uah = $listing->price !== null && $listing->currency !== null
            ? $this->converter->toUah($listing->price, $listing->currency)
            : null;

        if ($listing->price_uah === null) {
            return $this->unknown($listing, 'price', $settings->allow_unknown_price, Flag::UnknownPrice);
        }

        if ($listing->price_uah <= $settings->max_price_uah) {
            return null;
        }

        $original = $listing->currency === CurrencyCode::Uah ? '' : $this->money($listing->price).' '.$listing->currency->value.' = ';

        return StageResult::reject(sprintf(
            'price %s%s UAH > %s UAH',
            $original,
            $this->money($listing->price_uah),
            $this->money($settings->max_price_uah),
        ));
    }

    private function unknown(Listing $listing, string $field, bool $allowUnknown, Flag $flag): ?StageResult
    {
        if (! $allowUnknown) {
            return StageResult::reject("{$field} unknown");
        }

        $listing->addFlag($flag);

        return null;
    }

    private function money(int $amount): string
    {
        return number_format($amount, 0, '.', ' ');
    }
}
