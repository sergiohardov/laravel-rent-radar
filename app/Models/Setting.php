<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Search settings: the only row of the settings table, edited in the admin panel.
 */
#[Fillable([
    'center_lat', 'center_lng', 'radius_m',
    'max_price_uah', 'rooms_min', 'rooms_max', 'floor_min', 'floor_max',
    'allow_unknown_price', 'allow_unknown_rooms', 'allow_unknown_floor', 'allow_unknown_generator',
])]
class Setting extends Model
{
    /**
     * Keep the UTC offset when writing timestampTz columns.
     */
    protected $dateFormat = 'Y-m-d H:i:sP';

    /**
     * The settings row, created with the migration defaults on first use.
     */
    public static function current(): self
    {
        return static::query()->firstOr(function (): self {
            $setting = new static;
            $setting->id = 1;
            $setting->save();

            return $setting->refresh();
        });
    }

    public function hasSector(): bool
    {
        return $this->center_lat !== null && $this->center_lng !== null && $this->radius_m !== null;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'center_lat' => 'float',
            'center_lng' => 'float',
            'radius_m' => 'integer',
            'max_price_uah' => 'integer',
            'rooms_min' => 'integer',
            'rooms_max' => 'integer',
            'floor_min' => 'integer',
            'floor_max' => 'integer',
            'allow_unknown_price' => 'boolean',
            'allow_unknown_rooms' => 'boolean',
            'allow_unknown_floor' => 'boolean',
            'allow_unknown_generator' => 'boolean',
        ];
    }
}
