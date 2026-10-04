<?php

use App\Currency\CurrencyCode;
use App\Pipeline\Decision;
use App\Sources\RawListing;
use App\Sources\SourceName;
use Carbon\CarbonImmutable;

it('maps to the attributes of a new pending listing', function () {
    $publishedAt = CarbonImmutable::parse('2026-10-03 23:55:16+03:00');

    $raw = new RawListing(
        source: SourceName::DimRia,
        externalId: '34999109',
        url: 'https://dom.ria.com/uk/realty-34999109.html',
        raw: ['realty_id' => 34999109],
        description: 'Здам 1-кімнатну квартиру на Черемушках.',
        price: 5000,
        currency: CurrencyCode::Uah,
        rooms: 1,
        floor: 3,
        floorsTotal: 5,
        lat: 46.43452,
        lng: 30.718861,
        publishedAt: $publishedAt,
    );

    expect($raw->toListingAttributes())->toMatchArray([
        'source' => SourceName::DimRia,
        'external_id' => '34999109',
        'title' => null,
        'price' => 5000,
        'currency' => CurrencyCode::Uah,
        'floors_total' => 5,
        'area' => null,
        'published_at' => $publishedAt,
        'decision' => Decision::Pending,
    ]);
});
