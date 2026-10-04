<?php

namespace App\Telegram;

use App\Models\Listing;

interface TelegramNotifier
{
    /**
     * Posts the listing card to the group.
     *
     * @return int the Telegram message id
     */
    public function send(Listing $listing): int;
}
