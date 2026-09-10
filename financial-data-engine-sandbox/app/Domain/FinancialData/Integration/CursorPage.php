<?php

namespace App\Domain\FinancialData\Integration;

use App\Models\PublishedSnapshot;

final readonly class CursorPage
{
    /** @param list<PublishedSnapshot> $items */
    public function __construct(
        private array $items,
        private ?string $nextCursor,
        private bool $hasMore,
        private int $limit = 25,
    ) {}

    /** @return list<PublishedSnapshot> */
    public function items(): array
    {
        return $this->items;
    }

    public function nextCursor(): ?string
    {
        return $this->nextCursor;
    }

    public function hasMore(): bool
    {
        return $this->hasMore;
    }

    public function limit(): int
    {
        return $this->limit;
    }
}
