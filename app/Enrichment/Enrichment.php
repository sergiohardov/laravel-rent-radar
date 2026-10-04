<?php

namespace App\Enrichment;

use App\Currency\CurrencyCode;

/**
 * What the LLM read from a listing's text. Null means the text does not say.
 */
final readonly class Enrichment
{
    public function __construct(
        public ?string $complex = null,
        public ?string $street = null,
        public ?string $house = null,
        public ?string $district = null,
        public ?int $floor = null,
        public ?int $floorsTotal = null,
        public ?int $rooms = null,
        public ?float $area = null,
        public ?int $price = null,
        public ?CurrencyCode $currency = null,
        public ?bool $generator = null,
        public ?bool $generatorWater = null,
        public ?bool $generatorHeating = null,
        public ?bool $generatorElevator = null,
        public GeneratorSchedule $generatorSchedule = GeneratorSchedule::Unknown,
        public ?bool $closedTerritory = null,
        public ?bool $isOwner = null,
        public ?int $locationId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data  snake_case, as stored in listings.enrichment or as the
     *                                      LLM tool returns it (yes/no/unknown for flags)
     */
    public static function fromArray(array $data): self
    {
        $int = fn (string $key): ?int => is_numeric($data[$key] ?? null) ? (int) $data[$key] : null;
        $bool = fn (string $key): ?bool => match ($data[$key] ?? null) {
            true, 'yes' => true,
            false, 'no' => false,
            default => null,
        };
        $text = fn (string $key): ?string => is_string($data[$key] ?? null) && trim($data[$key]) !== '' ? trim($data[$key]) : null;

        return new self(
            complex: $text('complex'),
            street: $text('street'),
            house: $text('house'),
            district: $text('district'),
            floor: $int('floor'),
            floorsTotal: $int('floors_total'),
            rooms: $int('rooms'),
            area: is_numeric($data['area'] ?? null) ? (float) $data['area'] : null,
            price: $int('price'),
            currency: CurrencyCode::tryFrom((string) ($data['currency'] ?? '')),
            generator: $bool('generator'),
            generatorWater: $bool('generator_water'),
            generatorHeating: $bool('generator_heating'),
            generatorElevator: $bool('generator_elevator'),
            generatorSchedule: GeneratorSchedule::tryFrom((string) ($data['generator_schedule'] ?? '')) ?? GeneratorSchedule::Unknown,
            closedTerritory: $bool('closed_territory'),
            isOwner: $bool('is_owner'),
            locationId: $int('location_id'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'complex' => $this->complex,
            'street' => $this->street,
            'house' => $this->house,
            'district' => $this->district,
            'floor' => $this->floor,
            'floors_total' => $this->floorsTotal,
            'rooms' => $this->rooms,
            'area' => $this->area,
            'price' => $this->price,
            'currency' => $this->currency?->value,
            'generator' => $this->generator,
            'generator_water' => $this->generatorWater,
            'generator_heating' => $this->generatorHeating,
            'generator_elevator' => $this->generatorElevator,
            'generator_schedule' => $this->generatorSchedule->value,
            'closed_territory' => $this->closedTerritory,
            'is_owner' => $this->isOwner,
            'location_id' => $this->locationId,
        ];
    }
}
