<?php

namespace App\Domain\FinancialData\Validation;

use App\Domain\FinancialData\Pipeline\QualityStatus;

final class FilingQualityAggregator
{
    /**
     * @param  iterable<ValidationEvaluation>  $evaluations
     */
    public function aggregate(iterable $evaluations, QualityStatus $floor = QualityStatus::Pending): QualityStatus
    {
        if ($floor === QualityStatus::Failed) {
            return QualityStatus::Failed;
        }

        $status = $floor === QualityStatus::ReviewRequired
            ? QualityStatus::ReviewRequired
            : QualityStatus::Verified;

        foreach ($evaluations as $evaluation) {
            if (! $evaluation instanceof ValidationEvaluation) {
                continue;
            }

            if ($evaluation->result->result === 'FAIL' && $evaluation->result->severity === 'ERROR') {
                return QualityStatus::Failed;
            }

            if (in_array($evaluation->result->result, ['FAIL', 'REVIEW_REQUIRED'], true)) {
                $status = QualityStatus::ReviewRequired;
            }
        }

        return $status;
    }
}
