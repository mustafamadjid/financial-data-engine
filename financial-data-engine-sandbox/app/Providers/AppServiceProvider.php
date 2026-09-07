<?php

namespace App\Providers;

use App\Domain\FinancialData\Discovery\Contracts\FilingDiscoverySource as DiscoveryFilingDiscoverySource;
use App\Domain\FinancialData\Download\Contracts\FilingArtifactDownloader;
use App\Domain\FinancialData\Parsing\Contracts\XbrlParser;
use App\Domain\FinancialData\Pipeline\Contracts\FilingDiscoverySource;
use App\Infrastructure\Discovery\ConfiguredFilingDiscoverySource;
use App\Infrastructure\Download\ConfiguredFilingArtifactDownloader;
use App\Infrastructure\Parsing\ArelleProcessParser;
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
        $this->app->bind(FilingArtifactDownloader::class, ConfiguredFilingArtifactDownloader::class);
        $this->app->bind(XbrlParser::class, ArelleProcessParser::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
