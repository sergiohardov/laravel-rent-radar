<?php

use App\Currency\CurrencyCode;
use App\Models\Listing;
use App\Pipeline\Decision;
use App\Sources\SourceName;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

it('rejects a second listing with the same source and external id', function () {
    Listing::factory()->create(['source' => SourceName::Olx, 'external_id' => '936758180']);

    Listing::factory()->create(['source' => SourceName::Olx, 'external_id' => '936758180']);
})->throws(UniqueConstraintViolationException::class);

it('allows the same external id on different sources', function () {
    Listing::factory()->create(['source' => SourceName::Olx, 'external_id' => '42']);
    Listing::factory()->create(['source' => SourceName::Lun, 'external_id' => '42']);

    expect(Listing::query()->count())->toBe(2);
});

it('casts enums, json and time back from the database', function () {
    $listing = Listing::factory()->rejected('hard_filter', 'price 25000 UAH > 15000 UAH')->create([
        'currency' => CurrencyCode::Usd,
        'raw' => ['id' => 4728181952, 'autonomy_water' => true],
        'enrichment' => ['generator' => null, 'complex' => '32 Перлина'],
        'published_at' => '2026-10-03 23:55:16+03:00',
        'lat' => 46.434520,
    ])->fresh();

    expect($listing->source)->toBe(SourceName::DimRia)
        ->and($listing->currency)->toBe(CurrencyCode::Usd)
        ->and($listing->decision)->toBe(Decision::Rejected)
        ->and($listing->rejected_by)->toBe('hard_filter')
        ->and($listing->raw)->toBe(['id' => 4728181952, 'autonomy_water' => true])
        ->and($listing->enrichment['generator'])->toBeNull()
        ->and($listing->published_at->utc()->toDateTimeString())->toBe('2026-10-03 20:55:16')
        ->and($listing->lat)->toBe(46.43452);
});

it('stores a time given in Kyiv as the same moment in UTC', function () {
    $listing = Listing::factory()->create([
        'published_at' => CarbonImmutable::createFromFormat('Y-m-d H:i:s', '2026-10-03 23:55:16', 'Europe/Kyiv'),
    ]);

    expect(DB::table('listings')->where('id', $listing->id)->value('published_at'))->toBe('2026-10-03 20:55:16')
        ->and($listing->fresh()->published_at->toIso8601String())->toBe('2026-10-03T20:55:16+00:00');
});
