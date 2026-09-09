<?php

namespace App\Services\Pipeline;

use App\Domain\FinancialData\Pipeline\PipelineStage;
use Illuminate\Support\Facades\Log;

final class PipelineExecutionLogger
{
    /**
     * @param  array<string, scalar|null>  $versions
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public function context(
        string $filingId,
        ?int $pipelineRunId,
        PipelineStage|string $stage,
        string $job,
        string $connection,
        string $queue,
        int $attempt,
        ?string $correlationId,
        array $versions = [],
        array $extra = [],
    ): array {
        $stageValue = $stage instanceof PipelineStage ? $stage->value : strtoupper($stage);

        return $this->sanitize([
            'filing_id' => $filingId,
            'pipeline_run_id' => $pipelineRunId,
            'stage' => $stageValue,
            'job' => $job,
            'connection' => $connection,
            'queue' => $queue,
            'attempt' => $attempt,
            'correlation_id' => $correlationId,
            ...$versions,
            ...$extra,
        ]);
    }

    /** @param array<string, mixed> $context */
    public function withContext(array $context): void
    {
        Log::withContext($this->sanitize($context));
    }

    /** @param array<string, mixed> $context @return array<string, mixed> */
    private function sanitize(array $context): array
    {
        $sanitized = [];

        foreach ($context as $key => $value) {
            $key = (string) $key;

            if (preg_match('/(?:token|secret|password|credential|api[_-]?key|authorization)/i', $key) === 1) {
                $sanitized[$key] = '[REDACTED]';

                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitize($value);

                continue;
            }

            $sanitized[$key] = is_string($value) ? $this->redactString($value, $key) : $value;
        }

        return $sanitized;
    }

    private function redactString(string $value, string $key): string
    {
        $value = preg_replace('/(Bearer\s+|(?:token|secret|password|api[_-]?key|signature|authorization)\s*[=:])[^\s,;&]+/i', '$1[REDACTED]', $value) ?? '[REDACTED]';

        if (preg_match('/(?:^|_)(?:path|url)$/i', $key) === 1) {
            $value = preg_replace('/([?&](?:token|secret|signature|expires|credential|api[_-]?key)=[^&]+)/i', '$1[REDACTED]', $value) ?? '[REDACTED]';
            $value = preg_replace('/[A-Za-z]:\\\\[^\s]+|\/(?:[^\s\/]+\/){2,}[^\s]+/', '[REDACTED_PATH]', $value) ?? '[REDACTED]';
        }

        return $value;
    }
}
