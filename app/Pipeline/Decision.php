<?php

namespace App\Pipeline;

enum Decision: string
{
    /** Saved, not all pipeline stages passed yet. */
    case Pending = 'pending';

    /** A stage rejected it; the stage and the reason are stored on the listing. */
    case Rejected = 'rejected';

    /** A stage could not finish, e.g. the LLM was unavailable; the reason is stored. */
    case Failed = 'failed';

    /** Sent to the Telegram group as a card. */
    case Notified = 'notified';

    /** The card was removed with the "🗑" button. */
    case Dismissed = 'dismissed';
}
