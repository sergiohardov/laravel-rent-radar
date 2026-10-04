<?php

namespace App\Location;

use App\Models\Location;

/**
 * Adds streets, complexes and suburbs around a point from OpenStreetMap to
 * the sector whitelist. Existing entries keep the aliases added by hand.
 */
final class OsmLocationImporter
{
    public function __construct(
        private readonly OverpassClient $overpass,
        private readonly OsmLocationMapper $mapper,
    ) {}

    /**
     * @return array{entries: int, added: int}
     */
    public function import(float $lat, float $lng, int $radiusMetres): array
    {
        $entries = $this->mapper->map($this->overpass->around($lat, $lng, $radiusMetres));

        $added = 0;
        foreach ($entries as $entry) {
            $location = Location::query()->firstOrNew(['kind' => $entry['kind'], 'name' => $entry['name']]);
            $added += $location->exists ? 0 : 1;
            $location->aliases = array_values(array_unique([...($location->aliases ?? []), ...$entry['aliases']]));
            $location->save();
        }

        return ['entries' => count($entries), 'added' => $added];
    }
}
