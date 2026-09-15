<?php

namespace App\Http\Controllers\Ops;

use App\Application\Ops\ConceptMapping\MappingInventoryQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ops\ConceptMappingHistoryRequest;
use App\Http\Requests\Ops\ConceptMappingImpactRequest;
use App\Http\Requests\Ops\ConceptMappingListRequest;
use Illuminate\Http\JsonResponse;

final class ConceptMappingDataController extends Controller
{
    public function index(ConceptMappingListRequest $request, MappingInventoryQuery $query): JsonResponse
    {
        $paginator = $query->paginate($request->filters());

        return response()->json([
            'data' => $paginator->getCollection()->values()->all(),
            'meta' => [
                'currentPage' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'lastPage' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function history(string $mappingSeries, ConceptMappingHistoryRequest $request, MappingInventoryQuery $query): JsonResponse
    {
        $paginator = $query->history($mappingSeries, ...array_values($request->pagination()));

        return response()->json([
            'mappingSeriesKey' => $mappingSeries,
            'data' => $paginator->getCollection()->map(fn ($mapping): array => [
                'mappingRuleId' => (string) $mapping->mapping_rule_id,
                'mappingSeriesKey' => (string) ($mapping->mapping_series_key ?: $mappingSeries),
                'sourceConcept' => (string) $mapping->source_concept,
                'entryPoint' => $mapping->entry_point,
                'canonicalConcept' => [
                    'code' => (string) $mapping->canonicalConcept->code,
                    'name' => (string) $mapping->canonicalConcept->name,
                ],
                'status' => (string) $mapping->status,
                'rationale' => $mapping->rationale,
                'reviewer' => $mapping->reviewer,
                'version' => (int) $mapping->rule_version,
                'evidenceIds' => $mapping->evidence_ids ?? [],
                'supersedesMappingRuleId' => $mapping->supersedes_mapping_rule_id,
                'createdBy' => $mapping->created_by,
                'createdAt' => $mapping->created_at?->toIso8601String(),
            ])->values()->all(),
            'meta' => [
                'currentPage' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'lastPage' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function impact(string $mappingSeries, ConceptMappingImpactRequest $request, MappingInventoryQuery $query): JsonResponse
    {
        $filters = $request->filters();
        $impact = $query->impact($mappingSeries, $filters['version'], $filters['page'], $filters['per_page']);

        if ($impact === null) {
            return response()->json([
                'code' => 'MAPPING_SERIES_NOT_FOUND',
                'message' => 'The requested mapping series was not found.',
            ], 404);
        }

        return response()->json(['data' => $impact]);
    }

    public function canonicalOptions(ConceptMappingListRequest $request, MappingInventoryQuery $query): JsonResponse
    {
        return response()->json([
            'data' => $query->canonicalOptions($request->validated('search')),
        ]);
    }
}
