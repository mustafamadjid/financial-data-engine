<?php

namespace App\Application\Ops\ConceptMapping;

use RuntimeException;

final class MappingMutationException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 422,
        public readonly ?string $field = null,
    ) {
        parent::__construct($message);
    }
}
