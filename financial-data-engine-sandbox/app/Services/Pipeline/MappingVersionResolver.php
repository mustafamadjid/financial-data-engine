<?php

namespace App\Services\Pipeline;

use App\Domain\FinancialData\Mapping\MappingSeriesKey;
use App\Models\ConceptMapping;

final class MappingVersionResolver
{
    public function resolve(): string
    {
        $configuredVersion = trim((string) config('financial-pipeline.normalization.mapping_version', ''));

        if ($configuredVersion !== '') {
            return $configuredVersion;
        }

        $mappings = ConceptMapping::query()
            ->where('status', 'APPROVED')
            ->orderBy('source_concept')
            ->orderBy('rule_version')
            ->orderBy('mapping_rule_id')
            ->get(['source_concept', 'entry_point', 'mapping_series_key', 'mapping_rule_id', 'rule_version'])
            ->groupBy(fn (ConceptMapping $mapping): string => (string) ($mapping->mapping_series_key ?: MappingSeriesKey::from((string) $mapping->source_concept, $mapping->entry_point)))
            ->map(function ($group): array {
                $latestVersion = $group->max('rule_version');

                return $group->where('rule_version', $latestVersion)->values()->all();
            });
        $identity = [];

        foreach ($mappings as $sourceConcept => $sourceMappings) {
            foreach ($sourceMappings as $mapping) {
                $identity[] = implode(':', [$sourceConcept, $mapping->mapping_rule_id, $mapping->rule_version]);
            }
        }

        sort($identity);

        return $identity === [] ? 'empty' : 'set_'.substr(hash('sha256', implode('|', $identity)), 0, 32);
    }
}
