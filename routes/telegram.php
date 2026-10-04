<?php

/** @var SergiX44\Nutgram\Nutgram $bot */

use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Exceptions\TelegramException;

/*
|--------------------------------------------------------------------------
| Nutgram Handlers
|--------------------------------------------------------------------------
|
| Loaded by the NutgramServiceProvider. In a group with privacy mode on the
| bot only gets commands, e.g. /ping@rent_radar_dev_bot.
|
*/

// A failing handler is logged and the update acknowledged: on a 500 Telegram
// would deliver the same update again and again
$bot->onException(function (Nutgram $bot, Throwable $e) {
    report($e);
});
$bot->onApiError(function (Nutgram $bot, TelegramException $e) {
    report($e);
});

// Connection check; the chat id goes to TELEGRAM_CHAT_ID
$bot->onCommand('ping', function (Nutgram $bot) {
    $bot->sendMessage("pong · chat_id {$bot->chatId()}");
})->description('Check the bot is connected');
