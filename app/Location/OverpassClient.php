<?php

namespace App\Location;

use Illuminate\Http\Client\Factory as Http;

/**
 * Named streets, residential complexes and suburbs around a point, from OpenStreetMap.
 */
final class OverpassClient
{
    public function __construct(
        private readonly Http $http,
    ) {}

    /**
     * @return list<array<string, mixed>> elements with tags
     */
    public function around(float $lat, float $lng, int $radiusMetres): array
    {
        $around = "around:{$radiusMetres},{$lat},{$lng}";
        // Suburb nodes sit at the suburb's centre, which may be outside a small radius
        $suburbs = 'around:'.max($radiusMetres, 2000).",{$lat},{$lng}";

        $query = <<<QL
            [out:json][timeout:25];
            (
              way({$around})["highway"]["name"];
              nwr({$around})["landuse"="residential"]["name"];
              nwr({$around})["building"="apartments"]["name"];
              node({$suburbs})["place"~"^(suburb|quarter|neighbourhood)$"]["name"];
            );
            out tags;
            QL;

        return $this->http
            // The main instance answers 406 without a User-Agent and 504 when busy.
            // Two tries of the query's own 25 s fit the hosting's 60 s, also from the admin panel.
            ->withUserAgent('laravel-rent-radar (personal flat search)')
            ->timeout(25)
            ->retry(2, 2000, throw: false)
            ->asForm()
            ->post(config('services.overpass.url'), ['data' => $query])
            ->throw()
            ->json('elements', []);
    }
}
