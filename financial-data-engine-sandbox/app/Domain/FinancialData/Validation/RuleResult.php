<?php

namespace App\Domain\FinancialData\Validation;

use InvalidArgumentException;

final readonly class RuleResult
{
    /**
     * @param  list<string>  $normalizedFactIds
     */
    public function __construct(
        public string $result,
        public string $severity,
        public ?string $message = null,
        public ?string $expectedValue = null,
        public ?string $actualValue = null,
        public ?string $tolerance = null,
        public array $normalizedFactIds = [],
    ) {
        if (! in_array($result, ['PASS', 'FAIL', 'REVIEW_REQUIRED', 'SKIPPED'], true)) {
            throw new InvalidArgumentException('An unsupported validation result was provided.');
        }

        if (! in_array($severity, ['ERROR', 'WARN', 'INFO'], true)) {
            throw new InvalidArgumentException('An unsupported validation severity was provided.');
        }

        foreach ($normalizedFactIds as $normalizedFactId) {
            if (! is_string($normalizedFactId) || trim($normalizedFactId) === '') {
                throw new InvalidArgumentException('Validation fact lineage must contain non-empty identifiers.');
            }
        }
    }
}
