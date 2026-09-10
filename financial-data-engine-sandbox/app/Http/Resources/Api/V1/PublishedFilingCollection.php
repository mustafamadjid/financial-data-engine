<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\FinancialData\Integration\CursorPage;
use Illuminate\Contracts\Support\Responsable;
use Symfony\Component\HttpFoundation\Response;

final class PublishedFilingCollection implements Responsable
{
    public function __construct(private readonly CursorPage $page) {}

    public function toResponse($request): Response
    {
        $data = array_map(function ($snapshot): array {
            $filing = (array) data_get($snapshot->payload, 'filing');
            $publishedAt = $snapshot->published_at;

            return [
                'snapshot_id' => (string) $snapshot->snapshot_id,
                'filing_id' => (string) $snapshot->filing_id,
                'issuer_code' => (string) ($filing['issuer_code'] ?? ''),
                'report_type' => $filing['report_type'] ?? null,
                'fiscal_year' => isset($filing['fiscal_year']) ? (int) $filing['fiscal_year'] : null,
                'fiscal_period' => $filing['fiscal_period'] ?? null,
                'period_end' => $filing['period_end'] ?? null,
                'revision_number' => (int) $snapshot->revision_number,
                'quality_status' => 'VERIFIED',
                'published_at' => $publishedAt?->toIso8601String(),
                'contract_version' => (string) config('integration-api.contract_version', '0.1.0'),
                'links' => [
                    'detail' => route('api.v1.snapshots.show', ['snapshot_id' => $snapshot->snapshot_id]),
                    'export' => route('api.v1.filings.export', ['filing_id' => $snapshot->filing_id]),
                ],
            ];
        }, $this->page->items());

        return response()->json([
            'api_version' => 'v1',
            'contract' => 'hissa.financial-data.integration-list',
            'contract_version' => (string) config('integration-api.contract_version', '0.1.0'),
            'data' => $data,
            'pagination' => [
                'limit' => $this->page->limit(),
                'next_cursor' => $this->page->nextCursor(),
                'has_more' => $this->page->hasMore(),
            ],
        ])->header('X-Request-ID', (string) $request->attributes->get('integration_request_id', 'unknown'))
            ->header('X-HISSA-Contract-Version', (string) config('integration-api.contract_version', '0.1.0'));
    }
}
