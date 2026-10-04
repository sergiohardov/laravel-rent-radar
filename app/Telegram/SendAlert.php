<?php

namespace App\Telegram;

use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use SergiX44\Nutgram\Telegram\Exceptions\TelegramException;

/**
 * Posts one service message to the group. Alerts share the group's messages
 * a minute with listing cards (GroupMessageLimit).
 */
class SendAlert implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $text,
    ) {}

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new GroupMessageLimit(1)];
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
        try {
            $notifier->alert($this->text);
        } catch (TelegramException $e) {
            if ($e->getCode() !== 429) {
                throw $e;
            }

            $this->release((int) $e->getParameter('retry_after', 30));
        }
    }
}
