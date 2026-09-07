<?php

use App\Domain\FinancialData\Pipeline\PipelineIdempotencyKey;

it('creates the same key for the same stage inputs and dependency versions', function () {
    $first = PipelineIdempotencyKey::parse(
        filingId: 'FIL-001',
        sourceHash: 'sha256:abc',
        parserVersion: 'arelle-1',
        parserConfigVersion: 'config-1',
    );

    $second = PipelineIdempotencyKey::parse(
        filingId: 'FIL-001',
        sourceHash: 'sha256:abc',
        parserVersion: 'arelle-1',
        parserConfigVersion: 'config-1',
    );

    expect($first->value())->toBe($second->value())
        ->and($first->canonicalPayload())->toBe(
            'parse|filing_id=FIL-001|source_hash=sha256:abc|parser_version=arelle-1|parser_config_version=config-1'
        );
});

it('changes the key when a dependency version changes', function () {
    $first = PipelineIdempotencyKey::parse('FIL-001', 'sha256:abc', 'arelle-1', 'config-1');
    $second = PipelineIdempotencyKey::parse('FIL-001', 'sha256:abc', 'arelle-2', 'config-1');

    expect($first->value())->not->toBe($second->value());
});

it('rejects missing required stage input', function () {
    PipelineIdempotencyKey::forStage('parse', [
        'filing_id' => 'FIL-001',
        'source_hash' => 'sha256:abc',
    ]);
})->throws(InvalidArgumentException::class);
