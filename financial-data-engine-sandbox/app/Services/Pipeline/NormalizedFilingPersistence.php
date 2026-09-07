<?php

namespace App\Services\Pipeline;

use App\Domain\FinancialData\Normalization\NormalizationOutcome;
use App\Models\AuditLog;
use App\Models\Filing;
use App\Models\NormalizedFact;
use App\Models\RawFact;

final class NormalizedFilingPersistence
{
    /**
     * @param  list<array{rawFact: RawFact, outcome: NormalizationOutcome}>  $records
     * @return array{normalized_count: int, review_count: int}
     */
    public function persist(Filing $filing, array $records, string $normalizationVersion, ?string $correlationId = null): array
    {
        $normalizedCount = 0;
        $reviewCount = 0;

        foreach ($records as $record) {
            $rawFact = $record['rawFact'];
            $outcome = $record['outcome'];

            if ($outcome->isNormalized()) {
                NormalizedFact::query()->firstOrCreate(
                    ['normalized_fact_id' => $this->normalizedFactId($filing, $rawFact, $outcome, $normalizationVersion)],
                    [
                        'filing_id' => $filing->filing_id,
                        'raw_fact_id' => $rawFact->raw_fact_id,
                        'issuer_code' => $filing->issuer_code,
                        'period' => $outcome->period,
                        'period_start' => $outcome->periodStart,
                        'period_end' => $outcome->periodEnd,
                        'canonical_concept' => $outcome->canonicalConcept,
                        'value' => $outcome->value,
                        'currency' => $outcome->currency,
                        'scope' => $outcome->scope,
                        'data_type' => 'REPORTED',
                        'source_concept' => $outcome->sourceConcept,
                        'mapping_rule_id' => $outcome->mappingRuleId,
                        'mapping_rule_version' => $outcome->mappingRuleVersion,
                        'normalization_version' => $normalizationVersion,
                        'normalization_status' => 'NORMALIZED',
                        'validation_status' => 'PENDING',
                    ],
                );
                $normalizedCount++;

                continue;
            }

            if ($outcome->isReviewable()) {
                $reviewCount++;
                $this->recordReview($filing, $rawFact, $outcome, $normalizationVersion, $correlationId);
            }
        }

        return [
            'normalized_count' => $normalizedCount,
            'review_count' => $reviewCount,
        ];
    }

    private function normalizedFactId(Filing $filing, RawFact $rawFact, NormalizationOutcome $outcome, string $normalizationVersion): string
    {
        return 'nf_'.substr(hash('sha256', implode('|', [
            $filing->filing_id,
            $rawFact->raw_fact_id,
            (string) $outcome->mappingRuleId,
            (string) $outcome->mappingRuleVersion,
            $normalizationVersion,
            $outcome->exceptionRuleVersion,
        ])), 0, 48);
    }

    private function recordReview(
        Filing $filing,
        RawFact $rawFact,
        NormalizationOutcome $outcome,
        string $normalizationVersion,
        ?string $correlationId,
    ): void {
        $issueId = 'normalization_issue_'.substr(hash('sha256', implode('|', [
            $filing->filing_id,
            $rawFact->raw_fact_id,
            $normalizationVersion,
            $outcome->status,
            (string) $outcome->reason,
        ])), 0, 40);

        if (AuditLog::query()->where('action', 'normalization.review_required')->where('entity_id', $issueId)->exists()) {
            return;
        }

        AuditLog::query()->create([
            'actor_id' => 'system',
            'action' => 'normalization.review_required',
            'entity_type' => RawFact::class,
            'entity_id' => $issueId,
            'new_value' => [
                'filing_id' => $filing->filing_id,
                'raw_fact_id' => $rawFact->raw_fact_id,
                'source_concept' => $rawFact->source_concept,
                'normalization_status' => $outcome->status,
                'normalization_version' => $normalizationVersion,
                'reason' => $outcome->reason,
            ],
            'rationale' => 'The raw fact could not be mapped without an explicit reviewed rule.',
            'filing_id' => $filing->filing_id,
            'correlation_id' => $correlationId,
        ]);
    }
}
