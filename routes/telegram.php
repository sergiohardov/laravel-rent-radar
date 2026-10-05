<?php

/** @var Nutgram $bot */

use App\Telegram\GroupReports;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use SergiX44\Nutgram\Telegram\Types\Message\LinkPreviewOptions;

/*
|--------------------------------------------------------------------------
| Nutgram Handlers
|--------------------------------------------------------------------------
|
| Loaded by the NutgramServiceProvider. In a group with privacy mode on the
| bot only gets commands, e.g. /ping@rent_radar_dev_bot.
|
*/

// A failing handler, Telegram API errors inside it included, is logged and the
// update acknowledged: on a 500 Telegram would deliver it again and again.
// No onApiError: it would make every API call of this bot return null instead
// of throwing, and the card job could not see a 429.
$bot->onException(function (Nutgram $bot, Throwable $e) {
    report($e);
});

// Connection check, in any chat: the chat id goes to TELEGRAM_CHAT_ID
$bot->onCommand('ping', function (Nutgram $bot) {
    $bot->sendMessage("pong · chat_id {$bot->chatId()}");
})->description('Проверить связь и узнать chat_id');

// Reports, only in the working group: anyone could add the bot to another chat
$bot->group(function (Nutgram $bot) {
    $reply = fn (Nutgram $bot, string $text) => $bot->sendMessage(
        text: $text,
        parse_mode: ParseMode::HTML,
        link_preview_options: LinkPreviewOptions::make(is_disabled: true),
    );

    $bot->onCommand('status', fn (Nutgram $bot) => $reply($bot, app(GroupReports::class)->status()))
        ->description('Состояние площадок');
    $bot->onCommand('funnel', fn (Nutgram $bot) => $reply($bot, app(GroupReports::class)->funnel()))
        ->description('Воронка за 24 часа и скорость');
    $bot->onCommand('reasons', fn (Nutgram $bot) => $reply($bot, app(GroupReports::class)->reasons()))
        ->description('Причины отсева за 7 дней');
    $bot->onCommand('help', fn (Nutgram $bot) => $reply($bot, app(GroupReports::class)->help()))
        ->description('Список команд');
})->middleware(function (Nutgram $bot, $next) {
    if ((string) $bot->chatId() === (string) config('services.telegram.chat_id')) {
        $next($bot);
    }
});
