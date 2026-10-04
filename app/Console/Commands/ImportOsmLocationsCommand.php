<?php

namespace App\Console\Commands;

use App\Location\OsmLocationImporter;
use App\Models\Setting;
use Illuminate\Console\Command;

class ImportOsmLocationsCommand extends Command
{
    protected $signature = 'locations:import-osm
        {--lat= : Sector point latitude; saved to Settings}
        {--lng= : Sector point longitude; saved to Settings}
        {--radius= : Sector radius in metres; saved to Settings}';

    protected $description = 'Add streets, complexes and suburbs around the sector point from OpenStreetMap to the whitelist';

    public function handle(OsmLocationImporter $importer): int
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

        $result = $importer->import($settings->center_lat, $settings->center_lng, $settings->radius_m);

        $this->info(sprintf(
            'OpenStreetMap around %s, %s within %d m: %d entries, %d new.',
            $settings->center_lat,
            $settings->center_lng,
            $settings->radius_m,
            $result['entries'],
            $result['added'],
        ));

        return self::SUCCESS;
    }
}
