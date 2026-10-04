<?php

namespace App\Sources;

use App\Models\Source;
use GuzzleHttp\Psr7\Uri;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\PendingRequest;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

/**
 * HTTP client for source adapters: the configured HTTP version, a timeout, and
 * the proxy when the source's "use proxy" switch is on.
 *
 * SOURCES_PROXY_URL is either a proxy (http://login:password@host:port) or the
 * Scrape.do API (https://api.scrape.do/?token=…&geoCode=ua): the hosting lets
 * out only ports 80 and 443, so the request goes to the API with the target in
 * its url parameter.
 */
final class SourceHttp
{
    private const SCRAPE_DO_API = 'https://api.scrape.do/';

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

        if (str_starts_with($proxy, self::SCRAPE_DO_API)) {
            return $request->withRequestMiddleware(fn (RequestInterface $target) => $target->withUri(
                new Uri($proxy.'&url='.rawurlencode((string) $target->getUri())),
            ));
        }

        return $request->withOptions([
            'proxy' => $proxy,
            'verify' => (bool) config('sources.proxy_verify_tls'),
        ]);
    }

    /**
     * Hides proxy credentials and API tokens in an error text before it is
     *
     * stored or sent: "…?token=***&…", "http://***@proxy…".
     */
    public static function redact(string $message): string
    {
        return preg_replace(
            ['/([?&]token=)[^&\s"\']+/i', '#//[^/@\s"\']+@#'],
            ['$1***', '//***@'],
            $message,
        );
    }
}
