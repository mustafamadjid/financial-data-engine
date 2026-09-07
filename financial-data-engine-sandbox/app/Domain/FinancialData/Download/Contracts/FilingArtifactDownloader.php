<?php

namespace App\Domain\FinancialData\Download\Contracts;

use App\Domain\FinancialData\Download\DownloadedArtifact;
use App\Models\Filing;

interface FilingArtifactDownloader
{
    public function download(Filing $filing): DownloadedArtifact;
}
