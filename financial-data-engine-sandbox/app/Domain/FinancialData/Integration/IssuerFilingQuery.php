<?php

namespace App\Domain\FinancialData\Integration;

final class IssuerFilingQuery
{
    /** @param array<string, scalar|null> $filters */
    public function __construct(
        public string $issuerCode,
        public ?string $reportType = null,
        public ?int $fiscalYear = null,
        public ?string $fiscalPeriod = null,
        public ?string $periodEnd = null,
        public ?string $publishedAfter = null,
        public int $limit = 25,
        public ?string $cursor = null,
    ) {
        $this->limit = PublishedSnapshotCursor::normalizeLimit($this->limit);
    }

    public function withCursor(?string $cursor): self
    {
        return new self(
            issuerCode: $this->issuerCode,
            reportType: $this->reportType,
            fiscalYear: $this->fiscalYear,
            fiscalPeriod: $this->fiscalPeriod,
            periodEnd: $this->periodEnd,
            publishedAfter: $this->publishedAfter,
            limit: $this->limit,
            cursor: $cursor,
        );
    }

    /** @return array<string, scalar|null> */
    public function filters(): array
    {
        return [
            'issuer_code' => strtoupper($this->issuerCode),
            'report_type' => $this->reportType,
            'fiscal_year' => $this->fiscalYear,
            'fiscal_period' => $this->fiscalPeriod,
            'period_end' => $this->periodEnd,
            'published_after' => $this->publishedAfter,
            'limit' => $this->limit,
        ];
    }
}
