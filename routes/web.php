<?php

use Illuminate\Support\Facades\Route;
use SergiX44\Nutgram\Nutgram;

Route::get('/', function () {
    return view('welcome');
});

// Telegram updates: no web group (no session, cookies or CSRF check, which is
// PreventRequestForgery in Laravel 13); Nutgram's safe mode checks the secret token header
Route::post('/telegram/webhook', fn (Nutgram $bot) => $bot->run())
    ->withoutMiddleware('web')
    ->name('telegram.webhook');
