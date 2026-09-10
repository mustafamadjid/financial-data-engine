<?php

namespace App\Application\Ops\Pipeline;

use App\Models\Filing;
use App\Models\PipelineJobRun;

final class PipelineSummaryQuery
{
    /** @return array<string, int> */
    public function summary(): array
    {
        $qualityCounts = Filing::query()
            ->selectRaw("COALESCE(quality_status, 'PENDING') AS quality_status, COUNT(*) AS aggregate")
            ->groupBy('quality_status')
            ->pluck('aggregate', 'quality_status');

        return [
            'total' => Filing::query()->count(),
            'active' => Filing::query()->whereIn('processing_stage', ['DOWNLOADING', 'PARSING', 'NORMALIZING', 'VALIDATING', 'PUBLISHING'])->count(),
            'failed' => (int) ($qualityCounts['FAILED'] ?? 0),
            'verified' => (int) ($qualityCounts['VERIFIED'] ?? 0),
            'reviewRequired' => (int) ($qualityCounts['REVIEW_REQUIRED'] ?? 0),
            'pending' => (int) ($qualityCounts['PENDING'] ?? 0),
            'failedExecutions' => PipelineJobRun::query()->where('status', 'FAILED')->count(),
        ];
    }
}
