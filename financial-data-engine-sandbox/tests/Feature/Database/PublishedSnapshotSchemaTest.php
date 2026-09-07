<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates the immutable published snapshot schema', function () {
    expect(Schema::hasColumns('published_snapshots', [
        'snapshot_id',
        'filing_id',
        'revision_number',
        'publish_contract_version',
        'normalized_dataset_version',
        'validation_rule_set_version',
        'publish_idempotency_key',
        'payload',
        'lineage',
        'published_at',
    ]))->toBeTrue();
});
