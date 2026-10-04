<?php

namespace App\Location;

use Filament\Support\Contracts\HasLabel;

enum LocationKind: string implements HasLabel
{
    case Street = 'street';
    case Complex = 'complex';

    /** A district match alone puts a listing in the sector only with 📍? */
    case District = 'district';

    public function getLabel(): string
    {
        return match ($this) {
            self::Street => 'Улица',
            self::Complex => 'ЖК',
            self::District => 'Район',
        };
    }
}
