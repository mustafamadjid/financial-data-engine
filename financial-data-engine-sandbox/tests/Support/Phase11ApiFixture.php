<?php

use App\Models\Filing;
use App\Models\PublishedSnapshot;

function phase11ApiFiling(string $filingId): Filing
{
    return Filing::query()->create([
        'filing_id' => $filingId, 'issuer_code' => 'TEST', 'report_type' => 'ANNUAL',
        'fiscal_year' => 2025, 'fiscal_period' => 'FY', 'period_end' => '2025-12-31',
        'source_url' => 'https://example.test/'.$filingId, 'source_type' => 'XBRL_INSTANCE',
        'source_hash' => 'sha256:test', 'revision_number' => 1, 'processing_stage' => 'PUBLISHED',
        'quality_status' => 'VERIFIED',
    ]);
}

function phase11ApiSnapshot(Filing $filing, string $snapshotId, string $publishedAt): PublishedSnapshot
{
    $payload = [
        'contract' => 'hissa.financial-data.publish', 'contract_version' => '1.0.0',
        'filing' => [
            'filing_id' => $filing->filing_id, 'issuer_code' => $filing->issuer_code, 'report_type' => $filing->report_type,
            'fiscal_year' => 2025, 'fiscal_period' => 'FY', 'period_start' => null, 'period_end' => '2025-12-31',
            'revision_number' => 1, 'supersedes_filing_id' => null,
        ],
        'quality' => ['status' => 'VERIFIED', 'validation_rule_set_version' => 'rules-1', 'normalized_dataset_version' => 'dataset-1', 'validation_result_ids' => ['VR-1']],
        'source' => ['source_url' => 'https://example.test/'.$filing->filing_id, 'source_hash' => 'sha256:test', 'storage_reference' => null],
        'normalized_facts' => [[
            'normalized_fact_id' => 'NF-1', 'raw_fact_id' => 'RAW-1', 'canonical_concept' => 'cash', 'value' => '100',
            'currency' => 'IDR', 'scope' => 'CONSOLIDATED', 'period_start' => null, 'period_end' => '2025-12-31',
            'data_type' => 'REPORTED', 'mapping_rule_id' => 'MAP-1', 'mapping_rule_version' => 1,
            'normalization_version' => '1.0.0@mapping-1', 'validation_status' => 'VERIFIED',
        ]],
        'lineage' => ['raw_fact_ids' => ['RAW-1'], 'normalized_fact_ids' => ['NF-1'], 'validation_result_ids' => ['VR-1'], 'mapping_versions' => ['1.0.0@mapping-1'], 'normalization_version' => '1.0.0@mapping-1', 'normalized_dataset_version' => 'dataset-1'],
        'limitations' => ['items' => [], 'unmapped_concepts' => []],
    ];

    return PublishedSnapshot::query()->create([
        'snapshot_id' => $snapshotId, 'filing_id' => $filing->filing_id, 'revision_number' => 1,
        'publish_contract_version' => '1.0.0', 'normalized_dataset_version' => 'dataset-1',
        'validation_rule_set_version' => 'rules-1', 'publish_idempotency_key' => 'key-'.$snapshotId,
        'payload' => $payload, 'lineage' => ['pipeline_run_id' => 1], 'published_at' => $publishedAt,
    ]);
}
