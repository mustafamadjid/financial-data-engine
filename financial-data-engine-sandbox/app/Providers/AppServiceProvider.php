<?php

namespace App\Providers;

use App\Domain\FinancialData\Discovery\Contracts\FilingDiscoverySource as DiscoveryFilingDiscoverySource;
use App\Domain\FinancialData\Pipeline\Contracts\FilingDiscoverySource;
use App\Infrastructure\Discovery\ConfiguredFilingDiscoverySource;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(FilingDiscoverySource::class, ConfiguredFilingDiscoverySource::class);
        $this->app->bind(DiscoveryFilingDiscoverySource::class, ConfiguredFilingDiscoverySource::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
