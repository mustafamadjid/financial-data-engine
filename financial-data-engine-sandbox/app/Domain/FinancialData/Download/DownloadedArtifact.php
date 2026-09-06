<?php

namespace App\Domain\FinancialData\Download;

final readonly class DownloadedArtifact
{
    public function __construct(
        public string $temporaryPath,
        public string $originalFilename,
        public ?string $contentType,
        public int $sizeBytes,
    ) {}
}
