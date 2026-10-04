<?php

namespace App\Pipeline;

/**
 * Marks a stage leaves on a listing that passed; shown on the card as ❓ and similar.
 */
enum Flag: string
{
    case UnknownPrice = 'unknown_price';
    case UnknownRooms = 'unknown_rooms';
    case UnknownFloor = 'unknown_floor';
    case UnknownGenerator = 'unknown_generator';

    /** Only the district matched the sector whitelist (📍?) */
    case DistrictOnly = 'district_only';
}
