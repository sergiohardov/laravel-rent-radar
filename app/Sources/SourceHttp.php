<?php

namespace App\Sources;

use App\Models\Source;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\PendingRequest;
use RuntimeException;

/**
 * HTTP client for source adapters: the configured HTTP version, a timeout, and
 * the proxy when the source's "use proxy" switch is on.
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
            // OLX's CloudFront blocks by TLS fingerprint + HTTP version: PHP on
            // OpenSSL passes with HTTP/2 and gets 403 with 1.1 (docs/sources/olx.md)
            ->withOptions(['version' => (float) config('sources.http_version')]);

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
