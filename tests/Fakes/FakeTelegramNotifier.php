<?php

namespace Tests\Fakes;

use App\Models\Listing;
use App\Telegram\TelegramNotifier;
use Throwable;

/**
 * Remembers sent listings instead of posting to Telegram.
 */
final class FakeTelegramNotifier implements TelegramNotifier
{
    /** @var list<string> external ids */
    public array $sent = [];

    /** @var list<string> alert texts */
    public array $alerts = [];

    private int $nextMessageId = 100;

    public function __construct(
        private readonly ?Throwable $failWith = null,
    ) {}

    public function send(Listing $listing): int
    {
        if ($this->failWith !== null) {
            throw $this->failWith;
        }

        $this->sent[] = $listing->external_id;

        return $this->nextMessageId++;
    }

    public function alert(string $text): int
    {
        if ($this->failWith !== null) {
            throw $this->failWith;
        }

        $this->alerts[] = $text;

        return $this->nextMessageId++;
    }
}
