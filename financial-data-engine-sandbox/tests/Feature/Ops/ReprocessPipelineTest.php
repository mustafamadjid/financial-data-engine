<?php

use App\Jobs\Pipeline\DownloadFilingJob;
use App\Models\AuditLog;
use App\Models\Filing;
use App\Models\PipelineRun;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Queue;

uses(DatabaseMigrations::class);

it('allows public reprocess requests while validating stage and reason', function (): void {
    $filing = reprocessFixture();

    $this->postJson("/ops/actions/pipeline-filings/{$filing->filing_id}/reprocess", ['stage' => 'INVALID', 'reason' => 'x'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_ERROR');
});

it('starts a public reprocess with a system audit context', function (): void {
    Queue::fake();
    $filing = reprocessFixture();

    $response = $this->postJson("/ops/actions/pipeline-filings/{$filing->filing_id}/reprocess", ['stage' => 'DOWNLOAD', 'reason' => 'Provider correction.']);

    $response->assertStatus(202)
        ->assertJsonPath('data.operation', 'reprocess')
        ->assertJsonPath('data.filingId', $filing->filing_id)
        ->assertJsonPath('data.stage', 'DOWNLOAD');
    expect(PipelineRun::query()->where('filing_id', $filing->filing_id)->count())->toBe(1);
    expect(AuditLog::query()->where('filing_id', $filing->filing_id)->where('action', 'filing.reprocess_requested')->exists())->toBeTrue();
    expect(AuditLog::query()->where('filing_id', $filing->filing_id)->where('action', 'filing.reprocess_requested')->value('actor_id'))->toBe('system');
    Queue::assertPushed(DownloadFilingJob::class);
});

it('maps missing prerequisites and overlapping runs to stable domain errors', function (): void {
    Queue::fake();
    $filing = reprocessFixture();
    PipelineRun::query()->create([
        'filing_id' => $filing->filing_id, 'trigger' => 'REPROCESS', 'started_from_stage' => 'DOWNLOAD',
        'status' => 'RUNNING', 'correlation_id' => '44444444-4444-4444-4444-444444444444', 'started_at' => now(),
    ]);

    $this->postJson("/ops/actions/pipeline-filings/{$filing->filing_id}/reprocess", ['stage' => 'DOWNLOAD', 'reason' => 'Another attempt'])
        ->assertStatus(409)->assertJsonPath('code', 'ACTIVE_OPERATION');

    PipelineRun::query()->delete();
    $this->postJson("/ops/actions/pipeline-filings/{$filing->filing_id}/reprocess", ['stage' => 'NORMALIZE', 'reason' => 'No raw facts'])
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
