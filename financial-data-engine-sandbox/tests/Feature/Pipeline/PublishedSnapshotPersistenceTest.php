<?php

use App\Models\Filing;
use App\Services\Pipeline\PublishedSnapshotPersistence;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('reuses the same snapshot for an identical publish identity', function () {
    $filing = Filing::query()->create([
        'filing_id' => 'FIL-SNAPSHOT-1',
        'issuer_code' => 'TEST',
        'report_type' => 'ANNUAL',
        'fiscal_year' => 2025,
        'fiscal_period' => 'FY',
        'period_end' => '2025-12-31',
        'source_url' => 'https://example.test/FIL-SNAPSHOT-1.xbrl',
        'source_type' => 'XBRL_INSTANCE',
        'source_hash' => hash('sha256', 'snapshot'),
        'revision_number' => 1,
        'processing_stage' => 'PUBLISHING',
        'quality_status' => 'VERIFIED',
    ]);
    $payload = [
        'contract' => 'hissa.financial-data.publish',
        'contract_version' => '1.0.0',
        'filing' => ['filing_id' => $filing->filing_id, 'revision_number' => 1],
        'quality' => ['status' => 'VERIFIED', 'validation_rule_set_version' => 'rules-1'],
        'normalized_facts' => [['normalized_fact_id' => 'NF-1', 'raw_fact_id' => 'RAW-1', 'value' => '1']],
        'lineage' => ['raw_fact_ids' => ['RAW-1'], 'normalized_fact_ids' => ['NF-1'], 'validation_result_ids' => ['VR-1']],
    ];

    $first = app(PublishedSnapshotPersistence::class)->persist($filing, $payload, 'publish-key-1', 1);
    $second = app(PublishedSnapshotPersistence::class)->persist($filing, $payload, 'publish-key-1', 1);

    expect($second->snapshot_id)->toBe($first->snapshot_id)
        ->and($filing->publishedSnapshots()->count())->toBe(1)
        ->and($second->payload)->toBe($payload);
});
