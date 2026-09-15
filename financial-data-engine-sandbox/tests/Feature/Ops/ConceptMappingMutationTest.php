<?php

use App\Domain\FinancialData\Mapping\MappingSeriesKey;
use App\Models\AuditLog;
use App\Models\CanonicalConcept;
use App\Models\ConceptMapping;
use App\Models\EvidenceRecord;
use App\Models\Filing;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Bus;

uses(DatabaseMigrations::class);

it('creates the first mapping version publicly with server-owned metadata and no reprocess dispatch', function (): void {
    Bus::fake();
    $seriesKey = mappingMutationFixture();
    $evidence = EvidenceRecord::query()->create([
        'evidence_id' => 'EVID-MUT-001', 'filing_id' => 'FIL-MUT-001', 'source_document' => 'report.html',
        'source_concept' => 'Assets', 'extraction_method' => 'PARSER',
    ]);

    $this->postJson("/ops/actions/concept-mappings/{$seriesKey}/versions", [
        'source_concept' => 'Assets',
        'entry_point' => 'general',
        'canonical_concept' => 'total_assets',
        'status' => 'DRAFT',
        'rationale' => 'Map the source fact to the canonical asset concept.',
        'evidence_ids' => [$evidence->evidence_id],
        'period_type' => 'INSTANT',
        'sign_convention' => 'AS_REPORTED',
        'expected_version' => null,
    ])->assertCreated()
        ->assertJsonPath('data.mappingSeriesKey', $seriesKey)
        ->assertJsonPath('data.version', 1)
        ->assertJsonPath('data.createdBy', 'system')
        ->assertJsonPath('data.supersedesMappingRuleId', null)
        ->assertJsonPath('data.allowedActions.reprocess.allowed', false);

    $mapping = ConceptMapping::query()->firstOrFail();
    expect($mapping->mapping_series_key)->toBe($seriesKey)
        ->and($mapping->rule_version)->toBe(1)
        ->and($mapping->created_by)->toBe('system');
    Bus::assertNothingDispatched();
    expect(AuditLog::query()->where('action', 'concept_mapping.version_created')->count())->toBe(1);
});

it('appends exactly one next version, links its predecessor, audits before and after, and preserves the prior row', function (): void {
    $seriesKey = mappingMutationFixture();
    $previous = ConceptMapping::query()->create([
        'mapping_rule_id' => 'MAP-MUT-001', 'mapping_series_key' => $seriesKey, 'source_concept' => 'Assets',
        'entry_point' => 'general', 'canonical_concept' => 'total_assets', 'status' => 'APPROVED',
        'rationale' => 'Initial mapping.', 'rule_version' => 1, 'created_by' => 'system',
    ]);
    $before = [
        'mapping_rule_id' => $previous->mapping_rule_id,
        'mapping_series_key' => $previous->mapping_series_key,
        'source_concept' => $previous->source_concept,
        'entry_point' => $previous->entry_point,
        'canonical_concept' => $previous->canonical_concept,
        'status' => $previous->status,
        'rationale' => $previous->rationale,
        'rule_version' => $previous->rule_version,
        'created_by' => $previous->created_by,
    ];

    $this->postJson("/ops/actions/concept-mappings/{$seriesKey}/versions", [
        'source_concept' => 'Assets', 'entry_point' => 'general', 'canonical_concept' => 'total_assets',
        'status' => 'APPROVED', 'rationale' => 'Clarify the mapping with reviewed evidence.',
        'expected_version' => 1, 'allowed_scope' => ['CONSOLIDATED'], 'period_type' => 'INSTANT',
        'sign_convention' => 'AS_REPORTED',
    ])->assertCreated()
        ->assertJsonPath('data.version', 2)
        ->assertJsonPath('data.supersedesMappingRuleId', 'MAP-MUT-001');

    $next = ConceptMapping::query()->where('rule_version', 2)->firstOrFail();
    expect($next->mapping_rule_id)->not->toBe($previous->mapping_rule_id)
        ->and($next->supersedes_mapping_rule_id)->toBe('MAP-MUT-001')
        ->and($previous->fresh()->only(array_keys($before)))->toBe($before)
        ->and(ConceptMapping::query()->where('mapping_series_key', $seriesKey)->count())->toBe(2);

    $audit = AuditLog::query()->where('action', 'concept_mapping.version_created')->firstOrFail();
    expect($audit->actor_id)->toBe('system')
        ->and($audit->entity_id)->toBe($next->mapping_rule_id)
        ->and($audit->old_value['mapping_rule_id'])->toBe('MAP-MUT-001')
        ->and($audit->old_value['rule_version'])->toBe(1)
        ->and($audit->new_value['mapping_rule_id'])->toBe($next->mapping_rule_id)
        ->and($audit->new_value['rule_version'])->toBe(2)
        ->and($audit->rationale)->toBe('Clarify the mapping with reviewed evidence.');
});

it('rejects a stale expected version without changing the series', function (): void {
    $seriesKey = mappingMutationFixture();
    ConceptMapping::query()->create([
        'mapping_rule_id' => 'MAP-MUT-STALE', 'mapping_series_key' => $seriesKey, 'source_concept' => 'Assets',
        'entry_point' => 'general', 'canonical_concept' => 'total_assets', 'status' => 'APPROVED', 'rule_version' => 3,
    ]);

    $this->postJson("/ops/actions/concept-mappings/{$seriesKey}/versions", [
        'source_concept' => 'Assets', 'entry_point' => 'general', 'canonical_concept' => 'total_assets',
        'status' => 'DRAFT', 'rationale' => 'This request is based on an old version.', 'expected_version' => 1,
    ])->assertStatus(409)
        ->assertJsonPath('code', 'MAPPING_VERSION_STALE');

    expect(ConceptMapping::query()->where('mapping_series_key', $seriesKey)->count())->toBe(1);
});

it('rejects unknown canonical concepts and evidence that does not belong to the source series', function (): void {
    $seriesKey = mappingMutationFixture();

    $response = $this->postJson("/ops/actions/concept-mappings/{$seriesKey}/versions", [
        'source_concept' => 'Assets', 'entry_point' => 'general', 'canonical_concept' => 'does_not_exist',
        'status' => 'DRAFT', 'rationale' => 'Invalid canonical.', 'evidence_ids' => ['EVID-MISSING'],
    ])->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_ERROR')
        ->assertJsonPath('fieldErrors.canonical_concept.0', 'The selected canonical concept is invalid.');

    expect($response->json('fieldErrors')['evidence_ids.0'][0])->toBe('The selected evidence does not exist.');
});

function mappingMutationFixture(): string
{
    $seriesKey = MappingSeriesKey::from('Assets', 'general');

    CanonicalConcept::query()->create([
        'code' => 'total_assets', 'name' => 'Total assets', 'statement' => 'BALANCE_SHEET',
        'period_type' => 'INSTANT', 'sign_convention' => 'AS_REPORTED',
    ]);
    Filing::query()->create([
        'filing_id' => 'FIL-MUT-001', 'issuer_code' => 'HSSA', 'fiscal_year' => 2025, 'fiscal_period' => 'FY',
        'period_end' => '2025-12-31', 'source_url' => 'https://example.test/mutation', 'source_type' => 'XBRL_INSTANCE',
        'source_hash' => str_repeat('m', 64), 'revision_number' => 1, 'taxonomy_entry_point' => 'general',
    ]);

    return $seriesKey;
}
