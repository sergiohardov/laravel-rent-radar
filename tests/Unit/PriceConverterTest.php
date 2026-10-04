<?php

use App\Currency\CurrencyCode;
use App\Currency\ExchangeRates;
use App\Currency\PriceConverter;

function converter(?float $usdRate): PriceConverter
{
    return new PriceConverter(new class($usdRate) implements ExchangeRates
    {
        public function __construct(private ?float $usdRate) {}

        public function uahPer(CurrencyCode $currency): ?float
        {
            return $currency === CurrencyCode::Uah ? 1.0 : $this->usdRate;
        }
    });
}

it('converts dollars to whole hryvnias', function () {
    expect(converter(44.9857)->toUah(1050, CurrencyCode::Usd))->toBe(47235);
});

it('keeps hryvnias as they are', function () {
    expect(converter(null)->toUah(15000, CurrencyCode::Uah))->toBe(15000);
});

it('returns null without a rate', function () {
    expect(converter(null)->toUah(400, CurrencyCode::Usd))->toBeNull();
});
