<?php

namespace App\Sources\Olx;

use App\Currency\CurrencyCode;
use App\Sources\RawListing;
use App\Sources\SourceName;
use Carbon\CarbonImmutable;

/**
 * One offer of olx.ua /api/v1/offers → RawListing. Field notes: docs/sources/olx.md.
 */
final class OlxListingMapper
{
    /** Map points blurred wider than this, in km, are a district centre rather than the flat */
    private const MAX_MAP_RADIUS_KM = 1;

    /**
     * @param  array<string, mixed>  $offer
     */
    public function map(array $offer): RawListing
    {
        $params = collect($offer['params'] ?? [])->keyBy('key')->map(fn (array $param) => $param['value'] ?? null);
        $price = $params->get('price');
        $map = $offer['map'] ?? [];
        $preciseMap = isset($map['lat'], $map['lon']) && ($map['radius'] ?? PHP_INT_MAX) <= self::MAX_MAP_RADIUS_KM;

        return new RawListing(
            source: SourceName::Olx,
            externalId: (string) $offer['id'],
            url: $offer['url'],
            raw: $offer,
            title: $this->text($offer['title'] ?? null),
            description: $this->plainText($offer['description'] ?? null),
            address: $this->text($offer['location']['district']['name'] ?? null),
            price: isset($price['value']) ? (int) round((float) $price['value']) : null,
            currency: CurrencyCode::tryFrom((string) ($price['currency'] ?? '')),
            rooms: $this->rooms($params->get('number_of_rooms_string')),
            floor: $this->int($params->get('floor')['key'] ?? null),
            floorsTotal: $this->int($params->get('total_floors')['key'] ?? null),
            area: is_numeric($params->get('total_area')['key'] ?? null) ? (float) $params->get('total_area')['key'] : null,
            lat: $preciseMap ? (float) $map['lat'] : null,
            lng: $preciseMap ? (float) $map['lon'] : null,
            // Kyiv time with its offset, e.g. 2026-10-03T23:48:57+03:00
            publishedAt: isset($offer['created_time']) ? CarbonImmutable::parse($offer['created_time']) : null,
        );
    }

    /**
     * "2 кімнати" → 2. The key (dvuhkomnatnye…) carries no number, the label does.
     *
     * @param  array<string, mixed>|null  $value
     */
    private function rooms(?array $value): ?int
    {
        return preg_match('/\d+/', (string) ($value['label'] ?? ''), $match) ? (int) $match[0] : null;
    }

    /**
     * OLX descriptions are HTML with <br /> line breaks.
     */
    private function plainText(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $text = html_entity_decode(strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $html)), ENT_QUOTES | ENT_HTML5);
        $text = preg_replace("/\n\s*\n+/", "\n\n", str_replace("\r", '', $text));

        return $this->text(preg_replace("/[ \t]*\n[ \t]*/", "\n", $text));
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
