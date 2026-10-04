<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use SergiX44\Nutgram\Nutgram;

it('answers /ping with the chat id', function () {
    app(Nutgram::class)
        ->hearMessage(['text' => '/ping', 'chat' => ['id' => -1001234567890, 'type' => 'supergroup', 'title' => 'Rent Radar Dev']])
        ->reply()
        ->assertReplyText('pong · chat_id -1001234567890');
});

it('points the webhook at the dev tunnel with the secret token', function () {
    config(['services.telegram.tunnel_metrics_url' => 'http://cloudflared:2000/quicktunnel']);
    Http::fake(['cloudflared:2000/*' => Http::response(['hostname' => 'kissing-promo-fall-hitting.trycloudflare.com'])]);
    $bot = app(Nutgram::class);
    $bot->willReceive(true)->willReceive(['url' => 'https://kissing-promo-fall-hitting.trycloudflare.com/telegram/webhook', 'has_custom_certificate' => false, 'pending_update_count' => 0]);

    $this->artisan('telegram:set-webhook')
        ->expectsOutput('Webhook: https://kissing-promo-fall-hitting.trycloudflare.com/telegram/webhook')
        ->assertSuccessful();

    $bot->assertReply('setWebhook', [
        'url' => 'https://kissing-promo-fall-hitting.trycloudflare.com/telegram/webhook',
        'secret_token' => md5(config('app.key')),
    ], index: 0);
});

it('refuses a plain HTTP webhook URL', function () {
    config(['services.telegram.tunnel_metrics_url' => null, 'app.url' => 'http://localhost:8080']);

    $this->artisan('telegram:set-webhook')
        ->expectsOutputToContain('Telegram needs an HTTPS webhook, got http://localhost:8080/telegram/webhook')
        ->assertFailed();
});

it('takes Telegram updates outside the web group, so no session or CSRF check', function () {
    $route = Route::getRoutes()->getByName('telegram.webhook');

    expect($route->methods())->toContain('POST')
        ->and($route->excludedMiddleware())->toContain('web');
});

it('acknowledges an update whose handler fails instead of answering 500', function () {
    $bot = app(Nutgram::class);
    $bot->onCommand('boom', fn () => throw new RuntimeException('handler failed'));

    // without routes/telegram.php's onException this would throw
    expect(fn () => $bot->hearText('/boom')->reply())->not->toThrow(RuntimeException::class);
});

it('checks the webhook secret token', function () {
    expect(config('nutgram.safe_mode'))->toBeTrue();
});
