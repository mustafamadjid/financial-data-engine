<?php

namespace App\Providers;

use App\Domain\FinancialData\Discovery\Contracts\FilingDiscoverySource as DiscoveryFilingDiscoverySource;
use App\Domain\FinancialData\Download\Contracts\FilingArtifactDownloader;
use App\Domain\FinancialData\Parsing\Contracts\XbrlParser;
use App\Domain\FinancialData\Pipeline\Contracts\FilingDiscoverySource;
use App\Domain\FinancialData\Publishing\FilingPublishPayloadBuilder;
use App\Domain\FinancialData\Publishing\PublishContractValidator;
use App\Domain\FinancialData\Validation\Contracts\ValidationRuleProvider;
use App\Domain\FinancialData\Validation\DatabaseValidationRuleProvider;
use App\Domain\FinancialData\Validation\ValidationRuleRegistry;
use App\Infrastructure\Discovery\ConfiguredFilingDiscoverySource;
use App\Infrastructure\Download\ConfiguredFilingArtifactDownloader;
use App\Infrastructure\Parsing\ArelleProcessParser;
use App\Services\Pipeline\ReprocessService;
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
        $this->app->bind(PublishContractValidator::class, fn (): PublishContractValidator => new PublishContractValidator(
            expectedContract: (string) config('financial-pipeline.publish.contract', 'hissa.financial-data.publish'),
            expectedVersion: (string) config('financial-pipeline.publish.contract_version', '1.0.0'),
        ));
        $this->app->bind(FilingPublishPayloadBuilder::class, fn (): FilingPublishPayloadBuilder => new FilingPublishPayloadBuilder(
            contract: (string) config('financial-pipeline.publish.contract', 'hissa.financial-data.publish'),
            contractVersion: (string) config('financial-pipeline.publish.contract_version', '1.0.0'),
        ));
        $this->app->bind(ReprocessService::class);
        $this->app->singleton(ValidationRuleRegistry::class, fn (): ValidationRuleRegistry => new ValidationRuleRegistry(
            (array) config('financial-pipeline.validation.rules', []),
        ));
        $this->app->bind(ValidationRuleProvider::class, DatabaseValidationRuleProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
