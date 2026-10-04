<?php

namespace App\Pipeline;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum Decision: string implements HasColor, HasLabel
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

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'В работе',
            self::Rejected => 'Отсеяно',
            self::Failed => 'Сбой',
            self::Notified => 'Отправлено',
            self::Dismissed => 'Убрано',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Rejected, self::Dismissed => 'gray',
            self::Failed => 'danger',
            self::Notified => 'success',
        };
    }
}
