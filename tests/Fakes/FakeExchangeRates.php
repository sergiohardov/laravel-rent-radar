<?php

namespace Tests\Fakes;

use App\Currency\CurrencyCode;
use App\Currency\ExchangeRates;

final class FakeExchangeRates implements ExchangeRates
{
    public function __construct(
        private readonly ?float $usd = 45.0,
        private readonly ?float $eur = 50.0,
    ) {}

    public function uahPer(CurrencyCode $currency): ?float
    {
        return match ($currency) {
            CurrencyCode::Uah => 1.0,
            CurrencyCode::Usd => $this->usd,
            CurrencyCode::Eur => $this->eur,
        };
    }
}
