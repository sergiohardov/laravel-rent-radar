<?php

namespace App\Telegram;

use App\Models\Listing;
use App\Pipeline\Decision;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use SergiX44\Nutgram\Telegram\Exceptions\TelegramException;

/**
 * Posts one listing card. GroupMessageLimit keeps the group under Telegram's
 * messages a minute, counting each photo of the album; a Telegram 429 puts
 * the job back for retry_after.
 */
class SendListingCard implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $listingId,
    ) {}

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        $photos = count(Listing::query()->find($this->listingId)?->photo_urls ?? []);

        return [new GroupMessageLimit(max(1, $photos))];
    }

    /**
     * Rate-limit releases count as attempts, so the job lives by time instead.
     */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addDay();
    }

    public function handle(TelegramNotifier $notifier): void
    {
        $listing = Listing::query()->find($this->listingId);
        if ($listing === null || $listing->telegram_message_id !== null) {
            return;
        }

        try {
            $messageId = $notifier->send($listing);
        } catch (TelegramException $e) {
            if ($e->getCode() !== 429) {
                throw $e;
            }

            $this->release((int) $e->getParameter('retry_after', 30));

            return;
        }

        $listing->forceFill([
            'decision' => Decision::Notified,
            'telegram_message_id' => $messageId,
            'notified_at' => now(),
        ])->save();
    }
}
