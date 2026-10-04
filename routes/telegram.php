<?php

/** @var Nutgram $bot */

use SergiX44\Nutgram\Nutgram;

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

// Connection check; the chat id goes to TELEGRAM_CHAT_ID
$bot->onCommand('ping', function (Nutgram $bot) {
    $bot->sendMessage("pong · chat_id {$bot->chatId()}");
})->description('Check the bot is connected');
