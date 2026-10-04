<?php

namespace App\Providers;

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
    private const SOURCES = [];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->tag(self::SOURCES, 'listing-sources');

        $this->app->singleton(SourceRegistry::class, fn (Application $app) => new SourceRegistry(
            $app->tagged('listing-sources'),
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
