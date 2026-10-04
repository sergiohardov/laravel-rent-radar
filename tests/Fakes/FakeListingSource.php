<?php

namespace Tests\Fakes;

use App\Sources\ListingSource;
use App\Sources\RawListing;
use App\Sources\SourceName;

/**
 * In-memory source for pipeline tests; remembers how many listings were pulled from it.
 */
final class FakeListingSource implements ListingSource
{
    public int $pulled = 0;

    /**
     * @param  list<RawListing>  $listings  newest first
     */
    public function __construct(
        private readonly SourceName $name = SourceName::Lun,
        private readonly array $listings = [],
    ) {}

    public function name(): SourceName
    {
        return $this->name;
    }

    public function fetch(): iterable
    {
        foreach ($this->listings as $listing) {
            $this->pulled++;

            yield $listing;
        }
    }

    /**
     * Listings with ids from $first down to $first - $count + 1, newest first.
     */
    public static function withIds(int $first, int $count, SourceName $name = SourceName::Lun): self
    {
        $listings = [];
        for ($id = $first; $id > $first - $count; $id--) {
            $listings[] = new RawListing(
                source: $name,
                externalId: (string) $id,
                url: "https://example.test/listing/{$id}",
                raw: ['id' => $id],
                price: 12000,
                rooms: 2,
                floor: 3,
            );
        }

        return new self($name, $listings);
    }
}
