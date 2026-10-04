<?php

namespace App\Currency;

interface ExchangeRates
{
    /**
     * How many hryvnias one unit of the currency costs, or null when the rate is unavailable.
     */
    public function uahPer(CurrencyCode $currency): ?float;
}
