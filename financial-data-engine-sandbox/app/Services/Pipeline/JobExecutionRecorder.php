<?php

namespace App\Services\Pipeline;

use App\Models\PipelineJobRun;
use Illuminate\Support\Str;
use Throwable;

final class JobExecutionRecorder
{
    public function queued(
        string $filingId,
        string $stage,
        string $jobClass,
        string $queueName,
        string $idempotencyKey,
        int $attempt = 1,
        ?int $pipelineRunId = null,
        ?string $correlationId = null,
    ): PipelineJobRun {
        if ($pipelineRunId === null) {
            throw new \InvalidArgumentException('A pipeline run is required to record a job execution.');
        }

        return PipelineJobRun::query()->updateOrCreate(
            ['idempotency_key' => $idempotencyKey, 'attempt' => $attempt],
            [
                'pipeline_run_id' => $pipelineRunId,
                'filing_id' => $filingId,
                'stage' => strtoupper($stage),
                'job_class' => $jobClass,
                'queue_name' => $queueName,
                'status' => 'QUEUED',
                'correlation_id' => $correlationId ?? (string) Str::uuid(),
                'error_type' => null,
                'error_code' => null,
                'error_message' => null,
                'error_context' => null,
                'started_at' => null,
                'finished_at' => null,
            ],
        );
    }

    public function running(PipelineJobRun $jobRun): PipelineJobRun
    {
        $jobRun->forceFill(['status' => 'RUNNING', 'started_at' => now(), 'finished_at' => null])->save();

        return $jobRun;
    }

    public function succeeded(PipelineJobRun $jobRun): PipelineJobRun
    {
        $jobRun->forceFill(['status' => 'SUCCEEDED', 'finished_at' => now()])->save();

        return $jobRun;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function failed(PipelineJobRun $jobRun, Throwable $exception, array $context = []): PipelineJobRun
    {
        $jobRun->forceFill([
            'status' => 'FAILED',
            'error_type' => $exception::class,
            'error_code' => (string) $exception->getCode(),
            'error_message' => $this->redactString($exception->getMessage()),
            'error_context' => $this->sanitizeContext($context),
            'finished_at' => now(),
        ])->save();

        return $jobRun;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function sanitizeContext(array $context): array
    {
        $sanitized = [];

        foreach ($context as $key => $value) {
            if ($this->isSensitiveKey((string) $key)) {
                $sanitized[$key] = '[REDACTED]';

                continue;
            }

            $sanitized[$key] = is_array($value)
                ? $this->sanitizeContext($value)
                : (is_string($value) ? $this->redactString($value) : $value);
        }

        return $sanitized;
    }

    private function redactString(string $value): string
    {
        return preg_replace('/(Bearer\s+|(?:token|secret|password|api[_-]?key)\s*[=:]\s*)[^\s,;]+/i', '$1[REDACTED]', $value) ?? '[REDACTED]';
    }

    private function isSensitiveKey(string $key): bool
    {
        return preg_match('/(?:token|secret|password|credential|api[_-]?key|authorization)/i', $key) === 1;
    }
}
