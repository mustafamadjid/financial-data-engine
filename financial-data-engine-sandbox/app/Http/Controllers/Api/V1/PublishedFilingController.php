<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\FinancialData\Integration\Exceptions\InvalidIntegrationIdentifier;
use App\Domain\FinancialData\Integration\PublishedFilingDocumentFactory;
use App\Domain\FinancialData\Integration\PublishedSnapshotSelector;
use App\Http\Controllers\Controller;
use App\Http\Responses\Api\V1\CanonicalJsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class PublishedFilingController extends Controller
{
    public function __construct(
        private readonly PublishedSnapshotSelector $selector,
        private readonly PublishedFilingDocumentFactory $factory,
    ) {}

    public function show(Request $request, string $filingId): CanonicalJsonResponse|Response
    {
        $this->assertIdentifier($filingId);

        return CanonicalJsonResponse::fromDocument(
            $this->factory->make($this->selector->latestForFiling($filingId)),
            $request,
        );
    }

    public function export(Request $request, string $filingId): Response
    {
        $this->assertIdentifier($filingId);
        $document = $this->factory->make($this->selector->latestForFiling($filingId));
        $data = $document->toArray();
        $filename = $this->safeFilename(
            (string) $data['filing']['issuer_code'],
            (string) $data['filing']['filing_id'],
            (int) $data['filing']['revision_number'],
            (string) $data['snapshot']['snapshot_id'],
        );

        return CanonicalJsonResponse::fromDocument($document, $request, 'attachment', $filename);
    }

    private function assertIdentifier(string $identifier): void
    {
        if (preg_match('/\A[A-Za-z0-9._-]{1,128}\z/', $identifier) !== 1) {
            throw new InvalidIntegrationIdentifier;
        }
    }

    private function safeFilename(string $issuer, string $filingId, int $revision, string $snapshotId): string
    {
        $parts = array_map(fn (string $part): string => preg_replace('/[^A-Za-z0-9._-]+/', '-', $part) ?: 'unknown', [$issuer, $filingId, 'r'.$revision, $snapshotId, 'v'.config('api.contract_version', '0.1.0')]);

        return implode('-', $parts).'.json';
    }
}
