<?php

use App\Currency\CurrencyCode;
use App\Currency\ExchangeRates;
use App\Currency\NbuExchangeRates;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->fixture = json_decode(file_get_contents(base_path('tests/Fixtures/nbu/exchange.json')), true);
});

it('reads the USD and EUR rates from the NBU response', function () {
    Http::fake(['bank.gov.ua/*' => Http::response($this->fixture)]);

    $rates = app(NbuExchangeRates::class);

    expect($rates->uahPer(CurrencyCode::Usd))->toBe(44.9857)
        ->and($rates->uahPer(CurrencyCode::Eur))->toBe(50.5333)
        ->and($rates->uahPer(CurrencyCode::Uah))->toBe(1.0);
});

it('asks NBU once and serves the rest from cache', function () {
    Http::fake(['bank.gov.ua/*' => Http::response($this->fixture)]);

    app(NbuExchangeRates::class)->uahPer(CurrencyCode::Usd);
    app(NbuExchangeRates::class)->uahPer(CurrencyCode::Eur);

    Http::assertSentCount(1);
});

it('returns null when NBU fails and retries on the next call', function () {
    Http::fakeSequence('bank.gov.ua/*')
        ->push('Service Unavailable', 503)
        ->push($this->fixture);

    expect(app(NbuExchangeRates::class)->uahPer(CurrencyCode::Usd))->toBeNull()
        ->and(app(NbuExchangeRates::class)->uahPer(CurrencyCode::Usd))->toBe(44.9857);
});

it('does not need NBU for hryvnias', function () {
    expect(app(NbuExchangeRates::class)->uahPer(CurrencyCode::Uah))->toBe(1.0);

    Http::assertNothingSent();
});

it('is the bound exchange rates implementation', function () {
    expect(app(ExchangeRates::class))->toBeInstanceOf(NbuExchangeRates::class);
});
