<?php

namespace App\Sources;

use Filament\Support\Contracts\HasLabel;

enum SourceName: string implements HasLabel
{
    case Lun = 'lun';
    case Olx = 'olx';
    case DimRia = 'dimria';

    /**
     * The name people know the site by, for Telegram messages and the admin panel.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::Lun => 'ЛУН',
            self::Olx => 'OLX',
            self::DimRia => 'DIM.RIA',
        };
    }
}
