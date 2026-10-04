<?php

namespace App\Sources\DimRia;

use App\Sources\ListingSource;
use App\Sources\SourceHttp;
use App\Sources\SourceName;
use App\Sources\UnexpectedResponse;

/**
 * DIM.RIA through the site's own endpoints: the search gives ids, newest
 * first, and each listing is a separate card request. See docs/sources/dimria.md.
 */
final class DimRiaSource implements ListingSource
{
    private const SEARCH_URL = 'https://dom.ria.com/node/searchEngine/v2/';

    private const CARD_URL = 'https://dom.ria.com/realty/data/';

    private const PAGE_SIZE = 20;

    private const QUERY = [
        'category' => 1,    // flats
        'realty_type' => 2,
        'operation' => 3,   // long-term rent
        'state_id' => 12,   // Одеська область
        'city_id' => 12,    // Одеса
        'sort' => 'created_at',
    ];

    public function __construct(
        private readonly SourceHttp $http,
        private readonly DimRiaListingMapper $mapper,
    ) {}

    public function name(): SourceName
    {
        return SourceName::DimRia;
    }

    public function fetch(): iterable
    {
        $maxPages = config('sources.dimria.max_pages');

        for ($page = 0; $page < $maxPages; $page++) {
            $response = $this->http->for($this->name())
                ->get(self::SEARCH_URL, self::QUERY + ['page' => $page, 'limit' => self::PAGE_SIZE])
                ->throw();

            $ids = $response->json('items');
            if (! is_array($ids)) {
                throw UnexpectedResponse::from($this->name(), $response);
            }

            foreach ($ids as $id) {
                $card = $this->card((int) $id);
                if ($card !== null) {
                    yield $this->mapper->map($card);
                }
            }

            if (count($ids) < self::PAGE_SIZE) {
                return;
            }
        }
    }

    /**
     * @return array<string, mixed>|null null when the listing was removed after the search
     */
    private function card(int $id): ?array
    {
        $response = $this->http->for($this->name())->get(self::CARD_URL.$id, ['lang_id' => 4]);

        if ($response->notFound()) {
            return null;
        }

        $card = $response->throw()->json();
        if (! is_array($card) || ! isset($card['realty_id'])) {
            throw UnexpectedResponse::from($this->name(), $response);
        }

        return $card;
    }
}
