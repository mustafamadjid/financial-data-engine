<?php

use App\Domain\FinancialData\Integration\PublishedFilingDocumentFactory;
use App\Models\PublishedSnapshot;
use Tests\TestCase;

uses(TestCase::class);

it('is invariant to input ordering across repeated permutations', function (): void {
    $payload = integrationInternalPayload();
    $baseline = (new PublishedFilingDocumentFactory)->make(new PublishedSnapshot([
        'snapshot_id' => 'PUB-PROPERTY',
        'published_at' => '2026-09-09 03:30:00',
        'payload' => $payload,
        'lineage' => ['pipeline_run_id' => 45],
    ]));

    for ($iteration = 0; $iteration < 100; $iteration++) {
        shuffle($payload['normalized_facts']);
        shuffle($payload['lineage']['raw_fact_ids']);
        shuffle($payload['lineage']['normalized_fact_ids']);
        shuffle($payload['lineage']['validation_result_ids']);

        $document = (new PublishedFilingDocumentFactory)->make(new PublishedSnapshot([
            'snapshot_id' => 'PUB-PROPERTY',
            'published_at' => '2026-09-09 03:30:00',
            'payload' => $payload,
            'lineage' => ['pipeline_run_id' => 45],
        ]));

        expect($document->canonicalJson())->toBe($baseline->canonicalJson())
            ->and($document->etag())->toBe($baseline->etag());
    }
});
