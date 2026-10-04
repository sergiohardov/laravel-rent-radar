<?php

namespace Tests\Fakes;

use App\Sources\ListingSource;
use App\Sources\SourceName;
use RuntimeException;

final class FailingListingSource implements ListingSource
{
    public function __construct(
        private readonly SourceName $name = SourceName::Lun,
        private readonly string $message = 'HTTP request returned status code 403',
    ) {}

    public function name(): SourceName
    {
        return $this->name;
    }

    public function fetch(): iterable
    {
        throw new RuntimeException($this->message);
    }
}
