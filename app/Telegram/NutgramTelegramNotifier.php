<?php

namespace App\Telegram;

use App\Models\Listing;
use RuntimeException;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Exceptions\TelegramException;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use SergiX44\Nutgram\Telegram\Types\Message\LinkPreviewOptions;

/**
 * Sends the card as a photo with a caption. When Telegram cannot fetch the
 * photo from the site's CDN, the same text goes as a plain message.
 */
final class NutgramTelegramNotifier implements TelegramNotifier
{
    public function __construct(
        private readonly Nutgram $bot,
        private readonly ListingCard $card,
    ) {}

    public function send(Listing $listing): int
    {
        $chatId = config('services.telegram.chat_id');
        if (blank($chatId)) {
            throw new RuntimeException('TELEGRAM_CHAT_ID is empty: send /ping@<bot> in the group to get it.');
        }

        $caption = $this->card->caption($listing);

        if (filled($listing->photo_url)) {
            try {
                return $this->bot->sendPhoto(photo: $listing->photo_url, chat_id: $chatId, caption: $caption, parse_mode: ParseMode::HTML)->message_id;
            } catch (TelegramException $e) {
                if ($e->getCode() === 429) {
                    throw $e;
                }

                report($e);
            }
        }

        return $this->bot->sendMessage(
            text: $caption,
            chat_id: $chatId,
            parse_mode: ParseMode::HTML,
            link_preview_options: LinkPreviewOptions::make(is_disabled: true),
        )->message_id;
    }
}
