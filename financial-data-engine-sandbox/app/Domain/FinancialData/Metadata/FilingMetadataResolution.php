<?php

namespace App\Domain\FinancialData\Metadata;

final readonly class FilingMetadataResolution
{
    /**
     * @param  list<string>  $evidenceRawFactIds
     */
    public function __construct(
        public string $scope,
        public ?string $presentationCurrency,
        public string $status,
        public array $evidenceRawFactIds,
        public string $resolverVersion,
        public ?string $reason = null,
    ) {}

    public function isResolved(): bool
    {
        return $this->status === 'RESOLVED';
    }
}
