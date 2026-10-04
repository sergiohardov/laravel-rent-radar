<?php

namespace App\Sources\DimRia;

use App\Currency\CurrencyCode;
use App\Sources\RawListing;
use App\Sources\SourceName;
use Carbon\CarbonImmutable;

/**
 * dom.ria.com /realty/data/{id} → RawListing. Field notes: docs/sources/dimria.md.
 */
final class DimRiaListingMapper
{
    private const CURRENCIES = [
        'грн' => CurrencyCode::Uah,
        '$' => CurrencyCode::Usd,
        '€' => CurrencyCode::Eur,
    ];

    /**
     * @param  array<string, mixed>  $card
     */
    public function map(array $card): RawListing
    {
        return new RawListing(
            source: SourceName::DimRia,
            externalId: (string) $card['realty_id'],
            url: 'https://dom.ria.com/uk/'.$card['beautiful_url'],
            raw: $card,
            // DIM.RIA has no title; the description comes in Ukrainian and Russian
            description: $this->text($card['description_uk'] ?? null) ?? $this->text($card['description'] ?? null),
            address: $this->address($card),
            price: is_numeric($card['price'] ?? null) ? (int) $card['price'] : null,
            currency: self::CURRENCIES[$card['currency_type'] ?? ''] ?? null,
            rooms: $this->int($card['rooms_count'] ?? null),
            floor: $this->int($card['floor'] ?? null),
            floorsTotal: $this->int($card['floors_count'] ?? null),
            area: is_numeric($card['total_square_meters'] ?? null) ? (float) $card['total_square_meters'] : null,
            lat: is_numeric($card['latitude'] ?? null) ? (float) $card['latitude'] : null,
            lng: is_numeric($card['longitude'] ?? null) ? (float) $card['longitude'] : null,
            // Kyiv time without an offset, e.g. 2026-10-03 23:55:16
            publishedAt: isset($card['publishing_date'])
                ? CarbonImmutable::createFromFormat('Y-m-d H:i:s', $card['publishing_date'], 'Europe/Kyiv')
                : null,
        );
    }

    /**
     * "вул. Генуезька, 1Ж корпус 1 · Аркадія"
     *
     * @param  array<string, mixed>  $card
     */
    private function address(array $card): ?string
    {
        $street = $this->text($card['street_name_uk'] ?? null);
        $house = $this->text($card['building_number_str'] ?? null);

        $parts = array_filter([
            $street !== null && $house !== null ? "{$street}, {$house}" : $street,
            $this->text($card['district_name_uk'] ?? null),
        ]);

        return $parts === [] ? null : implode(' · ', $parts);
    }

    private function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function int(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
