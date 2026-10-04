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
