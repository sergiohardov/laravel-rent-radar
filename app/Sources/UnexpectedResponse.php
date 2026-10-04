<?php

namespace App\Sources;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * A source answered 2xx, but not in the expected format: a bot challenge or a changed API.
 */
final class UnexpectedResponse extends RuntimeException
{
    public static function from(SourceName $source, Response $response): self
    {
        $head = Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($response->body()))), 120);

        return new self("Source [{$source->value}] returned an unexpected response ({$response->status()}): {$head}");
    }
}
