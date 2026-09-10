<?php

use App\Jobs\Pipeline\DownloadFilingJob;
use App\Models\AuditLog;
use App\Models\Filing;
use App\Models\PipelineRun;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Queue;

uses(DatabaseMigrations::class);

it('requires explicit reprocess authorization and validates stage and reason', function (): void {
    $user = User::factory()->create();
    $filing = reprocessFixture();
    config(['financial-pipeline.ops.reprocess_actor_ids' => []]);

    $this->actingAs($user)->postJson("/ops/actions/pipeline-filings/{$filing->filing_id}/reprocess", ['stage' => 'DOWNLOAD', 'reason' => 'valid reason'])
        ->assertForbidden();

    config(['financial-pipeline.ops.reprocess_actor_ids' => [$user->getKey()]]);
    $this->actingAs($user)->postJson("/ops/actions/pipeline-filings/{$filing->filing_id}/reprocess", ['stage' => 'INVALID', 'reason' => 'x'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_ERROR');
});

it('starts an authorized reprocess with a new run and audit context', function (): void {
    Queue::fake();
    $user = User::factory()->create();
    $filing = reprocessFixture();
    config(['financial-pipeline.ops.reprocess_actor_ids' => [$user->getKey()]]);

    $response = $this->actingAs($user)->postJson("/ops/actions/pipeline-filings/{$filing->filing_id}/reprocess", ['stage' => 'DOWNLOAD', 'reason' => 'Provider correction.']);

    $response->assertStatus(202)
        ->assertJsonPath('data.operation', 'reprocess')
        ->assertJsonPath('data.filingId', $filing->filing_id)
        ->assertJsonPath('data.stage', 'DOWNLOAD');
    expect(PipelineRun::query()->where('filing_id', $filing->filing_id)->count())->toBe(1);
    expect(AuditLog::query()->where('filing_id', $filing->filing_id)->where('action', 'filing.reprocess_requested')->exists())->toBeTrue();
    Queue::assertPushed(DownloadFilingJob::class);
});

it('maps missing prerequisites and overlapping runs to stable domain errors', function (): void {
    Queue::fake();
    $user = User::factory()->create();
    $filing = reprocessFixture();
    config(['financial-pipeline.ops.reprocess_actor_ids' => [$user->getKey()]]);
    PipelineRun::query()->create([
        'filing_id' => $filing->filing_id, 'trigger' => 'REPROCESS', 'started_from_stage' => 'DOWNLOAD',
        'status' => 'RUNNING', 'correlation_id' => '44444444-4444-4444-4444-444444444444', 'started_at' => now(),
    ]);

    $this->actingAs($user)->postJson("/ops/actions/pipeline-filings/{$filing->filing_id}/reprocess", ['stage' => 'DOWNLOAD', 'reason' => 'Another attempt'])
        ->assertStatus(409)->assertJsonPath('code', 'ACTIVE_OPERATION');

    PipelineRun::query()->delete();
    $this->actingAs($user)->postJson("/ops/actions/pipeline-filings/{$filing->filing_id}/reprocess", ['stage' => 'NORMALIZE', 'reason' => 'No raw facts'])
        ->assertStatus(422)->assertJsonPath('code', 'PREREQUISITE_MISSING');
    Queue::assertNothingPushed();
});

function reprocessFixture(): Filing
{
    return Filing::query()->create([
        'filing_id' => 'FIL-REPROCESS-001', 'issuer_code' => 'HSSA', 'report_type' => 'ANNUAL',
        'fiscal_year' => 2025, 'fiscal_period' => 'FY', 'period_end' => '2025-12-31',
        'source_url' => 'https://example.test/reprocess', 'source_type' => 'XBRL_INSTANCE',
        'source_hash' => str_repeat('a', 64), 'revision_number' => 1, 'processing_stage' => 'FAILED',
        'quality_status' => 'REVIEW_REQUIRED',
    ]);
}
