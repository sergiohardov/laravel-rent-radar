<?php

namespace App\Telegram;

use App\Models\Listing;
use RuntimeException;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Exceptions\TelegramException;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use SergiX44\Nutgram\Telegram\Types\Input\InputMediaPhoto;
use SergiX44\Nutgram\Telegram\Types\Message\LinkPreviewOptions;

/**
 * Sends the card as an album of up to 10 photos with the caption on the first,
 * or as one photo when there is one. Telegram fetches the photos from the
 * site's CDN and fails the whole album when it cannot get one of them; then
 * the main photo goes alone, and without it the same text as a plain message.
 */
final class NutgramTelegramNotifier implements TelegramNotifier
{
    public function __construct(
        private readonly Nutgram $bot,
        private readonly ListingCard $card,
    ) {}

    public function send(Listing $listing): int
    {
        $chatId = $this->chatId();
        $caption = $this->card->caption($listing);
        $photos = array_values($listing->photo_urls ?? []);

        if (count($photos) > 1) {
            $messageId = $this->tryPhotos(fn () => $this->bot->sendMediaGroup(
                media: array_map(fn (string $url, int $i) => InputMediaPhoto::make(
                    media: $url,
                    caption: $i === 0 ? $caption : null,
                    parse_mode: $i === 0 ? ParseMode::HTML : null,
                ), $photos, array_keys($photos)),
                chat_id: $chatId,
            )[0]->message_id);

            if ($messageId !== null) {
                return $messageId;
            }
        }

        if ($photos !== []) {
            $messageId = $this->tryPhotos(fn () => $this->bot->sendPhoto(
                photo: $photos[0],
                chat_id: $chatId,
                caption: $caption,
                parse_mode: ParseMode::HTML,
            )->message_id);

            if ($messageId !== null) {
                return $messageId;
            }
        }

        return $this->message($chatId, $caption);
    }

    /**
     * @param  callable(): int  $send
     * @return int|null the message id, or null when Telegram could not take the photos
     */
    private function tryPhotos(callable $send): ?int
    {
        try {
            return $send();
        } catch (TelegramException $e) {
            if ($e->getCode() === 429) {
                throw $e;
            }

            report($e);

            return null;
        }
    }

    public function alert(string $text): int
    {
        return $this->message($this->chatId(), $text);
    }

    private function message(string $chatId, string $text): int
    {
        return $this->bot->sendMessage(
            text: $text,
            chat_id: $chatId,
            parse_mode: ParseMode::HTML,
            link_preview_options: LinkPreviewOptions::make(is_disabled: true),
        )->message_id;
    }

    private function chatId(): string
    {
        $chatId = (string) config('services.telegram.chat_id');
        if (blank($chatId)) {
            throw new RuntimeException('TELEGRAM_CHAT_ID is empty: send /ping@<bot> in the group to get it.');
        }

        return $chatId;
    }
}
