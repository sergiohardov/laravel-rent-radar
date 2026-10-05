<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pipeline Run Limits
    |--------------------------------------------------------------------------
    |
    | A source run stops after this many already seen listings in a row: the
    | feed is newest first, so the rest is older. Bumped listings come back to
    | the top as seen ones, hence more than one.
    |
    | The time budget is checked before each listing, and one listing may still
    | take an LLM call of up to ANTHROPIC_TIMEOUT_SECONDS (30 s) after it, so
    | budget + LLM timeout stays within the hosting's 60 s.
    |
    */

    'stop_after_seen' => (int) env('SOURCES_STOP_AFTER_SEEN', 10),

    'time_budget_seconds' => (float) env('SOURCES_TIME_BUDGET_SECONDS', 30),

    /*
    |--------------------------------------------------------------------------
    | Alerts
    |--------------------------------------------------------------------------
    |
    | The group hears about a source once its failures in a row reach this
    | number, and again when it works after that. A source that runs but
    | brings no new listings for this many hours is reported once, until a
    | new listing comes.
    |
    */

    'alert_after_failures' => 3,

    'silence_alert_hours' => 24,

    /*
    |--------------------------------------------------------------------------
    | HTTP
    |--------------------------------------------------------------------------
    |
    | LUN and OLX answer 403 to the hosting IP (Hetzner, Finland), so in
    | production they go through a Ukrainian IP: our relay on a Ukrainian
    | hosting (relay/relay.php), https://relay.creastep.com/relay.php?key=…,
    | or a proxy http://login:password@host:port. Each source turns it on
    | with its "use proxy" switch; in dev it is not needed.
    |
    */

    'proxy_url' => env('SOURCES_PROXY_URL'),

    // A scraping service in proxy mode (Scrape.do: http://TOKEN:geoCode=ua@proxy.scrape.do:8080)
    // re-signs HTTPS with its own certificate, so proxied requests skip the
    // certificate check. A plain datacenter proxy keeps it on.
    'proxy_verify_tls' => (bool) env('SOURCES_PROXY_VERIFY_TLS', true),

    'http_timeout_seconds' => (int) env('SOURCES_HTTP_TIMEOUT_SECONDS', 15),

    // OLX lets a client through or not by its TLS fingerprint together with
    // the HTTP version, so the version that works depends on the machine's
    // curl/OpenSSL build; verify on the hosting before changing it.
    'http_version' => env('SOURCES_HTTP_VERSION', '2.0'),

    /*
    |--------------------------------------------------------------------------
    | Per Source
    |--------------------------------------------------------------------------
    |
    | Page limits cap a run when nothing stops it earlier, e.g. the first run
    | on an empty database.
    |
    */

    'lun' => [
        'max_pages' => 5, // 24 listings per page
    ],

    'olx' => [
        'max_pages' => 4, // 50 offers per page plus paid promotions
    ],

    'dimria' => [
        'max_pages' => 3, // 20 ids per page, one card request per id
    ],

];
