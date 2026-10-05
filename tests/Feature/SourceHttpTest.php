<?php

use App\Models\Source;
use App\Sources\SourceHttp;
use App\Sources\SourceName;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Guzzle options of the sent request, plus its HTTP protocol version.
 */
function sentOptions(SourceName $name): array
{
    $options = [];
    Http::fake(function (Request $request, array $sent) use (&$options) {
        $options = $sent + ['protocol' => $request->toPsrRequest()->getProtocolVersion()];

        return Http::response(['data' => []]);
    });

    app(SourceHttp::class)->for($name)->get('https://flatfy.ua/api/realties');

    return $options;
}

it('goes direct over HTTP/2 with a timeout by default', function () {
    $options = sentOptions(SourceName::Lun);

    expect($options['protocol'])->toBe('2.0')
        ->and($options['timeout'])->toBe(15)
        ->and($options)->not->toHaveKey('proxy');
});

it('uses the configured HTTP version', function () {
    config(['sources.http_version' => '1.1']);

    expect(sentOptions(SourceName::Olx)['protocol'])->toBe('1.1');
});

it('goes through the proxy when the source switch is on', function () {
    config(['sources.proxy_url' => 'http://203.0.113.10:8080']);
    Source::for(SourceName::Olx)->update(['use_proxy' => true]);

    $options = sentOptions(SourceName::Olx);
    expect($options['proxy'])->toBe('http://203.0.113.10:8080')
        ->and($options['verify'])->toBeTrue();
});

it('skips the certificate check through a proxy that re-signs HTTPS', function () {
    config(['sources.proxy_url' => 'http://token:geoCode=ua@proxy.scrape.do:8080', 'sources.proxy_verify_tls' => false]);
    Source::for(SourceName::Lun)->update(['use_proxy' => true]);

    expect(sentOptions(SourceName::Lun)['verify'])->toBeFalse();
});

it('keeps the certificate check on direct requests', function () {
    config(['sources.proxy_url' => 'http://token:geoCode=ua@proxy.scrape.do:8080', 'sources.proxy_verify_tls' => false]);

    expect(sentOptions(SourceName::DimRia))->not->toHaveKey('proxy')
        ->and(sentOptions(SourceName::DimRia)['verify'] ?? true)->toBeTrue();
});

it('fails loudly when the switch is on but no proxy is configured', function () {
    config(['sources.proxy_url' => null]);
    Source::for(SourceName::Lun)->update(['use_proxy' => true]);

    app(SourceHttp::class)->for(SourceName::Lun);
})->throws(RuntimeException::class, 'Source [lun] uses a proxy, but SOURCES_PROXY_URL is empty.');

it('sends the request through the Scrape.do API when the proxy is its URL', function () {
    config(['sources.proxy_url' => 'https://api.scrape.do/?token=t0k3n&geoCode=ua']);
    Source::for(SourceName::Lun)->update(['use_proxy' => true]);
    $sent = null;
    Http::fake(function (Request $request, array $options) use (&$sent) {
        $sent = ['url' => $request->url(), 'proxy' => $options['proxy'] ?? null];

        return Http::response(['data' => []]);
    });

    app(SourceHttp::class)->for(SourceName::Lun)->get('https://flatfy.ua/api/realties', ['geo_id' => 10016589, 'page' => 2]);

    expect($sent['url'])->toBe('https://api.scrape.do/?token=t0k3n&geoCode=ua&url='.rawurlencode('https://flatfy.ua/api/realties?geo_id=10016589&page=2'))
        ->and($sent['proxy'])->toBeNull();
});

it('hides tokens and proxy credentials in error texts', function (string $message, string $redacted) {
    expect(SourceHttp::redact($message))->toBe($redacted);
})->with([
    'api token' => [
        'ConnectionException: cURL error 28 for https://api.scrape.do/?token=t0k3n&geoCode=ua&url=https%3A%2F%2Fflatfy.ua',
        'ConnectionException: cURL error 28 for https://api.scrape.do/?token=***&geoCode=ua&url=https%3A%2F%2Fflatfy.ua',
    ],
    'proxy credentials' => [
        'cURL error 7: Failed to connect via http://t0k3n:geoCode=ua@proxy.scrape.do:8080',
        'cURL error 7: Failed to connect via http://***@proxy.scrape.do:8080',
    ],
    'nothing secret' => ['RequestException: HTTP request returned status code 403', 'RequestException: HTTP request returned status code 403'],
]);

it('sends the request through our relay with the target in its url parameter', function () {
    config(['sources.proxy_url' => 'https://relay.creastep.com/relay.php?key=s3cret']);
    Source::for(SourceName::Olx)->update(['use_proxy' => true]);
    $sent = null;
    Http::fake(function (Request $request, array $options) use (&$sent) {
        $sent = ['url' => $request->url(), 'proxy' => $options['proxy'] ?? null];

        return Http::response(['data' => []]);
    });

    app(SourceHttp::class)->for(SourceName::Olx)->get('https://www.olx.ua/api/v1/offers/', ['city_id' => 62, 'limit' => 50]);

    expect($sent['url'])->toBe('https://relay.creastep.com/relay.php?key=s3cret&url='.rawurlencode('https://www.olx.ua/api/v1/offers/?city_id=62&limit=50'))
        ->and($sent['proxy'])->toBeNull();
});

it('treats an https proxy with credentials as a proxy, not a relay', function () {
    config(['sources.proxy_url' => 'https://login:pass@proxy.example.test:443']);
    Source::for(SourceName::Lun)->update(['use_proxy' => true]);

    expect(sentOptions(SourceName::Lun)['proxy'])->toBe('https://login:pass@proxy.example.test:443');
});

it('hides the relay key in error texts', function () {
    expect(SourceHttp::redact('cURL error 28 for https://relay.creastep.com/relay.php?key=s3cret&url=https%3A%2F%2Fflatfy.ua'))
        ->toBe('cURL error 28 for https://relay.creastep.com/relay.php?key=***&url=https%3A%2F%2Fflatfy.ua');
});
