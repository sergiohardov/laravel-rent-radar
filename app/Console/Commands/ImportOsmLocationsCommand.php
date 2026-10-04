<?php

namespace App\Console\Commands;

use App\Location\OsmLocationMapper;
use App\Location\OverpassClient;
use App\Models\Location;
use App\Models\Setting;
use Illuminate\Console\Command;

class ImportOsmLocationsCommand extends Command
{
    protected $signature = 'locations:import-osm
        {--lat= : Sector point latitude; saved to Settings}
        {--lng= : Sector point longitude; saved to Settings}
        {--radius= : Sector radius in metres; saved to Settings}';

    protected $description = 'Add streets, complexes and suburbs around the sector point from OpenStreetMap to the whitelist';

    public function handle(OverpassClient $overpass, OsmLocationMapper $mapper): int
    {
        $settings = Setting::current();
        $settings->fill(array_filter([
            'center_lat' => $this->option('lat'),
            'center_lng' => $this->option('lng'),
            'radius_m' => $this->option('radius'),
        ], fn ($value) => $value !== null))->save();

        if (! $settings->hasSector()) {
            $this->error('Set the sector point and radius first: --lat= --lng= --radius=');

            return self::INVALID;
        }

        $entries = $mapper->map($overpass->around($settings->center_lat, $settings->center_lng, $settings->radius_m));

        $added = 0;
        foreach ($entries as $entry) {
            $location = Location::query()->firstOrNew(['kind' => $entry['kind'], 'name' => $entry['name']]);
            $added += $location->exists ? 0 : 1;
            // Keep aliases added by hand
            $location->aliases = array_values(array_unique([...($location->aliases ?? []), ...$entry['aliases']]));
            $location->save();
        }

        $this->info(sprintf(
            'OpenStreetMap around %s, %s within %d m: %d entries, %d new.',
            $settings->center_lat,
            $settings->center_lng,
            $settings->radius_m,
            count($entries),
            $added,
        ));

        return self::SUCCESS;
    }
}
