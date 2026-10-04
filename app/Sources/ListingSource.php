<?php

namespace App\Sources;

/**
 * A listing site. Each site is one class; adding a site does not touch the pipeline.
 */
interface ListingSource
{
    public function name(): SourceName;

    /**
     * Listings newest first, fetched lazily page by page: the pipeline stops
     * iterating once it meets enough already seen listings or runs out of time,
     * and the remaining pages are never requested.
     *
     * @return iterable<RawListing>
     */
    public function fetch(): iterable;
}
