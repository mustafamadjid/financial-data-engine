<?php

namespace App\Application\Ops\Pipeline;

use App\Models\Filing;
use App\Models\FilingArtifact;
use App\Models\PipelineJobRun;
use App\Models\PipelineRun;
use Illuminate\Support\Collection;

final class PipelineFilingDetailQuery
{
    /** @return array<string, mixed> */
    public function detail(Filing $filing): array
    {
        $run = PipelineRun::query()
            ->where('filing_id', $filing->filing_id)
            ->orderByDesc('id')
            ->first();
        $attempts = $run === null
            ? collect()
            : PipelineJobRun::query()
                ->where('pipeline_run_id', $run->id)
                ->orderByDesc('id')
                ->get();
        $artifacts = FilingArtifact::query()
            ->where('filing_id', $filing->filing_id)
            ->orderByDesc('downloaded_at')
            ->get();

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
            'currentRun' => $run === null ? null : $this->run($run),
            'stageAttempts' => $attempts->map(fn (PipelineJobRun $attempt): array => $this->attempt($attempt))->values()->all(),
            'latestError' => $this->latestError($attempts),
            'artifacts' => $artifacts->map(fn (FilingArtifact $artifact): array => $this->artifact($artifact))->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function run(PipelineRun $run): array
    {
        return [
            'pipelineRunId' => $run->id,
            'trigger' => $run->trigger,
            'status' => $run->status,
            'startedFromStage' => $run->started_from_stage,
            'correlationId' => $run->correlation_id,
            'dependencyVersions' => $run->dependency_versions ?? [],
            'startedAt' => $run->started_at?->toIso8601String(),
            'finishedAt' => $run->finished_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function attempt(PipelineJobRun $attempt): array
    {
        return [
            'jobRunId' => $attempt->id,
            'stage' => $attempt->stage,
            'attempt' => $attempt->attempt,
            'status' => $attempt->status,
            'correlationId' => $attempt->correlation_id,
            'error' => $attempt->error_message === null ? null : [
                'code' => $attempt->error_code,
                'message' => $attempt->error_message,
            ],
            'startedAt' => $attempt->started_at?->toIso8601String(),
            'finishedAt' => $attempt->finished_at?->toIso8601String(),
        ];
    }

    /** @param Collection<int, PipelineJobRun> $attempts
     *  @return array{code: ?string, message: string, stage: string}|null */
    private function latestError(Collection $attempts): ?array
    {
        $failed = $attempts->first(static fn (PipelineJobRun $attempt): bool => $attempt->status === 'FAILED' && $attempt->error_message !== null);

        return $failed === null ? null : [
            'code' => $failed->error_code,
            'message' => $failed->error_message,
            'stage' => $failed->stage,
        ];
    }

    /** @return array<string, mixed> */
    private function artifact(FilingArtifact $artifact): array
    {
        return [
            'artifactId' => $artifact->artifact_id,
            'artifactType' => $artifact->artifact_type,
            'filename' => $this->safeFilename($artifact->original_filename),
            'contentType' => $this->safeContentType($artifact->content_type),
            'sizeBytes' => $artifact->size_bytes,
            'downloadedAt' => $artifact->downloaded_at?->toIso8601String(),
        ];
    }

    private function safeFilename(string $filename): string
    {
        return preg_replace('/[^A-Za-z0-9._-]/', '_', basename(str_replace('\\', '/', $filename))) ?: 'artifact.bin';
    }

    private function safeContentType(?string $contentType): string
    {
        return $contentType !== null && preg_match('/\A[-\w.+]+\/[-\w.+]+\z/', $contentType) === 1
            ? $contentType
            : 'application/octet-stream';
    }
}
