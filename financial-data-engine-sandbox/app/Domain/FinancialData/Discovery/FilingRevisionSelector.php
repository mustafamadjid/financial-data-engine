<?php

namespace App\Domain\FinancialData\Discovery;

use App\Models\Filing;

final class FilingRevisionSelector
{
    public function latest(string $issuerCode, string $periodEnd): ?Filing
    {
        return Filing::query()
            ->where('issuer_code', strtoupper(trim($issuerCode)))
            ->whereDate('period_end', $periodEnd)
            ->orderByDesc('revision_number')
            ->orderByDesc('publication_timestamp')
            ->orderByDesc('discovered_at')
            ->orderByDesc('filing_id')
            ->first();
    }
}
