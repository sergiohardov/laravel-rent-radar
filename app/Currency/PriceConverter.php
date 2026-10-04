<?php

namespace App\Currency;

final class PriceConverter
{
    public function __construct(
        private readonly ExchangeRates $rates,
    ) {}

    /**
     * Price in whole hryvnias, or null when the rate is unavailable.
     */
    public function toUah(int $price, CurrencyCode $currency): ?int
    {
        $rate = $this->rates->uahPer($currency);

        return $rate === null ? null : (int) round($price * $rate);
    }
}
