<?php

use App\Application\Ops\DebtReview\DebtReviewReleaseGate;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);

it('fails closed for every debt review route by default', function (string $uri): void {
    $this->getJson($uri)
        ->assertNotFound()
        ->assertJsonPath('code', 'DEBT_REVIEW_DISABLED');
})->with([
    '/ops/debt-review',
    '/ops/data/debt-records?filing_id=FIL-DEBT-001',
    '/ops/data/debt-records/DEBT-001?filing_id=FIL-DEBT-001',
    '/ops/data/debt-coverage?filing_id=FIL-DEBT-001',
]);

it('requires every approved prerequisite before enabling debt review', function (): void {
    $gate = app(DebtReviewReleaseGate::class);

    expect($gate->enabled())->toBeFalse();

    config()->set('financial-pipeline.debt_review', [
        'enabled' => true,
        'approved_da4_rule_version' => 'da4-v1',
        'approved_dictionary_version' => 'creditors-v1',
        'evidence_resolution' => true,
        'pipeline_stable' => true,
        'page_design_approved' => true,
    ]);

    expect($gate->enabled())->toBeTrue();
});
