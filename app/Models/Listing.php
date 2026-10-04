<?php

namespace App\Models;

use App\Currency\CurrencyCode;
use App\Models\Concerns\StoresDatesInUtc;
use App\Pipeline\Decision;
use App\Pipeline\Flag;
use App\Sources\SourceName;
use Database\Factories\ListingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'source', 'external_id', 'url', 'photo_urls', 'title', 'description', 'address', 'raw', 'source_hints',
    'price', 'currency', 'price_uah', 'rooms', 'floor', 'floors_total', 'area', 'lat', 'lng', 'published_at',
    'decision', 'rejected_by', 'reject_reason', 'flags',
    'enrichment', 'enriched_at', 'telegram_message_id', 'notified_at',
])]
class Listing extends Model
{
    /** @use HasFactory<ListingFactory> */
    use HasFactory;

    use StoresDatesInUtc;

    public function addFlag(Flag $flag): void
    {
        $this->flags = ($this->flags ?? collect())->push($flag)->unique()->values();
    }

    public function removeFlag(Flag $flag): void
    {
        if ($this->flags !== null) {
            $this->flags = $this->flags->reject(fn (Flag $set) => $set === $flag)->values();
        }
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
            'photo_urls' => 'array',
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
            'telegram_message_id' => 'integer',
            'notified_at' => 'immutable_datetime',
        ];
    }
}
