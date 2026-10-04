<?php

namespace App\Pipeline\Stages;

use App\Models\Listing;
use App\Pipeline\Stage;
use App\Pipeline\StageResult;
use App\Telegram\SendListingCard;

/**
 * Last stage: the listing passed everything, its card goes to the queue.
 */
final class NotifyStage implements Stage
{
    public function name(): string
    {
        return 'telegram_notifier';
    }

    public function handle(Listing $listing): StageResult
    {
        // The queued job reads the listing, so the stages' fields must be saved first
        $listing->save();

        SendListingCard::dispatch($listing->id);

        return StageResult::pass();
    }
}
