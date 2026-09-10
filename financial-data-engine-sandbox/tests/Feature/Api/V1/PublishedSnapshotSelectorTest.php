<?php

use App\Domain\FinancialData\Integration\Exceptions\PublishedFilingNotFound;
use App\Domain\FinancialData\Integration\Exceptions\PublishedSnapshotNotFound;
use App\Domain\FinancialData\Integration\IssuerFilingQuery;
use App\Domain\FinancialData\Integration\PublishedSnapshotSelector;
use App\Models\Filing;
use App\Models\PublishedSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('selects the latest snapshot for an exact filing and exact historical snapshots', function (): void {
    $filing = selectorFiling('FIL-SELECTOR-1');
    selectorSnapshot($filing, 'PUB-001', '2026-09-09 10:00:00');
    selectorSnapshot($filing, 'PUB-002', '2026-09-09 11:00:00');
    $revision = selectorFiling('FIL-SELECTOR-2', ['revision_number' => 2]);
    selectorSnapshot($revision, 'PUB-003', '2026-09-09 12:00:00');

    $selector = app(PublishedSnapshotSelector::class);

    expect($selector->latestForFiling($filing->filing_id)->snapshot_id)->toBe('PUB-002')
        ->and($selector->exactSnapshot('PUB-001')->snapshot_id)->toBe('PUB-001');

    expect(fn () => $selector->latestForFiling('FIL-MISSING'))
        ->toThrow(PublishedFilingNotFound::class);
    expect(fn () => $selector->exactSnapshot('PUB-MISSING'))
        ->toThrow(PublishedSnapshotNotFound::class);
});

it('lists only the latest snapshot per filing with stable cursor traversal', function (): void {
    $first = selectorFiling('FIL-LIST-1', ['issuer_code' => 'TEST']);
    $second = selectorFiling('FIL-LIST-2', ['issuer_code' => 'TEST']);
    selectorSnapshot($first, 'PUB-LIST-1A', '2026-09-09 10:00:00');
    selectorSnapshot($first, 'PUB-LIST-1B', '2026-09-09 11:00:00');
    selectorSnapshot($second, 'PUB-LIST-2', '2026-09-09 10:00:00');

    $selector = app(PublishedSnapshotSelector::class);
    $query = new IssuerFilingQuery('TEST', limit: 1);
    $pageOne = $selector->forIssuer($query);
    $pageTwo = $selector->forIssuer($query->withCursor($pageOne->nextCursor()));

    expect($pageOne->items())->toHaveCount(1)
        ->and($pageOne->items()[0]->snapshot_id)->toBe('PUB-LIST-1B')
        ->and($pageTwo->items())->toHaveCount(1)
        ->and($pageTwo->items()[0]->snapshot_id)->toBe('PUB-LIST-2')
        ->and($pageOne->items()[0]->snapshot_id)->not->toBe($pageTwo->items()[0]->snapshot_id);
});

function selectorFiling(string $filingId, array $overrides = []): Filing
{
    return Filing::query()->create(array_merge([
        'filing_id' => $filingId,
        'issuer_code' => 'TEST',
        'report_type' => 'ANNUAL',
        'fiscal_year' => 2025,
        'fiscal_period' => 'FY',
        'period_end' => '2025-12-31',
        'source_url' => 'https://example.test/'.$filingId,
        'source_type' => 'XBRL_INSTANCE',
        'source_hash' => 'sha256:test',
        'revision_number' => 1,
        'processing_stage' => 'PUBLISHED',
        'quality_status' => 'VERIFIED',
    ], $overrides));
}

function selectorSnapshot(Filing $filing, string $snapshotId, string $publishedAt): PublishedSnapshot
{
    return PublishedSnapshot::query()->create([
        'snapshot_id' => $snapshotId,
        'filing_id' => $filing->filing_id,
        'revision_number' => $filing->revision_number,
        'publish_contract_version' => '1.0.0',
        'normalized_dataset_version' => 'dataset-1',
        'validation_rule_set_version' => 'rules-1',
        'publish_idempotency_key' => 'key-'.$snapshotId,
        'payload' => selectorPayload($filing),
        'lineage' => ['pipeline_run_id' => 1],
        'published_at' => $publishedAt,
    ]);
}

/** @return array<string, mixed> */
function selectorPayload(Filing $filing): array
{
    return [
        'filing' => [
            'filing_id' => $filing->filing_id,
            'issuer_code' => $filing->issuer_code,
            'report_type' => $filing->report_type,
            'fiscal_year' => $filing->fiscal_year,
            'fiscal_period' => $filing->fiscal_period,
            'period_end' => $filing->period_end->format('Y-m-d'),
        ],
        'quality' => ['status' => 'VERIFIED'],
    ];
}
