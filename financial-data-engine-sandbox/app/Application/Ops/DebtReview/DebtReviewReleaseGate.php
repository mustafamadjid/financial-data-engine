<?php

namespace App\Application\Ops\DebtReview;

final class DebtReviewReleaseGate
{
    public function enabled(): bool
    {
        $config = (array) config('financial-pipeline.debt_review', []);

        return $config['enabled'] === true
            && is_string($config['approved_da4_rule_version'] ?? null)
            && trim($config['approved_da4_rule_version']) !== ''
            && is_string($config['approved_dictionary_version'] ?? null)
            && trim($config['approved_dictionary_version']) !== ''
            && $config['evidence_resolution'] === true
            && $config['pipeline_stable'] === true
            && $config['page_design_approved'] === true;
    }

    public function assertEnabled(): void
    {
        if (! $this->enabled()) {
            throw new DebtReviewGateException;
        }
    }
}
