<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('stores reprocess actor reason stage and dependency versions on pipeline runs', function () {
    expect(Schema::hasColumns('pipeline_runs', [
        'started_from_stage',
        'initiated_by',
        'reason',
        'dependency_versions',
    ]))->toBeTrue();
});
