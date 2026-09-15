<?php

namespace App\Http\Controllers\Ops;

use App\Application\Ops\ConceptMapping\CreateMappingVersion;
use App\Application\Ops\ConceptMapping\MappingMutationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ops\CreateMappingVersionRequest;
use App\Models\ConceptMapping;
use Illuminate\Http\JsonResponse;

final class CreateMappingVersionController extends Controller
{
    public function __invoke(string $mappingSeries, CreateMappingVersionRequest $request, CreateMappingVersion $createMappingVersion): JsonResponse
    {
        try {
            $mapping = $createMappingVersion->execute($mappingSeries, [
                'source_concept' => (string) $request->validated('source_concept'),
                'entry_point' => $request->validated('entry_point'),
                'canonical_concept' => (string) $request->validated('canonical_concept'),
                'allowed_scope' => $request->validated('allowed_scope'),
                'period_type' => $request->validated('period_type'),
                'sign_convention' => $request->validated('sign_convention'),
                'status' => (string) $request->validated('status'),
                'rationale' => (string) $request->validated('rationale'),
                'evidence_ids' => $request->validated('evidence_ids') ?? [],
                'expected_version' => $request->validated('expected_version'),
            ]);
        } catch (MappingMutationException $exception) {
            $payload = ['code' => $exception->errorCode, 'message' => $exception->getMessage()];
            if ($exception->field !== null) {
                $payload['fieldErrors'] = [$exception->field => [$exception->getMessage()]];
            }

            return response()->json($payload, $exception->status);
        }

        return response()->json(['data' => $this->mappingDto($mapping)], 201);
    }

    /** @return array<string, mixed> */
    private function mappingDto(ConceptMapping $mapping): array
    {
        return [
            'mappingRuleId' => (string) $mapping->mapping_rule_id,
            'mappingSeriesKey' => (string) $mapping->mapping_series_key,
            'sourceConcept' => (string) $mapping->source_concept,
            'entryPoint' => $mapping->entry_point,
            'canonicalConcept' => [
                'code' => (string) $mapping->canonicalConcept->code,
                'name' => (string) $mapping->canonicalConcept->name,
            ],
            'status' => (string) $mapping->status,
            'rationale' => $mapping->rationale,
            'version' => (int) $mapping->rule_version,
            'evidenceIds' => $mapping->evidence_ids ?? [],
            'supersedesMappingRuleId' => $mapping->supersedes_mapping_rule_id,
            'createdBy' => $mapping->created_by,
            'createdAt' => $mapping->created_at?->toIso8601String(),
            'allowedActions' => [
                'edit' => ['allowed' => true, 'reasonCode' => null, 'reason' => null],
                'reprocess' => [
                    'allowed' => $mapping->status === 'APPROVED',
                    'reasonCode' => $mapping->status === 'APPROVED' ? null : 'MAPPING_NOT_APPROVED',
                    'reason' => $mapping->status === 'APPROVED' ? null : 'Only an approved mapping can be reprocessed.',
                ],
            ],
        ];
    }
}
