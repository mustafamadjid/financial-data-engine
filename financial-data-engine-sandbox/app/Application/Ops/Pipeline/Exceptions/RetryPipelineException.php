<?php

namespace App\Application\Ops\Pipeline\Exceptions;

use RuntimeException;

final class RetryPipelineException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message, public readonly int $status = 409)
    {
        parent::__construct($message);
    }
}
