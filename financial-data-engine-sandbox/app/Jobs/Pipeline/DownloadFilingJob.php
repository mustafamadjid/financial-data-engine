<?php

namespace App\Jobs\Pipeline;

use App\Application\Pipeline\PipelineOrchestrator;
use App\Domain\FinancialData\Download\Contracts\FilingArtifactDownloader;
use App\Domain\FinancialData\Download\Exceptions\TerminalDownloadException;
use App\Domain\FinancialData\Pipeline\PipelineIdempotencyKey;
use App\Domain\FinancialData\Pipeline\PipelineStage;
use App\Infrastructure\Storage\FilingArtifactStorage;
use App\Jobs\Middleware\PipelineOverlapMiddleware;
use App\Models\AuditLog;
use App\Models\Filing;
use App\Models\PipelineRun;
use App\Services\Pipeline\JobExecutionRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class DownloadFilingJob extends PipelineJob
{
    public int $tries;

    public int $timeout;

    public function __construct(string $filingId)
    {
        parent::__construct($filingId);
        $this->onQueue((string) config('financial-pipeline.stages.DOWNLOAD.queue', 'filing-download'));
        $this->tries = (int) config('financial-pipeline.stages.DOWNLOAD.tries', 5);
        $this->timeout = (int) config('financial-pipeline.stages.DOWNLOAD.timeout', 300);
    }

    public function backoff(): array
    {
        return array_values((array) config('financial-pipeline.stages.DOWNLOAD.backoff', [30, 120, 300]));
    }

    public function middleware(): array
    {
        return [new PipelineOverlapMiddleware($this->filingId, PipelineStage::Downloading, $this->timeout)];
    }

    public function handle(
        FilingArtifactDownloader $downloader,
        FilingArtifactStorage $storage,
        JobExecutionRecorder $executionRecorder,
        PipelineOrchestrator $orchestrator,
    ): void {
        $filing = Filing::query()->find($this->filingId);

        if ($filing === null) {
            throw new RuntimeException('Filing was not found.');
        }

        if (trim((string) $filing->source_url) === '') {
            $orchestrator->markFailed(
                $filing->filing_id,
                PipelineStage::Downloading,
                new TerminalDownloadException('Filing source locator is missing.'),
            );

            return;
        }

        if ($storage->validArtifact($filing, $filing->source_type) !== null && PipelineStage::tryFrom((string) $filing->processing_stage) !== PipelineStage::Downloading) {
            return;
        }

        $stage = PipelineStage::tryFrom((string) $filing->processing_stage);

        if ($stage !== PipelineStage::Downloading) {
            $orchestrator->markFailed(
                $filing->filing_id,
                PipelineStage::Downloading,
                new TerminalDownloadException('Filing is not ready for download.'),
            );

            return;
        }

        $pipelineRun = PipelineRun::query()->where('filing_id', $filing->filing_id)->latest('id')->first();

        if ($pipelineRun === null) {
            $pipelineRun = PipelineRun::query()->create([
                'filing_id' => $filing->filing_id,
                'trigger' => 'DOWNLOAD',
                'status' => 'RUNNING',
                'correlation_id' => (string) Str::uuid(),
                'started_at' => now(),
            ]);
        }

        $idempotencyKey = PipelineIdempotencyKey::download(
            $filing->filing_id,
            (string) $filing->source_url,
            (string) $filing->revision_number,
        )->value();
        $jobRun = $executionRecorder->queued(
            filingId: $filing->filing_id,
            stage: PipelineStage::Downloading->value,
            jobClass: self::class,
            queueName: (string) $this->queue,
            idempotencyKey: $idempotencyKey,
            pipelineRunId: $pipelineRun->id,
            correlationId: $pipelineRun->correlation_id,
        );
        $executionRecorder->running($jobRun);

        try {
            $downloaded = $downloader->download($filing);
            $artifact = $storage->persist(
                $filing,
                $downloaded->temporaryPath,
                (string) $filing->source_type,
                $downloaded->originalFilename,
                $downloaded->contentType,
            );

            DB::transaction(function () use ($filing, $artifact, $executionRecorder, $jobRun, $orchestrator): void {
                $current = Filing::query()->lockForUpdate()->findOrFail($filing->filing_id);
                $current->forceFill([
                    'storage_path' => $artifact->storage_path,
                    'source_hash' => $artifact->source_hash,
                    'downloaded_at' => $artifact->downloaded_at ?? now(),
                    'processing_stage' => PipelineStage::Downloaded->value,
                ])->save();

                $executionRecorder->succeeded($jobRun);
                AuditLog::create([
                    'actor_id' => 'system',
                    'action' => 'artifact.downloaded',
                    'entity_type' => $artifact::class,
                    'entity_id' => $artifact->artifact_id,
                    'new_value' => [
                        'filing_id' => $current->filing_id,
                        'artifact_type' => $artifact->artifact_type,
                        'source_hash' => $artifact->source_hash,
                        'storage_path' => $artifact->storage_path,
                    ],
                    'rationale' => 'Source artifact downloaded and integrity verified.',
                    'filing_id' => $current->filing_id,
                    'correlation_id' => $jobRun->correlation_id,
                ]);
                $orchestrator->dispatchNext($current->filing_id, PipelineStage::Downloaded);
            });
        } catch (TerminalDownloadException $exception) {
            $orchestrator->markFailed($filing->filing_id, PipelineStage::Downloading, $exception);
        }
    }

    public function failed(Throwable $exception): void
    {
        try {
            app(PipelineOrchestrator::class)->markFailed($this->filingId, PipelineStage::Downloading, $exception);
        } catch (Throwable) {
            // The queue worker already records the original failure; do not mask it.
        }
    }
}
