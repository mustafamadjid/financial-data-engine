<?php

use App\Domain\FinancialData\Pipeline\PipelineStage;
use App\Jobs\Pipeline\DiscoverFilingsJob;
use App\Models\Filing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('dispatches discovery asynchronously from the operational command', function () {
    Queue::fake();

    $this->artisan('financial-data:discover', [
        'sourceAdapter' => 'configured',
        'discoveryWindow' => '2026-Q3',
        '--page-size' => 10,
    ])->assertExitCode(0);

    Queue::assertPushed(DiscoverFilingsJob::class);
});

it('rejects invalid reprocess stage and missing reason', function () {
    $this->artisan('financial-data:reprocess', [
        'filing' => 'FIL-UNKNOWN',
        'stage' => 'INVALID',
    ])->assertExitCode(1);
});

it('prints read-only filing status', function () {
    Filing::query()->create([
        'filing_id' => 'FIL-STATUS-1',
        'issuer_code' => 'TEST',
        'report_type' => 'ANNUAL',
        'fiscal_year' => 2025,
        'fiscal_period' => 'FY',
        'period_end' => '2025-12-31',
        'source_url' => 'https://example.test/status.xbrl',
        'source_type' => 'XBRL_INSTANCE',
        'source_hash' => hash('sha256', 'status'),
        'revision_number' => 1,
        'processing_stage' => PipelineStage::Validated->value,
        'quality_status' => 'VERIFIED',
    ]);

    $this->artisan('financial-data:status', ['filing' => 'FIL-STATUS-1'])
        ->assertExitCode(0)
        ->expectsOutputToContain('FIL-STATUS-1')
        ->expectsOutputToContain('VERIFIED');
});
