<?php

namespace App\Models;

use App\Currency\CurrencyCode;
use App\Pipeline\Decision;
use App\Pipeline\Flag;
use App\Sources\SourceName;
use Database\Factories\ListingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'source', 'external_id', 'url', 'title', 'description', 'address', 'raw', 'source_hints',
    'price', 'currency', 'price_uah', 'rooms', 'floor', 'floors_total', 'area', 'lat', 'lng', 'published_at',
    'decision', 'rejected_by', 'reject_reason', 'flags',
    'enrichment', 'enriched_at',
])]
class Listing extends Model
{
    /** @use HasFactory<ListingFactory> */
    use HasFactory;

    /**
     * Keep the UTC offset when writing timestampTz columns, so "23:55+03:00"
     * is stored as 20:55 UTC instead of 23:55 UTC.
     */
    protected $dateFormat = 'Y-m-d H:i:sP';

    public function addFlag(Flag $flag): void
    {
        $this->flags = ($this->flags ?? collect())->push($flag)->unique()->values();
    }

    public function hasFlag(Flag $flag): bool
    {
        return (bool) $this->flags?->contains($flag);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => SourceName::class,
            'currency' => CurrencyCode::class,
            'decision' => Decision::class,
            'flags' => AsEnumCollection::of(Flag::class),
            'raw' => 'array',
            'source_hints' => 'array',
            'enrichment' => 'array',
            'price' => 'integer',
            'price_uah' => 'integer',
            'rooms' => 'integer',
            'floor' => 'integer',
            'floors_total' => 'integer',
            'area' => 'float',
            'lat' => 'float',
            'lng' => 'float',
            'published_at' => 'immutable_datetime',
            'enriched_at' => 'immutable_datetime',
        ];
    }
}
