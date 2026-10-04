<?php

namespace App\Sources\Olx;

use App\Sources\ListingSource;
use App\Sources\SourceHttp;
use App\Sources\SourceName;
use App\Sources\UnexpectedResponse;

/**
 * OLX: long-term rent of flats in Odesa, newest first. See docs/sources/olx.md.
 */
final class OlxSource implements ListingSource
{
    private const URL = 'https://www.olx.ua/api/v1/offers/';

    /** The API refuses a larger page */
    private const PAGE_SIZE = 50;

    private const QUERY = [
        'category_id' => 1760, // long-term rent of flats
        'region_id' => 9,      // Одеська область
        'city_id' => 62,       // Одеса
        'sort_by' => 'created_at:desc',
        'currency' => 'UAH',
    ];

    public function __construct(
        private readonly SourceHttp $http,
        private readonly OlxListingMapper $mapper,
    ) {}

    public function name(): SourceName
    {
        return SourceName::Olx;
    }

    public function fetch(): iterable
    {
        $maxPages = config('sources.olx.max_pages');

        for ($page = 0; $page < $maxPages; $page++) {
            $response = $this->http->for($this->name())
                ->get(self::URL, self::QUERY + ['offset' => $page * self::PAGE_SIZE, 'limit' => self::PAGE_SIZE])
                ->throw();

            $offers = $response->json('data');
            if (! is_array($offers)) {
                throw UnexpectedResponse::from($this->name(), $response);
            }

            // Paid promotions sit at fixed positions on top of the limit; they are
            // old listings, so they would break the "newest first" order
            $promoted = array_flip($response->json('metadata.source.promoted') ?? []);

            foreach ($offers as $index => $offer) {
                if (! isset($promoted[$index])) {
                    yield $this->mapper->map($offer);
                }
            }

            if ($offers === [] || $response->json('links.next') === null) {
                return;
            }
        }
    }
}
