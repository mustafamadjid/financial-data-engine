<?php

namespace App\Application\Ops\Pipeline;

use App\Models\Filing;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class PipelineFilingHistoryQuery
{
    public function paginate(Filing $filing, int $perPage): LengthAwarePaginator
    {
        $history = $this->pipelineRuns($filing)
            ->unionAll($this->jobAttempts($filing))
            ->unionAll($this->auditEvents($filing));

        return DB::query()
            ->fromSub($history, 'pipeline_history')
            ->orderByDesc('occurred_at')
            ->orderByDesc('entry_id')
            ->paginate($perPage, ['*'], 'page');
    }

    private function pipelineRuns(Filing $filing): Builder
    {
        return DB::table('pipeline_runs')
            ->where('filing_id', $filing->filing_id)
            ->selectRaw("'pipelineRun' as type, id as entry_id, COALESCE(finished_at, started_at, created_at) as occurred_at, correlation_id, status, trigger as action, NULL as actor_id, NULL as rationale, NULL as stage, NULL as attempt, NULL as error_code, NULL as error_message");
    }

    private function jobAttempts(Filing $filing): Builder
    {
        return DB::table('pipeline_job_runs')
            ->where('filing_id', $filing->filing_id)
            ->selectRaw("'jobAttempt' as type, id as entry_id, COALESCE(finished_at, started_at, created_at) as occurred_at, correlation_id, status, stage as action, NULL as actor_id, NULL as rationale, stage, attempt, error_code, error_message");
    }

    private function auditEvents(Filing $filing): Builder
    {
        return DB::table('audit_logs')
            ->where('filing_id', $filing->filing_id)
            ->selectRaw("'auditEvent' as type, id as entry_id, created_at as occurred_at, correlation_id, NULL as status, action, actor_id, rationale, NULL as stage, NULL as attempt, NULL as error_code, NULL as error_message");
    }

    /** @param object{type: string, entry_id: int, occurred_at: string|null, correlation_id: string|null, status: string|null, action: string|null, actor_id: string|null, rationale: string|null, stage: string|null, attempt: int|null, error_code: string|null, error_message: string|null} $entry
     *  @return array<string, mixed> */
    public function resource(object $entry): array
    {
        return [
            'id' => "{$entry->type}:{$entry->entry_id}",
            'type' => $entry->type,
            'occurredAt' => $entry->occurred_at,
            'correlationId' => $entry->correlation_id,
            'status' => $entry->status,
            'action' => $entry->action,
            'actorId' => $entry->actor_id,
            'rationale' => $entry->rationale,
            'stage' => $entry->stage,
            'attempt' => $entry->attempt === null ? null : (int) $entry->attempt,
            'error' => $entry->error_message === null ? null : [
                'code' => $entry->error_code,
                'message' => $entry->error_message,
            ],
        ];
    }
}
