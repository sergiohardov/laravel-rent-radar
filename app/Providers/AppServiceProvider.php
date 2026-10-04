<?php

namespace App\Providers;

use Anthropic\Client as AnthropicClient;
use App\Currency\ExchangeRates;
use App\Currency\NbuExchangeRates;
use App\Enrichment\AnthropicListingEnricher;
use App\Enrichment\ListingEnricher;
use App\Location\LocationMatcher;
use App\Pipeline\Pipeline;
use App\Pipeline\SeenFilter;
use App\Pipeline\Stages\EnrichStage;
use App\Pipeline\Stages\HardFilter;
use App\Pipeline\Stages\TextHardFilter;
use App\Sources\DimRia\DimRiaSource;
use App\Sources\Lun\LunSource;
use App\Sources\Olx\OlxSource;
use App\Sources\SourceRegistry;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Listing source adapters, see App\Sources\ListingSource.
     *
     * @var list<class-string<\App\Sources\ListingSource>>
     */
    private const SOURCES = [
        LunSource::class,
        OlxSource::class,
        DimRiaSource::class,
    ];

    /**
     * Pipeline stages in order, cheap checks first, see App\Pipeline\Stage.
     *
     * @var list<class-string<\App\Pipeline\Stage>>
     */
    private const STAGES = [
        HardFilter::class,
        EnrichStage::class,
        TextHardFilter::class,
        LocationMatcher::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ExchangeRates::class, NbuExchangeRates::class);

        // The SDK sends requests through its own PSR-18 transport, not Laravel's
        // Http client; the timeout is enforced by that transport.
        $this->app->singleton(AnthropicClient::class, fn () => new AnthropicClient(
            apiKey: (string) config('services.anthropic.key'),
            requestOptions: [
                'transporter' => new GuzzleClient(['timeout' => config('services.anthropic.timeout_seconds')]),
            ],
        ));

        $this->app->bind(AnthropicListingEnricher::class, fn (Application $app) => new AnthropicListingEnricher(
            $app->make(AnthropicClient::class),
            (string) config('services.anthropic.model'),
        ));
        $this->app->bind(ListingEnricher::class, AnthropicListingEnricher::class);

        $this->app->tag(self::SOURCES, 'listing-sources');

        $this->app->singleton(SourceRegistry::class, fn (Application $app) => new SourceRegistry(
            $app->tagged('listing-sources'),
        ));

        $this->app->bind(Pipeline::class, fn (Application $app) => new Pipeline(
            $app->make(SeenFilter::class),
            array_map(fn (string $stage) => $app->make($stage), self::STAGES),
            config('sources.stop_after_seen'),
            config('sources.time_budget_seconds'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
