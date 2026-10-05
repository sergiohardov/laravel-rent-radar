<?php

declare(strict_types=1);

/*
 * Relay for laravel-rent-radar on a Ukrainian hosting (CityHost).
 *
 * The bot's hosting has a Finnish IP that LUN and OLX answer with 403, and it
 * lets out only ports 80 and 443. The bot calls this script over HTTPS:
 *
 *   GET https://relay.creastep.com/relay.php?key=SECRET&url=<encoded target>
 *
 * and gets the target's answer as fetched from a Ukrainian IP. Only HTTPS
 * requests to the listing sites below pass, so it is no open proxy.
 *
 * The key lives in relay-config.php (`<?php return ['key' => '...'];`), one
 * level above the site root when the hosting allows it, else next to this file.
 * Deployed by hand; see README → «Прод» → «Ретранслятор».
 */

const RELAY_ALLOWED_HOSTS = ['flatfy.ua', 'olx.ua', 'www.olx.ua'];

const RELAY_TIMEOUT_SECONDS = 25;

/**
 * Why the request may not be relayed, or null when it may.
 *
 * @param  array<string, mixed>  $query
 * @return array{0: int, 1: string}|null HTTP status and message
 */
function relay_reject(string $method, array $query, string $secret): ?array
{
    if ($method !== 'GET') {
        return [405, 'Only GET'];
    }

    if (strlen($secret) < 32 || ! hash_equals($secret, (string) ($query['key'] ?? ''))) {
        return [403, 'Forbidden'];
    }

    $url = (string) ($query['url'] ?? '');
    $parts = parse_url($url);
    if ($parts === false || ($parts['scheme'] ?? '') !== 'https' || ! isset($parts['host'])) {
        return [400, 'url must be an https address'];
    }

    if (isset($parts['user']) || isset($parts['pass']) || (isset($parts['port']) && $parts['port'] !== 443)) {
        return [400, 'url must not carry credentials or a port'];
    }

    if (! in_array(strtolower($parts['host']), RELAY_ALLOWED_HOSTS, true)) {
        return [400, 'Host not allowed'];
    }

    return null;
}

/**
 * @return array{status: int, type: string, body: string, error: string}
 */
function relay_fetch(string $url, string $accept, string $userAgent): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        // Redirects are not followed: they could lead off the allowed hosts
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => RELAY_TIMEOUT_SECONDS,
        CURLOPT_ENCODING => '',
        // OLX's CloudFront lets a client in or not by TLS fingerprint and HTTP
        // version; HTTP/1.1 passed from every machine we tried, HTTP/2 not
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_USERAGENT => $userAgent,
        CURLOPT_HTTPHEADER => ['Accept: '.$accept],
    ]);
    $body = curl_exec($ch);

    return [
        'status' => (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE),
        'type' => (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE),
        'body' => is_string($body) ? $body : '',
        'error' => curl_error($ch),
    ];
}

function relay_secret(): string
{
    foreach ([dirname(__DIR__).'/relay-config.php', __DIR__.'/relay-config.php'] as $path) {
        if (is_file($path)) {
            $config = require $path;

            return is_array($config) ? (string) ($config['key'] ?? '') : '';
        }
    }

    return '';
}

function relay_respond(int $status, string $body, string $type = 'text/plain; charset=utf-8'): void
{
    http_response_code($status);
    header('Content-Type: '.$type);
    header('Cache-Control: no-store');
    echo $body;
}

if (! defined('RELAY_NO_RUN')) {
    $rejection = relay_reject($_SERVER['REQUEST_METHOD'] ?? 'GET', $_GET, relay_secret());
    if ($rejection !== null) {
        relay_respond($rejection[0], $rejection[1]);

        return;
    }

    $answer = relay_fetch(
        (string) $_GET['url'],
        (string) ($_SERVER['HTTP_ACCEPT'] ?? 'application/json'),
        (string) ($_SERVER['HTTP_USER_AGENT'] ?? 'laravel-rent-radar relay'),
    );

    if ($answer['status'] === 0) {
        relay_respond(502, 'Upstream error: '.$answer['error']);

        return;
    }

    relay_respond($answer['status'], $answer['body'], $answer['type'] !== '' ? $answer['type'] : 'application/octet-stream');
}
