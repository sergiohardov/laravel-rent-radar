<?php
// Check a hosting before moving the relay there: do LUN and OLX answer
// plain PHP curl from this server's IP? Upload to the hosting, open in the browser
// (or run `php check.php` over SSH), then delete the file.

header('Content-Type: text/plain; charset=utf-8');
set_time_limit(120);

$targets = [
    'lun' => 'https://flatfy.ua/api/realties?geo_id=10016589&section_id=2&sort=insert_time&page=1',
    'olx' => 'https://www.olx.ua/api/v1/offers/?category_id=1760&city_id=62&offset=0&limit=50',
];
$versions = ['http1.1' => CURL_HTTP_VERSION_1_1, 'http2' => CURL_HTTP_VERSION_2TLS];
$userAgents = [
    'guzzle' => 'GuzzleHttp/7',
    'browser' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0 Safari/537.36',
];

function fetch(string $url, int $version, string $userAgent): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => $version,
        CURLOPT_USERAGENT => $userAgent,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_ENCODING => '',
    ]);
    $body = curl_exec($ch);
    $result = [
        'status' => curl_getinfo($ch, CURLINFO_RESPONSE_CODE),
        'seconds' => round(curl_getinfo($ch, CURLINFO_TOTAL_TIME), 1),
        'error' => curl_error($ch),
        'body' => is_string($body) ? $body : '',
    ];

    return $result;
}

$ip = trim((string) @file_get_contents('https://api.ipify.org'));
echo "Server IP: {$ip}\n";
$geo = @json_decode((string) @file_get_contents("http://ip-api.com/json/{$ip}?fields=country,isp,as"), true);
echo 'Country / network: '.($geo['country'] ?? '?').' / '.($geo['isp'] ?? '?').' / '.($geo['as'] ?? '?')."\n";
echo 'PHP '.PHP_VERSION.', curl '.curl_version()['version'].', '.curl_version()['ssl_version']."\n\n";

foreach ($targets as $name => $url) {
    foreach ($versions as $versionName => $version) {
        foreach ($userAgents as $uaName => $userAgent) {
            $r = fetch($url, $version, $userAgent);
            $json = json_decode($r['body'], true);
            $items = is_array($json) ? count($json['data'] ?? []) : 0;
            $verdict = $r['status'] === 200 && $items > 0 ? 'OK' : 'BLOCKED';
            printf("%-4s %-8s %-8s -> %s: HTTP %d, %d items, %.1f s %s\n",
                $name, $versionName, $uaName, $verdict, $r['status'], $items, $r['seconds'], $r['error']);
        }
    }
}
