<?php

return [
    // The Telegram Api Token
    'token' => env('TELEGRAM_BOT_TOKEN'),

    // Check X-Telegram-Bot-Api-Secret-Token on every webhook call, in dev too:
    // the dev webhook is public through the tunnel. The secret is md5(APP_KEY),
    // and nutgram:hook:set / telegram:set-webhook register it with Telegram.
    'safe_mode' => true,

    // Extra or specific configurations
    'config' => [],

    // Handlers live in routes/telegram.php
    'routes' => true,

    // Enable or disable Nutgram mixins
    'mixins' => false,

    // Path to save generated classes
    'namespace' => app_path('Telegram'),

    // Set log channel
    'log_channel' => env('TELEGRAM_LOG_CHANNEL', 'null'),
];
