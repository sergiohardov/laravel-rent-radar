<?php

namespace App\Sources;

use App\Models\Source;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\PendingRequest;
use RuntimeException;

/**
 * HTTP client for source adapters: HTTP/1.1, a timeout, and the proxy when the
 * source's "use proxy" switch is on.
 */
final class SourceHttp
{
    public function __construct(
        private readonly Http $http,
    ) {}

    public function for(SourceName $name): PendingRequest
    {
        $request = $this->http
            ->timeout(config('sources.http_timeout_seconds'))
            ->acceptJson()
            // OLX's CloudFront rejects HTTP/2 client fingerprints; keep every source on 1.1
            ->withOptions(['version' => 1.1]);

        if (! Source::for($name)->use_proxy) {
            return $request;
        }

        $proxy = config('sources.proxy_url');
        if (blank($proxy)) {
            throw new RuntimeException("Source [{$name->value}] uses a proxy, but SOURCES_PROXY_URL is empty.");
        }

        return $request->withOptions(['proxy' => $proxy]);
    }
}
