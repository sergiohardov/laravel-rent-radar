<?php

namespace App\Location;

enum LocationKind: string
{
    case Street = 'street';
    case Complex = 'complex';

    /** A district match alone puts a listing in the sector only with 📍? */
    case District = 'district';
}
