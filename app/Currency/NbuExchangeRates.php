<?php

namespace App\Currency;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;

/**
 * Official National Bank of Ukraine rates, fetched once a day.
 */
final class NbuExchangeRates implements ExchangeRates
{
    private const URL = 'https://bank.gov.ua/NBUStatService/v1/statdirectory/exchange?json';

    private const CACHE_KEY = 'currency.nbu-rates';

    public function __construct(
        private readonly Http $http,
        private readonly Cache $cache,
    ) {}

    public function uahPer(CurrencyCode $currency): ?float
    {
        if ($currency === CurrencyCode::Uah) {
            return 1.0;
        }

        return $this->rates()[$currency->value] ?? null;
    }

    /**
     * @return array<string, float> rate by currency code; empty when NBU is unreachable
     */
    private function rates(): array
    {
        $cached = $this->cache->get(self::CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $response = $this->http->timeout(10)->get(self::URL);
        } catch (ConnectionException) {
            return [];
        }

        if (! $response->successful() || ! is_array($response->json())) {
            return [];
        }

        $rates = collect($response->json())
            ->filter(fn ($row) => is_array($row) && isset($row['cc'], $row['rate']))
            ->mapWithKeys(fn (array $row) => [$row['cc'] => (float) $row['rate']])
            ->all();

        $this->cache->put(self::CACHE_KEY, $rates, now()->addDay());

        return $rates;
    }
}
