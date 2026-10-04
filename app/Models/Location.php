<?php

namespace App\Models;

use App\Location\LocationKind;
use App\Models\Concerns\StoresDatesInUtc;
use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A sector whitelist entry: a street, a residential complex or a district.
 */
#[Fillable(['kind', 'name', 'aliases'])]
class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use HasFactory;

    use StoresDatesInUtc;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => LocationKind::class,
            // Unescaped Cyrillic, so the admin search can match it with LIKE
            'aliases' => 'json:unicode',
        ];
    }
}
