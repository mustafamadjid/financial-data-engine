<?php

namespace App\Domain\FinancialData\Integration\Exceptions;

use RuntimeException;

final class IntegrationContractViolation extends RuntimeException
{
    public function __construct(private readonly string $violationCode)
    {
        parent::__construct('Integration contract validation failed.');
    }

    public function violationCode(): string
    {
        return $this->violationCode;
    }
}
