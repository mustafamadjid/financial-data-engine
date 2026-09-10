<?php

namespace App\Application\Ops\Pipeline;

use App\Models\Filing;
use App\Models\PipelineJobRun;
use App\Models\PipelineRun;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class PipelineFilingQuery
{
    /** @var array<string, array<int, string>> */
    private const STAGE_ALIASES = [
        'DOWNLOAD' => ['DOWNLOAD', 'DOWNLOADING'],
        'PARSE' => ['PARSE', 'PARSING'],
        'NORMALIZE' => ['NORMALIZE', 'NORMALIZING'],
        'VALIDATE' => ['VALIDATE', 'VALIDATING'],
        'PUBLISH' => ['PUBLISH', 'PUBLISHING'],
    ];

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $activity = DB::table('pipeline_job_runs')
            ->select('filing_id')
            ->selectRaw('MAX(COALESCE(finished_at, started_at, created_at)) AS last_processed_at')
            ->groupBy('filing_id');

        $query = Filing::query()
            ->leftJoinSub($activity, 'pipeline_activity', function (JoinClause $join): void {
                $join->on('pipeline_activity.filing_id', '=', 'filings.filing_id');
            })
            ->select('filings.*')
            ->selectRaw('pipeline_activity.last_processed_at AS last_processed_at');

        $this->applyFilters($query, $filters);
        $this->applyOrdering($query, (string) ($filters['sort'] ?? 'last_processed_desc'));

        $paginator = $query->paginate((int) ($filters['per_page'] ?? 25));
        $filings = $paginator->getCollection();
        $this->attachStageProjections($filings);

        return $paginator;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(EloquentBuilder $query, array $filters): void
    {
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (EloquentBuilder $searchQuery) use ($search): void {
                $searchQuery
                    ->where('filings.filing_id', 'like', "%{$search}%")
                    ->orWhere('filings.issuer_code', 'like', "%{$search}%");
            });
        }

        if (($stage = $filters['processing_stage'] ?? null) !== null && $stage !== '') {
            $query->where('filings.processing_stage', $stage);
        }

        if (($quality = $filters['quality_status'] ?? null) !== null && $quality !== '') {
            $query->where('filings.quality_status', $quality);
        }

        if (($period = trim((string) ($filters['period'] ?? ''))) !== '') {
            if (ctype_digit($period)) {
                $query->where('filings.fiscal_year', (int) $period);
            } else {
                $query->where('filings.fiscal_period', $period);
            }
        }
    }

    private function applyOrdering(EloquentBuilder $query, string $sort): void
    {
        match ($sort) {
            'last_processed_asc' => $query->orderByRaw('pipeline_activity.last_processed_at IS NULL ASC')->orderBy('pipeline_activity.last_processed_at')->orderBy('filings.filing_id'),
            'issuer_asc' => $query->orderBy('filings.issuer_code')->orderBy('filings.filing_id'),
            default => $query->orderByRaw('pipeline_activity.last_processed_at IS NULL ASC')->orderByDesc('pipeline_activity.last_processed_at')->orderBy('filings.filing_id'),
        };
    }

    /** @param  EloquentCollection<int, Filing>  $filings */
    private function attachStageProjections(EloquentCollection $filings): void
    {
        if ($filings->isEmpty()) {
            return;
        }

        $filingIds = $filings->modelKeys();
        $latestRuns = PipelineRun::query()
            ->whereIn('filing_id', $filingIds)
            ->orderByDesc('id')
            ->get()
            ->groupBy('filing_id')
            ->map(static fn (Collection $runs): PipelineRun => $runs->first());

        $runIds = $latestRuns->pluck('id')->filter()->values();
        $jobRuns = $runIds->isEmpty()
            ? collect()
            : PipelineJobRun::query()
                ->whereIn('pipeline_run_id', $runIds)
                ->whereIn('stage', collect(self::STAGE_ALIASES)->flatten()->unique()->values())
                ->orderByDesc('id')
                ->get()
                ->groupBy('pipeline_run_id');

        foreach ($filings as $filing) {
            $run = $latestRuns->get($filing->filing_id);
            $attempts = $run === null ? collect() : $jobRuns->get($run->id, collect());
            $filing->setAttribute('pipeline_run', $run);
            $filing->setAttribute('pipeline_stage_summaries', $this->stageSummaries($attempts));
            $filing->setAttribute('pipeline_error_summary', $this->errorSummary($attempts));
            $filing->setAttribute('pipeline_retry_job_run_id', $this->retryableAttempt($attempts)?->id);
        }
    }

    /** @param  Collection<int, PipelineJobRun>  $attempts */
    private function stageSummaries(Collection $attempts): array
    {
        $summaries = [];

        foreach (self::STAGE_ALIASES as $key => $aliases) {
            $attempt = $attempts->first(static fn (PipelineJobRun $jobRun): bool => in_array($jobRun->stage, $aliases, true));
            $summaries[$key] = [
                'stage' => $key,
                'status' => $attempt === null ? 'NOT_STARTED' : (string) $attempt->status,
                'attempt' => $attempt?->attempt,
                'startedAt' => $attempt?->started_at?->toIso8601String(),
                'finishedAt' => $attempt?->finished_at?->toIso8601String(),
            ];
        }

        return $summaries;
    }

    /** @param  Collection<int, PipelineJobRun>  $attempts */
    private function errorSummary(Collection $attempts): ?array
    {
        $failed = $attempts->first(static fn (PipelineJobRun $jobRun): bool => $jobRun->status === 'FAILED' && $jobRun->error_message !== null);

        if ($failed === null) {
            return null;
        }

        return [
            'code' => $failed->error_code,
            'message' => $failed->error_message,
            'stage' => $this->canonicalStage((string) $failed->stage),
        ];
    }

    private function retryableAttempt(Collection $attempts): ?PipelineJobRun
    {
        foreach (self::STAGE_ALIASES as $aliases) {
            $attempt = $attempts->first(static fn (PipelineJobRun $jobRun): bool => in_array($jobRun->stage, $aliases, true));
            if ($attempt !== null && $attempt->status === 'FAILED' && $attempt->failure_classification === 'TRANSIENT' && $attempt->logical_input_hash !== null) {
                return $attempt;
            }
        }

        return null;
    }

    private function canonicalStage(string $stage): string
    {
        foreach (self::STAGE_ALIASES as $key => $aliases) {
            if (in_array($stage, $aliases, true)) {
                return $key;
            }
        }

        return $stage;
    }
}
