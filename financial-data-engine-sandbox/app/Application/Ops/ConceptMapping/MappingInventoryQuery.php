<?php

namespace App\Application\Ops\ConceptMapping;

use App\Domain\FinancialData\Mapping\MappingSeriesKey;
use App\Models\CanonicalConcept;
use App\Models\ConceptMapping;
use App\Models\Filing;
use App\Models\RawFact;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;

final class MappingInventoryQuery
{
    /** @param array<string, mixed> $filters */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $rows = $this->inventoryRows();
        $search = strtolower(trim((string) ($filters['search'] ?? '')));

        if ($search !== '') {
            $rows = $rows->filter(function (array $row) use ($search): bool {
                $haystack = strtolower(implode(' ', [
                    $row['sourceConcept'],
                    (string) $row['entryPoint'],
                    $row['displayStatus'],
                    (string) ($row['currentMapping']['canonicalConcept']['code'] ?? ''),
                    (string) ($row['currentMapping']['canonicalConcept']['name'] ?? ''),
                ]));

                return str_contains($haystack, $search);
            });
        }

        if (($filters['status'] ?? null) !== null) {
            $rows = $rows->where('displayStatus', $filters['status']);
        }

        if (($filters['entry_point'] ?? null) !== null) {
            $rows = $rows->where('entryPoint', $filters['entry_point']);
        }

        $rows = $rows->sortBy([
            ['sourceConcept', 'asc'],
            ['entryPoint', 'asc'],
            ['mappingSeriesKey', 'asc'],
        ])->values();

        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = (int) ($filters['per_page'] ?? 25);

        return new Paginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => request()->url()],
        );
    }

    /** @return Collection<int, array<string, mixed>> */
    public function inventoryRows(): Collection
    {
        $mappings = ConceptMapping::query()
            ->with('canonicalConcept')
            ->orderByDesc('rule_version')
            ->orderBy('mapping_rule_id')
            ->get();
        $rawSeries = RawFact::query()
            ->join('filings', 'filings.filing_id', '=', 'raw_facts.filing_id')
            ->select(['raw_facts.source_concept', 'filings.taxonomy_entry_point'])
            ->distinct()
            ->get();

        /** @var Collection<string, EloquentCollection<int, ConceptMapping>> $mappingGroups */
        $mappingGroups = $mappings->groupBy(fn (ConceptMapping $mapping): string => $this->seriesKey($mapping));
        $series = collect();

        foreach ($mappingGroups as $seriesKey => $group) {
            /** @var ConceptMapping $current */
            $current = $group->sort(function (ConceptMapping $left, ConceptMapping $right): int {
                return $right->rule_version <=> $left->rule_version ?: strcmp((string) $left->mapping_rule_id, (string) $right->mapping_rule_id);
            })->first();
            $series->put($seriesKey, [
                'mappingSeriesKey' => $seriesKey,
                'sourceConcept' => (string) $current->source_concept,
                'entryPoint' => $current->entry_point,
                'currentMapping' => $this->mappingDto($current),
            ]);
        }

        foreach ($rawSeries as $raw) {
            $sourceConcept = (string) $raw->source_concept;
            $entryPoint = $raw->taxonomy_entry_point === null ? null : (string) $raw->taxonomy_entry_point;
            $seriesKey = MappingSeriesKey::from($sourceConcept, $entryPoint);

            if ($series->has($seriesKey)) {
                continue;
            }

            $series->put($seriesKey, [
                'mappingSeriesKey' => $seriesKey,
                'sourceConcept' => $sourceConcept,
                'entryPoint' => $entryPoint,
                'currentMapping' => null,
            ]);
        }

        return $series->map(function (array $row): array {
            $row['displayStatus'] = $row['currentMapping']['status'] ?? 'UNMAPPED';
            $row['affectedFilingCount'] = $this->affectedFilingCount(
                (string) $row['sourceConcept'],
                $row['entryPoint'],
                $row['currentMapping'],
            );
            $row['allowedActions'] = [
                'edit' => $this->capability(true),
                'viewHistory' => $this->capability(true),
                'previewImpact' => $this->capability($row['currentMapping'] !== null, $row['currentMapping'] === null ? 'UNMAPPED_SERIES' : null, $row['currentMapping'] === null ? 'Create a mapping version before previewing impact.' : null),
                'reprocess' => $this->capability(($row['currentMapping']['status'] ?? null) === 'APPROVED', ($row['currentMapping']['status'] ?? null) === 'APPROVED' ? null : 'MAPPING_NOT_APPROVED', ($row['currentMapping']['status'] ?? null) === 'APPROVED' ? null : 'Only an approved mapping can be reprocessed.'),
            ];

            return $row;
        })->values();
    }

    /** @return LengthAwarePaginator<ConceptMapping> */
    public function history(string $mappingSeriesKey, int $page = 1, int $perPage = 25): LengthAwarePaginator
    {
        $mappings = ConceptMapping::query()
            ->with('canonicalConcept')
            ->get()
            ->filter(fn (ConceptMapping $mapping): bool => $this->seriesKey($mapping) === $mappingSeriesKey)
            ->sort(function (ConceptMapping $left, ConceptMapping $right): int {
                return $right->rule_version <=> $left->rule_version ?: strcmp((string) $left->mapping_rule_id, (string) $right->mapping_rule_id);
            })
            ->values();

        return new Paginator(
            $mappings->forPage(max(1, $page), $perPage)->values(),
            $mappings->count(),
            $perPage,
            max(1, $page),
            ['path' => request()->url()],
        );
    }

    /** @return Collection<int, array{code: string, name: string}> */
    public function canonicalOptions(?string $search = null, int $limit = 50): Collection
    {
        return CanonicalConcept::query()
            ->when($search !== null && trim($search) !== '', function ($query) use ($search): void {
                $term = '%'.trim((string) $search).'%';
                $query->where(fn ($nested) => $nested->where('code', 'like', $term)->orWhere('name', 'like', $term));
            })
            ->orderBy('code')
            ->limit(min(max($limit, 1), 100))
            ->get(['code', 'name'])
            ->map(fn (CanonicalConcept $concept): array => ['code' => (string) $concept->code, 'name' => (string) $concept->name])
            ->values();
    }

    /** @return array<string, mixed>|null */
    public function impact(string $mappingSeriesKey, ?int $version = null, int $page = 1, int $perPage = 25): ?array
    {
        $mapping = ConceptMapping::query()
            ->with('canonicalConcept')
            ->get()
            ->filter(fn (ConceptMapping $candidate): bool => $this->seriesKey($candidate) === $mappingSeriesKey)
            ->when($version !== null, fn (Collection $items): Collection => $items->where('rule_version', $version))
            ->sort(function (ConceptMapping $left, ConceptMapping $right): int {
                return $right->rule_version <=> $left->rule_version ?: strcmp((string) $left->mapping_rule_id, (string) $right->mapping_rule_id);
            })
            ->first();

        if (! $mapping instanceof ConceptMapping) {
            return null;
        }

        $facts = RawFact::query()
            ->with(['filing', 'context'])
            ->where('source_concept', $mapping->source_concept)
            ->get()
            ->filter(fn (RawFact $fact): bool => $this->mappingApplies($fact, $mapping))
            ->groupBy('filing_id')
            ->map(fn (EloquentCollection $filingFacts): Filing => $filingFacts->first()->filing)
            ->sortBy(fn (Filing $filing): string => (string) $filing->filing_id)
            ->values();

        $paginator = new Paginator(
            $facts->forPage(max(1, $page), $perPage)->values(),
            $facts->count(),
            $perPage,
            max(1, $page),
            ['path' => request()->url()],
        );

        return [
            'mappingSeriesKey' => $mappingSeriesKey,
            'mappingSetVersion' => (int) $mapping->rule_version,
            'selectedMapping' => $this->mappingDto($mapping),
            'affectedFilingCount' => $facts->count(),
            'filings' => $paginator->getCollection()->map(fn (Filing $filing): array => [
                'filingId' => (string) $filing->filing_id,
                'issuerCode' => (string) $filing->issuer_code,
                'fiscalPeriod' => $filing->fiscal_period,
                'revisionNumber' => (int) $filing->revision_number,
                'periodEnd' => $filing->period_end?->toDateString(),
                'processingStage' => $filing->processing_stage,
            ])->values()->all(),
            'meta' => [
                'currentPage' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'lastPage' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
            'stageChain' => ['NORMALIZE', 'VALIDATE', 'PUBLISH'],
            'upstream' => [
                'rawFactsReused' => true,
                'parsedArtifactsReused' => true,
                'priorNormalizedFactsPreserved' => true,
                'automaticReprocess' => false,
            ],
            'allowedActions' => [
                'reprocess' => $this->capability((string) $mapping->status === 'APPROVED', (string) $mapping->status === 'APPROVED' ? null : 'MAPPING_NOT_APPROVED', (string) $mapping->status === 'APPROVED' ? null : 'Only an approved mapping can be reprocessed.'),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function mappingDto(ConceptMapping $mapping): array
    {
        return [
            'mappingRuleId' => (string) $mapping->mapping_rule_id,
            'mappingSeriesKey' => $this->seriesKey($mapping),
            'canonicalConcept' => [
                'code' => (string) $mapping->canonicalConcept->code,
                'name' => (string) $mapping->canonicalConcept->name,
            ],
            'status' => (string) $mapping->status,
            'rationale' => $mapping->rationale,
            'reviewer' => $mapping->reviewer,
            'version' => (int) $mapping->rule_version,
            'sourceConcept' => (string) $mapping->source_concept,
            'entryPoint' => $mapping->entry_point,
            'allowedScope' => $mapping->allowed_scope,
            'periodType' => $mapping->period_type,
            'signConvention' => $mapping->sign_convention,
            'evidenceIds' => $mapping->evidence_ids ?? [],
            'createdBy' => $mapping->created_by,
            'supersedesMappingRuleId' => $mapping->supersedes_mapping_rule_id,
            'createdAt' => $mapping->created_at?->toIso8601String(),
        ];
    }

    /** @param array<string, mixed>|null $mapping */
    private function affectedFilingCount(string $sourceConcept, ?string $entryPoint, ?array $mapping): int
    {
        return RawFact::query()
            ->with(['filing', 'context'])
            ->where('source_concept', $sourceConcept)
            ->get()
            ->filter(function (RawFact $fact) use ($entryPoint, $mapping): bool {
                if ($entryPoint !== null && $fact->filing?->taxonomy_entry_point !== $entryPoint) {
                    return false;
                }

                if ($mapping === null) {
                    return true;
                }

                $allowedScope = $mapping['allowedScope'] ?? null;
                if (is_array($allowedScope) && $allowedScope !== [] && ! in_array($fact->context?->scope, $allowedScope, true)) {
                    return false;
                }

                return $mapping['periodType'] === null || $mapping['periodType'] === $fact->context?->period_type;
            })
            ->pluck('filing_id')
            ->unique()
            ->count();
    }

    private function mappingApplies(RawFact $fact, ConceptMapping $mapping): bool
    {
        if ($mapping->entry_point !== null && $mapping->entry_point !== '' && $fact->filing?->taxonomy_entry_point !== $mapping->entry_point) {
            return false;
        }

        $allowedScope = $mapping->allowed_scope;
        if (is_array($allowedScope) && $allowedScope !== [] && ! in_array($fact->context?->scope, $allowedScope, true)) {
            return false;
        }

        return $mapping->period_type === null || $mapping->period_type === $fact->context?->period_type;
    }

    private function seriesKey(ConceptMapping $mapping): string
    {
        return (string) ($mapping->mapping_series_key ?: MappingSeriesKey::from((string) $mapping->source_concept, $mapping->entry_point));
    }

    /** @return array{allowed: bool, reasonCode: string|null, reason: string|null} */
    private function capability(bool $allowed, ?string $reasonCode = null, ?string $reason = null): array
    {
        return ['allowed' => $allowed, 'reasonCode' => $reasonCode, 'reason' => $reason];
    }
}
