<?php

namespace App\Pipeline\Stages;

use App\Currency\CurrencyCode;
use App\Currency\PriceConverter;
use App\Models\Listing;
use App\Models\Setting;
use App\Pipeline\EuroTwoRoomDetector;
use App\Pipeline\Flag;
use App\Pipeline\Stage;
use App\Pipeline\StageResult;

/**
 * Rooms, floor and price against Settings. An unknown value passes with a ❓ flag
 * unless its "pass when unknown" switch is off. Safe to run again after the
 * LLM filled a value: its flags are recomputed each time.
 *
 * A one-room listing when two rooms are wanted may be a euro two-room flat:
 * its text is read last, after floor and price, and a match passes with a flag.
 */
final class HardFilter implements Stage
{
    private ?Setting $settings = null;

    public function __construct(
        private readonly PriceConverter $converter,
        private readonly EuroTwoRoomDetector $euroTwoRoom,
    ) {}

    public function name(): string
    {
        return 'hard_filter';
    }

    public function handle(Listing $listing): StageResult
    {
        $settings = $this->settings ??= Setting::current();

        foreach ([Flag::UnknownRooms, Flag::UnknownFloor, Flag::UnknownPrice, Flag::EuroTwoRoom] as $flag) {
            $listing->removeFlag($flag);
        }

        $maybeEuroTwoRoom = $this->maybeEuroTwoRoom($listing, $settings);

        return ($maybeEuroTwoRoom ? null : $this->checkRange($listing, 'rooms', $listing->rooms, $settings->rooms_min, $settings->rooms_max, $settings->allow_unknown_rooms, Flag::UnknownRooms))
            ?? $this->checkRange($listing, 'floor', $listing->floor, $settings->floor_min, $settings->floor_max, $settings->allow_unknown_floor, Flag::UnknownFloor)
            ?? $this->checkPrice($listing, $settings)
            ?? ($maybeEuroTwoRoom ? $this->checkEuroTwoRoom($listing, $settings) : null)
            ?? StageResult::pass();
    }

    /**
     * One room listed, two wanted and one not: the text decides.
     */
    private function maybeEuroTwoRoom(Listing $listing, Setting $settings): bool
    {
        return $settings->allow_euro_two_room
            && $listing->rooms === 1
            && $settings->rooms_min === 2;
    }

    private function checkEuroTwoRoom(Listing $listing, Setting $settings): ?StageResult
    {
        if (! $this->euroTwoRoom->matches($listing->title, $listing->description)) {
            return StageResult::reject("rooms 1 not in {$settings->rooms_min}–{$settings->rooms_max}, no euro two-room in the text");
        }

        $listing->addFlag(Flag::EuroTwoRoom);

        return null;
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
