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
    | The time budget keeps a run inside the hosting's 60 s max_execution_time
    | with room for saving and logging.
    |
    */

    'stop_after_seen' => (int) env('SOURCES_STOP_AFTER_SEEN', 10),

    'time_budget_seconds' => (float) env('SOURCES_TIME_BUDGET_SECONDS', 45),

    /*
    |--------------------------------------------------------------------------
    | HTTP
    |--------------------------------------------------------------------------
    |
    | LUN and OLX answer 403 to the hosting IP (Hetzner, Finland), so in
    | production they go through a Ukrainian datacenter proxy, e.g.
    | http://login:password@203.0.113.10:8080. Each source turns it on with
    | its "use proxy" switch; in dev the proxy is not needed.
    |
    */

    'proxy_url' => env('SOURCES_PROXY_URL'),

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
