<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\FinancialData\Integration\IssuerFilingQuery;
use App\Domain\FinancialData\Integration\PublishedSnapshotCursor;
use Illuminate\Foundation\Http\FormRequest;

final class ListIssuerFilingsRequest extends FormRequest
{
    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'report_type' => ['nullable', 'in:ANNUAL,QUARTERLY,INTERIM,OTHER'],
            'fiscal_year' => ['nullable', 'integer', 'between:1900,2100'],
            'fiscal_period' => ['nullable', 'string', 'max:16'],
            'period_end' => ['nullable', 'date_format:Y-m-d'],
            'published_after' => ['nullable', 'date_format:Y-m-d\\TH:i:sP'],
            'limit' => ['nullable'],
            'cursor' => ['nullable', 'string', 'max:2048'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['limit' => PublishedSnapshotCursor::normalizeLimit($this->input('limit'))]);
    }

    public function integrationQuery(string $issuerCode): IssuerFilingQuery
    {
        return new IssuerFilingQuery(
            issuerCode: strtoupper($issuerCode),
            reportType: $this->validated('report_type'),
            fiscalYear: $this->validated('fiscal_year') !== null ? (int) $this->validated('fiscal_year') : null,
            fiscalPeriod: $this->validated('fiscal_period'),
            periodEnd: $this->validated('period_end'),
            publishedAfter: $this->validated('published_after'),
            limit: (int) $this->validated('limit', 25),
            cursor: $this->validated('cursor'),
        );
    }
}
