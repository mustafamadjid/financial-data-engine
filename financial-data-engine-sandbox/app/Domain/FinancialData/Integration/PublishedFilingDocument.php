<?php

namespace App\Domain\FinancialData\Integration;

final readonly class PublishedFilingDocument
{
    /** @param array<string, mixed> $document */
    public function __construct(private array $document) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->document;
    }

    public function canonicalJson(): string
    {
        return json_encode(
            $this->document,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    public function etag(): string
    {
        return 'sha256-'.hash('sha256', $this->canonicalJson());
    }
}
