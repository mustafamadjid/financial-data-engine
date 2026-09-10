<?php

use App\Domain\FinancialData\Integration\Exceptions\InvalidIntegrationQuery;
use App\Domain\FinancialData\Integration\PublishedSnapshotCursor;
use Tests\TestCase;

uses(TestCase::class);

it('round trips a signed cursor bound to normalized filters', function (): void {
    $cursor = new PublishedSnapshotCursor('application-test-key');
    $filters = ['issuer_code' => 'test', 'limit' => 25, 'report_type' => null];
    $token = $cursor->encode('2026-09-09 10:00:00', 'PUB-1', $filters);
    $decoded = $cursor->decode($token, ['issuer_code' => 'TEST', 'limit' => 25, 'report_type' => null]);

    expect($decoded['snapshot_id'])->toBe('PUB-1')
        ->and($decoded['published_at'])->toBe('2026-09-09 10:00:00');
});

it('rejects tampered and filter-reused cursors', function (): void {
    $cursor = new PublishedSnapshotCursor('application-test-key');
    $token = $cursor->encode('2026-09-09 10:00:00', 'PUB-1', ['issuer_code' => 'TEST']);
    $tampered = substr($token, 0, -1).(substr($token, -1) === 'A' ? 'B' : 'A');

    expect(fn () => $cursor->decode($tampered, ['issuer_code' => 'TEST']))
        ->toThrow(InvalidIntegrationQuery::class);
    expect(fn () => $cursor->decode($token, ['issuer_code' => 'OTHER']))
        ->toThrow(InvalidIntegrationQuery::class);
});

it('enforces the documented page size boundaries', function (): void {
    expect(PublishedSnapshotCursor::normalizeLimit(null))->toBe(25)
        ->and(PublishedSnapshotCursor::normalizeLimit('1'))->toBe(1)
        ->and(PublishedSnapshotCursor::normalizeLimit('100'))->toBe(100);

    expect(fn () => PublishedSnapshotCursor::normalizeLimit('101'))
        ->toThrow(InvalidIntegrationQuery::class);
});
