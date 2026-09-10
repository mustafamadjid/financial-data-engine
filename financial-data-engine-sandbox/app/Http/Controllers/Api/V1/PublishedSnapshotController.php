<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\FinancialData\Integration\Exceptions\InvalidIntegrationIdentifier;
use App\Domain\FinancialData\Integration\PublishedFilingDocumentFactory;
use App\Domain\FinancialData\Integration\PublishedSnapshotSelector;
use App\Http\Controllers\Controller;
use App\Http\Responses\Api\V1\CanonicalJsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class PublishedSnapshotController extends Controller
{
    public function __construct(
        private readonly PublishedSnapshotSelector $selector,
        private readonly PublishedFilingDocumentFactory $factory,
    ) {}

    public function show(Request $request, string $snapshotId): Response
    {
        if (preg_match('/\A[A-Za-z0-9._-]{1,128}\z/', $snapshotId) !== 1) {
            throw new InvalidIntegrationIdentifier;
        }

        return CanonicalJsonResponse::fromDocument(
            $this->factory->make($this->selector->exactSnapshot($snapshotId)),
            $request,
        );
    }
}
