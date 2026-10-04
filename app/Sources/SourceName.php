<?php

namespace App\Sources;

enum SourceName: string
{
    case Lun = 'lun';
    case Olx = 'olx';
    case DimRia = 'dimria';

    /**
     * The name people know the site by, for Telegram messages.
     */
    public function label(): string
    {
        return match ($this) {
            self::Lun => 'ЛУН',
            self::Olx => 'OLX',
            self::DimRia => 'DIM.RIA',
        };
    }
}
