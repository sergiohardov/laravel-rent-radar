<?php

namespace App\Sources\Lun;

use App\Sources\ListingSource;
use App\Sources\SourceHttp;
use App\Sources\SourceName;
use App\Sources\UnexpectedResponse;

/**
 * ЛУН (flatfy.ua): long-term rent in Odesa, newest first. See docs/sources/lun.md.
 */
final class LunSource implements ListingSource
{
    private const URL = 'https://flatfy.ua/api/realties';

    private const QUERY = [
        'geo_id' => 10016589, // Одеса
        'section_id' => 2,    // long-term rent
        'lang' => 'uk',
        'sort' => 'insert_time',
    ];

    public function __construct(
        private readonly SourceHttp $http,
        private readonly LunListingMapper $mapper,
    ) {}

    public function name(): SourceName
    {
        return SourceName::Lun;
    }

    public function fetch(): iterable
    {
        $maxPages = config('sources.lun.max_pages');

        for ($page = 1; $page <= $maxPages; $page++) {
            $response = $this->http->for($this->name())
                ->get(self::URL, self::QUERY + ['page' => $page])
                ->throw();

            // A Cloudflare challenge comes back as HTML; never read it as "no listings"
            $items = $response->json('data');
            if (! is_array($items)) {
                throw UnexpectedResponse::from($this->name(), $response);
            }

            if ($items === []) {
                return;
            }

            foreach ($items as $item) {
                yield $this->mapper->map($item);
            }
        }
    }
}
