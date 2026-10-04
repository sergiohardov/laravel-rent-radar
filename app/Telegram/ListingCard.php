<?php

namespace App\Telegram;

use App\Currency\CurrencyCode;
use App\Enrichment\GeneratorSchedule;
use App\Models\Listing;
use App\Pipeline\Flag;

/**
 * The Telegram caption of a listing: resources/views/telegram/listing.blade.php.
 */
final class ListingCard
{
    private const CURRENCY_SIGNS = [
        'UAH' => 'грн',
        'USD' => '$',
        'EUR' => '€',
    ];

    public function caption(Listing $listing): string
    {
        $enrichment = $listing->enrichment ?? [];

        $html = view('telegram.listing', [
            'price' => $this->price($listing),
            'params' => $this->params($listing),
            'place' => $this->place($listing, $enrichment),
            'districtOnly' => $listing->hasFlag(Flag::DistrictOnly),
            'marks' => [
                '⚡ Генератор' => $this->mark($enrichment['generator'] ?? null),
                '💧 Вода при отключениях' => $this->mark($enrichment['generator_water'] ?? null),
                '🔥 Тепло при отключениях' => $this->mark($enrichment['generator_heating'] ?? null),
                '🛗 Лифт при отключениях' => $this->mark($enrichment['generator_elevator'] ?? null),
                '⏱ График генератора' => $this->schedule($enrichment['generator_schedule'] ?? null),
                '🔒 Закрытая территория' => $this->mark($enrichment['closed_territory'] ?? null),
                '👤 Собственник' => $this->mark($enrichment['is_owner'] ?? null),
            ],
            'site' => preg_replace('/^www\./', '', (string) parse_url($listing->url, PHP_URL_HOST)),
            'url' => $listing->url,
        ])->render();

        // Blade leaves blank lines around @if blocks; Telegram shows them
        return trim(preg_replace("/\n{3,}/", "\n\n", preg_replace('/^[ \t]+|[ \t]+$/m', '', $html)));
    }

    /**
     * "400 $ ≈ 18 000 грн", "15 000 грн" or null when the price is unknown.
     */
    private function price(Listing $listing): ?string
    {
        if ($listing->price === null || $listing->currency === null) {
            return null;
        }

        $original = $this->money($listing->price).' '.self::CURRENCY_SIGNS[$listing->currency->value];

        return $listing->currency === CurrencyCode::Uah || $listing->price_uah === null
            ? $original
            : $original.' ≈ '.$this->money($listing->price_uah).' грн';
    }

    /**
     * "2 комн · 4/9 эт · 52 м²"; a one-room listing that passed as a euro two-room says so.
     */
    private function params(Listing $listing): string
    {
        $floor = match (true) {
            $listing->floor !== null && $listing->floors_total !== null => "{$listing->floor}/{$listing->floors_total} эт",
            $listing->floor !== null => "{$listing->floor} эт",
            default => '? эт',
        };

        return implode(' · ', array_filter([
            $listing->hasFlag(Flag::EuroTwoRoom) ? 'евродвушка' : ($listing->rooms ?? '?').' комн',
            $floor,
            $listing->area !== null ? rtrim(rtrim(number_format($listing->area, 1, '.', ''), '0'), '.').' м²' : null,
        ]));
    }

    /**
     * The sector entry it matched, then the street and house the text names.
     *
     * @param  array<string, mixed>  $enrichment
     */
    private function place(Listing $listing, array $enrichment): ?string
    {
        $street = trim(implode(', ', array_filter([$enrichment['street'] ?? null, $enrichment['house'] ?? null])));
        $parts = array_filter([
            $enrichment['sector_match']['name'] ?? null,
            $enrichment['complex'] ?? null,
            $street !== '' ? $street : null,
        ]);

        // A matched name and the LLM's spelling of it are often the same place
        $parts = collect($parts)->unique(fn (string $part) => mb_strtolower($part))->values()->all();

        return $parts !== [] ? implode(' · ', $parts) : $listing->address;
    }

    private function schedule(?string $schedule): string
    {
        return match (GeneratorSchedule::tryFrom((string) $schedule)) {
            GeneratorSchedule::RoundTheClock => 'круглосуточно ✅',
            GeneratorSchedule::Daytime => 'весь день ✅',
            GeneratorSchedule::Scheduled => 'по графику ⚠️',
            default => '❓',
        };
    }

    private function mark(?bool $value): string
    {
        return match ($value) {
            true => '✅',
            false => '❌',
            null => '❓',
        };
    }

    private function money(int $amount): string
    {
        return number_format($amount, 0, '.', ' ');
    }
}
