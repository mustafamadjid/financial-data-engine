<?php

use App\Models\AuditLog;
use App\Models\Filing;
use App\Models\FilingArtifact;
use App\Models\PipelineJobRun;
use App\Models\PipelineRun;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Storage;

uses(DatabaseMigrations::class);

it('requires an authenticated session for detail history and artifact reads', function (): void {
    $filing = detailFiling('FIL-DETAIL-AUTH');
    $artifact = detailArtifact($filing, 'source.zip');

    $this->getJson("/ops/data/pipeline-filings/{$filing->filing_id}")->assertUnauthorized();
    $this->getJson("/ops/data/pipeline-filings/{$filing->filing_id}/history")->assertUnauthorized();
    $this->getJson("/ops/data/pipeline-filings/{$filing->filing_id}/artifacts/{$artifact->artifact_id}")->assertUnauthorized();
});

it('returns a sanitized filing detail projection without private pipeline fields', function (): void {
    $user = User::factory()->create();
    $filing = detailFiling('FIL-DETAIL-001');
    $run = PipelineRun::query()->create([
        'filing_id' => $filing->filing_id,
        'trigger' => 'REPROCESS',
        'started_from_stage' => 'PARSE',
        'status' => 'FAILED',
        'initiated_by' => 'ops-user',
        'reason' => 'Mapping correction',
        'dependency_versions' => ['parser' => 'v2', 'mapping' => '2026.09'],
        'correlation_id' => '11111111-1111-1111-1111-111111111111',
        'started_at' => '2026-09-09 09:00:00',
        'finished_at' => '2026-09-09 09:01:00',
    ]);
    PipelineJobRun::query()->create([
        'pipeline_run_id' => $run->id,
        'filing_id' => $filing->filing_id,
        'stage' => 'PARSE',
        'job_class' => 'App\\Jobs\\Pipeline\\ParseXbrlJob',
        'queue_name' => 'pipeline',
        'attempt' => 2,
        'status' => 'FAILED',
        'idempotency_key' => 'private-idempotency-value',
        'correlation_id' => $run->correlation_id,
        'error_type' => 'ParserException',
        'error_code' => 'INVALID_XBRL',
        'error_message' => 'The source could not be parsed.',
        'error_context' => ['authorization' => 'secret-value', 'queue_payload' => 'serialized-private-payload'],
        'started_at' => '2026-09-09 09:00:10',
        'finished_at' => '2026-09-09 09:00:30',
    ]);
    detailArtifact($filing, '../../unsafe name.zip');

    $this->actingAs($user)
        ->getJson("/ops/data/pipeline-filings/{$filing->filing_id}")
        ->assertOk()
        ->assertJsonPath('data.filingId', 'FIL-DETAIL-001')
        ->assertJsonPath('data.currentRun.pipelineRunId', $run->id)
        ->assertJsonPath('data.currentRun.correlationId', '11111111-1111-1111-1111-111111111111')
        ->assertJsonPath('data.currentRun.dependencyVersions.parser', 'v2')
        ->assertJsonPath('data.stageAttempts.0.stage', 'PARSE')
        ->assertJsonPath('data.latestError.code', 'INVALID_XBRL')
        ->assertJsonPath('data.artifacts.0.filename', 'unsafe_name.zip')
        ->assertJsonMissingPath('data.storagePath')
        ->assertJsonMissingPath('data.stageAttempts.0.errorContext')
        ->assertJsonMissingPath('data.stageAttempts.0.idempotencyKey')
        ->assertJsonMissingPath('data.stageAttempts.0.jobClass');
});

it('returns a bounded newest-first history stream with stable pagination and sanitized audit data', function (): void {
    $user = User::factory()->create();
    $filing = detailFiling('FIL-HISTORY-001');
    $run = PipelineRun::query()->create([
        'filing_id' => $filing->filing_id,
        'trigger' => 'AUTOMATIC',
        'status' => 'SUCCEEDED',
        'correlation_id' => '22222222-2222-2222-2222-222222222222',
        'started_at' => '2026-09-09 09:00:00',
        'finished_at' => '2026-09-09 09:05:00',
    ]);
    PipelineJobRun::query()->create([
        'pipeline_run_id' => $run->id, 'filing_id' => $filing->filing_id, 'stage' => 'DOWNLOAD',
        'job_class' => 'App\\Jobs\\Pipeline\\DownloadFilingJob', 'queue_name' => 'pipeline', 'attempt' => 1,
        'status' => 'SUCCEEDED', 'idempotency_key' => 'private-key', 'correlation_id' => $run->correlation_id,
        'finished_at' => '2026-09-09 09:03:00',
    ]);
    foreach (range(2, 10) as $attempt) {
        PipelineJobRun::query()->create([
            'pipeline_run_id' => $run->id, 'filing_id' => $filing->filing_id, 'stage' => 'DOWNLOAD',
            'job_class' => 'App\\Jobs\\Pipeline\\DownloadFilingJob', 'queue_name' => 'pipeline', 'attempt' => $attempt,
            'status' => 'SUCCEEDED', 'idempotency_key' => "private-key-{$attempt}", 'correlation_id' => $run->correlation_id,
            'finished_at' => '2026-09-09 09:03:00',
        ]);
    }
    AuditLog::query()->create([
        'actor_id' => 'operator-42', 'action' => 'pipeline.reviewed', 'entity_type' => Filing::class,
        'entity_id' => $filing->filing_id, 'filing_id' => $filing->filing_id,
        'correlation_id' => $run->correlation_id, 'rationale' => 'Verified source evidence.',
        'new_value' => ['storage_path' => '/private/path', 'status' => 'VERIFIED'], 'created_at' => '2026-09-09 09:10:00',
    ]);

    $firstPage = $this->actingAs($user)
        ->getJson("/ops/data/pipeline-filings/{$filing->filing_id}/history?per_page=10&page=1")
        ->assertOk()
        ->assertJsonPath('data.0.type', 'auditEvent')
        ->assertJsonPath('data.0.actorId', 'operator-42')
        ->assertJsonPath('data.0.action', 'pipeline.reviewed')
        ->assertJsonPath('data.1.type', 'pipelineRun')
        ->assertJsonMissingPath('data.0.newValue')
        ->assertJsonPath('meta.currentPage', 1)
        ->assertJsonPath('meta.perPage', 10)
        ->assertJsonPath('meta.total', 12);

    $secondPage = $this->actingAs($user)
        ->getJson("/ops/data/pipeline-filings/{$filing->filing_id}/history?per_page=10&page=2")
        ->assertOk()
        ->assertJsonPath('data.0.type', 'jobAttempt')
        ->assertJsonPath('data.0.correlationId', $run->correlation_id);

    expect($firstPage->json('data.0.id'))->not->toBe($secondPage->json('data.0.id'));
});

it('only streams an existing artifact owned by the filing with safe headers', function (): void {
    Storage::fake('local');
    $user = User::factory()->create();
    $filing = detailFiling('FIL-ARTIFACT-001');
    $artifact = detailArtifact($filing, '../../quarterly report.zip');
    Storage::disk('local')->put($artifact->storage_path, 'artifact-content');
    $artifact->forceFill(['source_hash' => hash('sha256', 'artifact-content')])->save();

    $this->actingAs($user)
        ->get("/ops/data/pipeline-filings/{$filing->filing_id}/artifacts/{$artifact->artifact_id}")
        ->assertOk()
        ->assertHeader('content-type', 'application/zip')
        ->assertHeader('content-disposition', 'attachment; filename=quarterly_report.zip');

    Storage::disk('local')->put($artifact->storage_path, 'tampered-content');
    $this->actingAs($user)
        ->get("/ops/data/pipeline-filings/{$filing->filing_id}/artifacts/{$artifact->artifact_id}")
        ->assertNotFound();
    Storage::disk('local')->put($artifact->storage_path, 'artifact-content');

    $otherFiling = detailFiling('FIL-ARTIFACT-OTHER');
    $this->actingAs($user)
        ->get("/ops/data/pipeline-filings/{$otherFiling->filing_id}/artifacts/{$artifact->artifact_id}")
        ->assertNotFound();

    Storage::disk('local')->delete($artifact->storage_path);
    $this->actingAs($user)
        ->get("/ops/data/pipeline-filings/{$filing->filing_id}/artifacts/{$artifact->artifact_id}")
        ->assertNotFound();
});

function detailFiling(string $filingId): Filing
{
    return Filing::query()->create([
        'filing_id' => $filingId, 'issuer_code' => 'HSSA', 'report_type' => 'ANNUAL',
        'fiscal_year' => 2025, 'fiscal_period' => 'FY', 'period_end' => '2025-12-31',
        'source_url' => "https://example.test/{$filingId}", 'source_type' => 'XBRL_INSTANCE',
        'source_hash' => str_repeat('a', 64), 'revision_number' => 1, 'processing_stage' => 'FAILED',
        'quality_status' => 'REVIEW_REQUIRED',
    ]);
}

function detailArtifact(Filing $filing, string $filename): FilingArtifact
{
    $path = "financial-pipeline/artifacts/{$filing->filing_id}/source.zip";

    return FilingArtifact::query()->create([
        'artifact_id' => 'ART-'.strtolower($filing->filing_id), 'filing_id' => $filing->filing_id,
        'artifact_type' => 'XBRL_INSTANCE', 'source_hash' => str_repeat('b', 64), 'storage_path' => $path,
        'original_filename' => $filename, 'content_type' => 'application/zip', 'size_bytes' => 16,
        'downloaded_at' => '2026-09-09 09:00:00',
    ]);
}
