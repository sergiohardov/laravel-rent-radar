<?php

namespace App\Sources;

use InvalidArgumentException;

/**
 * All registered listing sources, keyed by name.
 */
final class SourceRegistry
{
    /** @var array<string, ListingSource> */
    private array $sources = [];

    /**
     * @param  iterable<ListingSource>  $sources
     */
    public function __construct(iterable $sources)
    {
        foreach ($sources as $source) {
            $this->sources[$source->name()->value] = $source;
        }
    }

    public function get(SourceName $name): ListingSource
    {
        return $this->sources[$name->value]
            ?? throw new InvalidArgumentException("Source [{$name->value}] is not registered.");
    }

    /**
     * @return list<ListingSource>
     */
    public function all(): array
    {
        return array_values($this->sources);
    }
}
