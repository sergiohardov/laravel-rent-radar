<?php

if (! defined('RELAY_NO_RUN')) {
    define('RELAY_NO_RUN', true);
}

require_once __DIR__.'/../../relay/relay.php';

const RELAY_TEST_SECRET = '0123456789abcdef0123456789abcdef';

function relayQuery(string $url, string $key = RELAY_TEST_SECRET): array
{
    return ['key' => $key, 'url' => $url];
}

it('relays https requests to the listing sites', function (string $url) {
    expect(relay_reject('GET', relayQuery($url), RELAY_TEST_SECRET))->toBeNull();
})->with([
    'https://flatfy.ua/api/realties?geo_id=10016589&page=1',
    'https://www.olx.ua/api/v1/offers/?category_id=1760&limit=50',
    'https://olx.ua/api/v1/offers/',
]);

it('refuses a wrong or missing key', function (array $query) {
    expect(relay_reject('GET', $query, RELAY_TEST_SECRET))->toBe([403, 'Forbidden']);
})->with([
    'wrong key' => [relayQuery('https://flatfy.ua/', 'nope')],
    'no key' => [['url' => 'https://flatfy.ua/']],
]);

it('refuses to run with a short key on the server', function () {
    expect(relay_reject('GET', relayQuery('https://flatfy.ua/', 'short'), 'short'))->toBe([403, 'Forbidden']);
});

it('refuses anything that is not an https request to an allowed host', function (string $url, string $message) {
    expect(relay_reject('GET', relayQuery($url), RELAY_TEST_SECRET))->toBe([400, $message]);
})->with([
    'other host' => ['https://example.com/', 'Host not allowed'],
    'look-alike host' => ['https://flatfy.ua.evil.test/', 'Host not allowed'],
    'plain http' => ['http://flatfy.ua/', 'url must be an https address'],
    'no url' => ['', 'url must be an https address'],
    'credentials' => ['https://user:pass@flatfy.ua/', 'url must not carry credentials or a port'],
    'other port' => ['https://flatfy.ua:8443/', 'url must not carry credentials or a port'],
]);

it('only relays GET', function () {
    expect(relay_reject('POST', relayQuery('https://flatfy.ua/'), RELAY_TEST_SECRET))->toBe([405, 'Only GET']);
});
