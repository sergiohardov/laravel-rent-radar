<?php

namespace App\Sources\Lun;

use App\Currency\CurrencyCode;
use App\Sources\RawListing;
use App\Sources\SourceName;
use Carbon\CarbonImmutable;

/**
 * One item of flatfy.ua /api/realties → RawListing. Field notes: docs/sources/lun.md.
 */
final class LunListingMapper
{
    /** geo_entities types that make up the address line, in display order */
    private const ADDRESS_TYPES = ['street', 'residential_complex', 'microdistrict', 'district'];

    /**
     * @param  array<string, mixed>  $item
     */
    public function map(array $item): RawListing
    {
        $location = is_array($item['location'] ?? null) ? $item['location'] : null;

        return new RawListing(
            source: SourceName::Lun,
            externalId: (string) $item['id'],
            // url_raw points to the original site (OLX, rieltor.ua, DOM.RIA)
            url: $item['url_raw'] ?? "https://lun.ua/uk/realty/{$item['id']}",
            raw: $item,
            title: $this->text($item['header'] ?? null),
            description: $this->text($item['text'] ?? null),
            address: $this->address($item['geo_entities'] ?? []),
            price: isset($item['price']) ? (int) round((float) $item['price']) : null,
            currency: CurrencyCode::tryFrom((string) ($item['currency'] ?? '')),
            rooms: $this->int($item['room_count'] ?? null),
            floor: $this->int($item['floor'] ?? null),
            floorsTotal: $this->int($item['floor_count'] ?? null),
            area: isset($item['area_total']) ? (float) $item['area_total'] : null,
            // LUN gives [lon, lat]
            lat: isset($location[1]) ? (float) $location[1] : null,
            lng: isset($location[0]) ? (float) $location[0] : null,
            publishedAt: isset($item['insert_time']) ? CarbonImmutable::parse($item['insert_time']) : null,
        );
    }

    /**
     * "вулиця Новосельського, 90 · Центр · Приморський"
     *
     * @param  list<array<string, mixed>>  $entities
     */
    private function address(array $entities): ?string
    {
        $names = collect($entities)->groupBy('type')->map(fn ($group) => $group->first()['name'] ?? null);

        $parts = [];
        foreach (self::ADDRESS_TYPES as $type) {
            $name = $names->get($type);
            if (blank($name)) {
                continue;
            }

            if ($type === 'street' && filled($names->get('house'))) {
                $name .= ', '.$names->get('house');
            }

            $parts[] = $name;
        }

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
