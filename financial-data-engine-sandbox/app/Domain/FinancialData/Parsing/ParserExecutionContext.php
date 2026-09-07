<?php

namespace App\Domain\FinancialData\Parsing;

use InvalidArgumentException;

final readonly class ParserExecutionContext
{
    public function __construct(
        public string $filingId,
        public string $sourceHash,
        public string $parserVersion,
        public string $parserConfigVersion,
        public string $correlationId,
        public string $contractVersion,
    ) {
        foreach ([
            'filing identifier' => $filingId,
            'source hash' => $sourceHash,
            'parser version' => $parserVersion,
            'parser configuration version' => $parserConfigVersion,
            'correlation identifier' => $correlationId,
            'contract version' => $contractVersion,
        ] as $label => $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException("A {$label} is required.");
            }
        }
    }
}
