<?php

namespace App\Pipeline;

use Filament\Support\Contracts\HasLabel;

/**
 * Marks a stage leaves on a listing that passed; shown on the card as ❓ and similar.
 */
enum Flag: string implements HasLabel
{
    case UnknownPrice = 'unknown_price';
    case UnknownRooms = 'unknown_rooms';
    case UnknownFloor = 'unknown_floor';
    case UnknownGenerator = 'unknown_generator';

    /** Only the district matched the sector whitelist (📍?) */
    case DistrictOnly = 'district_only';

    /** Listed as one room, the text describes a euro two-room flat */
    case EuroTwoRoom = 'euro_two_room';

    public function getLabel(): string
    {
        return match ($this) {
            self::UnknownPrice => 'цена ❓',
            self::UnknownRooms => 'комнаты ❓',
            self::UnknownFloor => 'этаж ❓',
            self::UnknownGenerator => 'генератор ❓',
            self::DistrictOnly => '📍? только район',
            self::EuroTwoRoom => 'евродвушка',
        };
    }
}
