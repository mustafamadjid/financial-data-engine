<?php

namespace App\Http\Resources\Ops;

use App\Models\Filing;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Filing */
final class PipelineFilingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Filing $filing */
        $filing = $this->resource;
        $error = $filing->getAttribute('pipeline_error_summary');

        return [
            'filingId' => $filing->filing_id,
            'issuerCode' => $filing->issuer_code,
            'reportType' => $filing->report_type,
            'fiscalYear' => $filing->fiscal_year,
            'fiscalPeriod' => $filing->fiscal_period,
            'periodStart' => $filing->period_start?->toDateString(),
            'periodEnd' => $filing->period_end?->toDateString(),
            'revisionNumber' => $filing->revision_number,
            'processingStage' => $filing->processing_stage,
            'qualityStatus' => $filing->quality_status ?? 'PENDING',
            'stages' => $filing->getAttribute('pipeline_stage_summaries') ?? $this->emptyStages(),
            'lastProcessedAt' => $this->lastProcessedAt($filing),
            'errorSummary' => $error,
            'retryJobRunId' => $filing->getAttribute('pipeline_retry_job_run_id'),
            'allowedActions' => [
                'viewDetail' => ['allowed' => true, 'reasonCode' => null, 'reason' => null],
                'viewArtifact' => [
                    'allowed' => $filing->storage_path !== null,
                    'reasonCode' => $filing->storage_path === null ? 'ARTIFACT_NOT_AVAILABLE' : null,
                    'reason' => $filing->storage_path === null ? 'No stored source artifact is available.' : null,
                ],
                'viewHistory' => ['allowed' => true, 'reasonCode' => null, 'reason' => null],
                'retry' => [
                    'allowed' => $filing->getAttribute('pipeline_retry_job_run_id') !== null,
                    'reasonCode' => $filing->getAttribute('pipeline_retry_job_run_id') === null ? 'NOT_RETRYABLE' : null,
                    'reason' => $filing->getAttribute('pipeline_retry_job_run_id') === null ? 'Only the latest transient stage failure can be retried.' : null,
                ],
                'reprocess' => ['allowed' => false, 'reasonCode' => 'NOT_IN_SCOPE', 'reason' => 'Reprocess is not enabled in this release.'],
            ],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function emptyStages(): array
    {
        $stages = [];
        foreach (['DOWNLOAD', 'PARSE', 'NORMALIZE', 'VALIDATE', 'PUBLISH'] as $stage) {
            $stages[$stage] = [
                'stage' => $stage,
                'status' => 'NOT_STARTED',
                'attempt' => null,
                'startedAt' => null,
                'finishedAt' => null,
            ];
        }

        return $stages;
    }

    private function lastProcessedAt(Filing $filing): ?string
    {
        $value = $filing->getAttribute('last_processed_at');

        return $value === null ? null : CarbonImmutable::parse((string) $value)->toIso8601String();
    }
}
