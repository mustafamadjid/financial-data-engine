<?php

namespace App\Domain\FinancialData\Publishing\Exceptions;

use RuntimeException;

final class InvalidPublishContractException extends RuntimeException
{
    /**
     * @param  list<string>  $violations
     */
    public function __construct(array $violations)
    {
        parent::__construct('Publish contract is invalid: '.implode('; ', $violations));
    }
}
