<?php

use App\Domain\FinancialData\Discovery\DiscoveredFilingData;

it('normalizes a valid filing metadata candidate and exposes its source locator', function () {
    $candidate = DiscoveredFilingData::fromArray([
        'filing_id' => ' filing-001 ',
        'issuer_code' => ' antm ',
        'report_type' => 'quarterly',
        'fiscal_year' => 2026,
        'fiscal_period' => 'Q2',
        'period_start' => '2026-01-01',
        'period_end' => '2026-06-30',
        'source_url' => 'https://example.test/filings/filing-001.zip',
        'source_type' => 'xbrl_instance',
        'source_hash' => 'sha256:abc123',
        'revision_number' => 1,
        'supersedes_filing_id' => null,
        'discovered_at' => '2026-09-06T10:00:00+07:00',
    ]);

    expect($candidate->filingId)->toBe('filing-001')
        ->and($candidate->issuerCode)->toBe('ANTM')
        ->and($candidate->reportType)->toBe('QUARTERLY')
        ->and($candidate->sourceType)->toBe('XBRL_INSTANCE')
        ->and($candidate->sourceLocator())->toBe('https://example.test/filings/filing-001.zip')
        ->and($candidate->discoveredAt)->toBe('2026-09-06T03:00:00+00:00');
});

it('rejects missing, unknown, and invalid filing metadata fields', function (array $payload) {
    expect(fn () => DiscoveredFilingData::fromArray($payload))
        ->toThrow(InvalidArgumentException::class);
})->with([
    [[]],
    [['filing_id' => 'FIL-001', 'issuer_code' => 'ANTM']],
    [['filing_id' => 'FIL-001', 'issuer_code' => 'ANTM', 'period_end' => '2026-06-30', 'source_url' => 'https://example.test/a', 'source_type' => 'XBRL_INSTANCE', 'source_hash' => 'hash', 'revision_number' => 1, 'unexpected' => true]],
    [['filing_id' => '../escape', 'issuer_code' => 'ANTM', 'period_end' => '2026-06-30', 'source_url' => 'https://example.test/a', 'source_type' => 'XBRL_INSTANCE', 'source_hash' => 'hash', 'revision_number' => 1]],
    [['filing_id' => 'FIL-001', 'issuer_code' => 'ANTM', 'period_end' => 'not-a-date', 'source_url' => 'https://example.test/a', 'source_type' => 'XBRL_INSTANCE', 'source_hash' => 'hash', 'revision_number' => 1]],
]);
