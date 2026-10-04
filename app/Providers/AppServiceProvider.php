<?php

namespace App\Providers;

use App\Currency\ExchangeRates;
use App\Currency\NbuExchangeRates;
use App\Pipeline\Pipeline;
use App\Pipeline\SeenFilter;
use App\Pipeline\Stages\HardFilter;
use App\Sources\Lun\LunSource;
use App\Sources\SourceRegistry;
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
    ];

    /**
     * Pipeline stages in order, cheap checks first, see App\Pipeline\Stage.
     *
     * @var list<class-string<\App\Pipeline\Stage>>
     */
    private const STAGES = [
        HardFilter::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ExchangeRates::class, NbuExchangeRates::class);

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
