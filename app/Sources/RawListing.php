<?php

namespace App\Sources;

use App\Currency\CurrencyCode;
use App\Pipeline\Decision;
use Carbon\CarbonImmutable;

/**
 * A listing as a source adapter returns it, before any pipeline stage.
 * Null means the source does not say.
 */
final readonly class RawListing
{
    /**
     * @param  array<string, mixed>  $raw  the source's own payload, kept for the journal
     */
    public function __construct(
        public SourceName $source,
        public string $externalId,
        public string $url,
        public array $raw,
        public ?string $title = null,
        public ?string $description = null,
        public ?string $address = null,
        public ?int $price = null,
        public ?CurrencyCode $currency = null,
        public ?int $rooms = null,
        public ?int $floor = null,
        public ?int $floorsTotal = null,
        public ?float $area = null,
        public ?float $lat = null,
        public ?float $lng = null,
        public ?CarbonImmutable $publishedAt = null,
    ) {}

    /**
     * Attributes of a new, not yet processed Listing row.
     *
     * @return array<string, mixed>
     */
    public function toListingAttributes(): array
    {
        return [
            'source' => $this->source,
            'external_id' => $this->externalId,
            'url' => $this->url,
            'title' => $this->title,
            'description' => $this->description,
            'address' => $this->address,
            'raw' => $this->raw,
            'price' => $this->price,
            'currency' => $this->currency,
            'rooms' => $this->rooms,
            'floor' => $this->floor,
            'floors_total' => $this->floorsTotal,
            'area' => $this->area,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'published_at' => $this->publishedAt,
            'decision' => Decision::Pending,
        ];
    }
}
