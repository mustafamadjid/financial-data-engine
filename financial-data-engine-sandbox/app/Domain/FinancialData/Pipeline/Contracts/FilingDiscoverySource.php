<?php

namespace App\Domain\FinancialData\Pipeline\Contracts;

use App\Domain\FinancialData\Discovery\DiscoveredFilingData;
use App\Domain\FinancialData\Discovery\DiscoveryCriteria;

interface FilingDiscoverySource
{
    /**
     * @return iterable<DiscoveredFilingData>
     */
    public function discover(DiscoveryCriteria $criteria): iterable;
}
