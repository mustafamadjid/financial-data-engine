<?php

use App\Models\Filing;
use App\Models\PipelineJobRun;
use App\Models\PipelineRun;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;

uses(DatabaseMigrations::class);

it('requires authentication for the pipeline data endpoint', function (): void {
    $response = $this->getJson('/ops/data/pipeline-filings');

    $response->assertUnauthorized();
});

it('returns the frozen Ops error shape for invalid list parameters', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/ops/data/pipeline-filings?per_page=10')
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_ERROR')
        ->assertJsonPath('fieldErrors.per_page.0', 'The selected per page is invalid.');
});

it('returns a paginated pipeline read model with per-stage statuses and capabilities', function (): void {
    $user = User::factory()->create();
    $filing = createPipelineReadFiling('FIL-OPS-001', 'BBCA', 'PUBLISHED', 'VERIFIED');
    $run = PipelineRun::query()->create([
        'filing_id' => $filing->filing_id,
        'trigger' => 'AUTOMATIC',
        'status' => 'SUCCEEDED',
        'correlation_id' => '22222222-2222-2222-2222-222222222222',
        'started_at' => '2026-09-08 01:00:00',
        'finished_at' => '2026-09-08 01:02:00',
    ]);
    createPipelineJobRun($run->id, $filing->filing_id, 'DOWNLOAD', 'SUCCEEDED', 1, '2026-09-08 01:00:00', '2026-09-08 01:00:10');
    createPipelineJobRun($run->id, $filing->filing_id, 'PARSE', 'SUCCEEDED', 1, '2026-09-08 01:00:10', '2026-09-08 01:00:30');
    createPipelineJobRun($run->id, $filing->filing_id, 'NORMALIZE', 'SUCCEEDED', 1, '2026-09-08 01:00:30', '2026-09-08 01:00:50');
    createPipelineJobRun($run->id, $filing->filing_id, 'VALIDATE', 'SUCCEEDED', 1, '2026-09-08 01:00:50', '2026-09-08 01:01:10');
    createPipelineJobRun($run->id, $filing->filing_id, 'PUBLISH', 'SUCCEEDED', 1, '2026-09-08 01:01:10', '2026-09-08 01:02:00');

    $response = $this->actingAs($user)->getJson('/ops/data/pipeline-filings?per_page=25');

    $response->assertOk()
        ->assertJsonPath('data.0.filingId', 'FIL-OPS-001')
        ->assertJsonPath('data.0.issuerCode', 'BBCA')
        ->assertJsonPath('data.0.processingStage', 'PUBLISHED')
        ->assertJsonPath('data.0.qualityStatus', 'VERIFIED')
        ->assertJsonPath('data.0.stages.DOWNLOAD.status', 'SUCCEEDED')
        ->assertJsonPath('data.0.stages.PUBLISH.status', 'SUCCEEDED')
        ->assertJsonPath('data.0.lastProcessedAt', '2026-09-08T01:02:00+00:00')
        ->assertJsonPath('data.0.allowedActions.viewDetail.allowed', true)
        ->assertJsonMissingPath('data.0.storagePath')
        ->assertJsonPath('meta.perPage', 25);
});

it('filters the pipeline list on the server and returns summary counts independently', function (): void {
    $user = User::factory()->create();
    createPipelineReadFiling('FIL-OPS-BBCA', 'BBCA', 'FAILED', 'FAILED');
    createPipelineReadFiling('FIL-OPS-TLKM', 'TLKM', 'PUBLISHED', 'VERIFIED');

    $list = $this->actingAs($user)->getJson('/ops/data/pipeline-filings?search=BBCA');
    $summary = $this->actingAs($user)->getJson('/ops/data/pipeline-summary');

    $list->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.issuerCode', 'BBCA');
    $summary->assertOk()
        ->assertJsonPath('data.failed', 1)
        ->assertJsonPath('data.verified', 1)
        ->assertJsonPath('data.total', 2);
});

it('projects missing stages, keeps revisions separate, and uses bounded deterministic queries', function (): void {
    $user = User::factory()->create();
    $first = createPipelineReadFiling('FIL-OPS-REV1', 'BBCA', 'PARSED', 'PENDING');
    $second = createPipelineReadFiling('FIL-OPS-REV2', 'BBCA', 'PUBLISHED', 'VERIFIED');
    $second->forceFill(['revision_number' => 2])->save();

    $run = PipelineRun::query()->create([
        'filing_id' => $second->filing_id,
        'trigger' => 'AUTOMATIC',
        'status' => 'SUCCEEDED',
        'correlation_id' => '33333333-3333-3333-3333-333333333333',
        'finished_at' => '2026-09-08 01:02:00',
    ]);
    createPipelineJobRun($run->id, $second->filing_id, 'PUBLISH', 'SUCCEEDED', 1, '2026-09-08 01:01:00', '2026-09-08 01:02:00');

    DB::flushQueryLog();
    DB::enableQueryLog();
    $response = $this->actingAs($user)->getJson('/ops/data/pipeline-filings?search=BBCA&per_page=25');
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.filingId', 'FIL-OPS-REV2')
        ->assertJsonPath('data.0.revisionNumber', 2)
        ->assertJsonPath('data.0.stages.DOWNLOAD.status', 'NOT_STARTED')
        ->assertJsonPath('data.0.stages.PUBLISH.status', 'SUCCEEDED')
        ->assertJsonPath('data.1.filingId', 'FIL-OPS-REV1');
    expect(count($queries))->toBeLessThanOrEqual(5);
});

it('renders the authenticated inertia pipeline shell', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/ops/pipeline')
        ->assertOk()
        ->assertSee('data-page');
});

function createPipelineReadFiling(
    string $filingId,
    string $issuerCode,
    string $processingStage,
    string $qualityStatus,
): Filing {
    return Filing::query()->create([
        'filing_id' => $filingId,
        'issuer_code' => $issuerCode,
        'report_type' => 'ANNUAL',
        'fiscal_year' => 2025,
        'fiscal_period' => 'FY 2025',
        'period_end' => '2025-12-31',
        'source_url' => "https://example.test/{$filingId}.zip",
        'source_type' => 'XBRL_INSTANCE',
        'source_hash' => "sha256:{$filingId}",
        'revision_number' => 1,
        'processing_stage' => $processingStage,
        'quality_status' => $qualityStatus,
    ]);
}

function createPipelineJobRun(
    int $pipelineRunId,
    string $filingId,
    string $stage,
    string $status,
    int $attempt,
    string $startedAt,
    string $finishedAt,
): PipelineJobRun {
    return PipelineJobRun::query()->create([
        'pipeline_run_id' => $pipelineRunId,
        'filing_id' => $filingId,
        'stage' => $stage,
        'job_class' => 'App\\Jobs\\Pipeline\\'.ucfirst(strtolower($stage)).'FilingJob',
        'queue_name' => strtolower($stage),
        'attempt' => $attempt,
        'status' => $status,
        'idempotency_key' => strtolower($stage).":{$filingId}:{$attempt}",
        'correlation_id' => '22222222-2222-2222-2222-222222222222',
        'started_at' => $startedAt,
        'finished_at' => $finishedAt,
    ]);
}
