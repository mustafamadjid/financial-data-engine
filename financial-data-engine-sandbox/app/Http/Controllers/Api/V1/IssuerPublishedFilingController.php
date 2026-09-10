<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\FinancialData\Integration\Exceptions\InvalidIntegrationIdentifier;
use App\Domain\FinancialData\Integration\PublishedSnapshotSelector;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListIssuerFilingsRequest;
use App\Http\Resources\Api\V1\PublishedFilingCollection;

final class IssuerPublishedFilingController extends Controller
{
    public function __construct(private readonly PublishedSnapshotSelector $selector) {}

    public function index(ListIssuerFilingsRequest $request, string $issuerCode): PublishedFilingCollection
    {
        if (preg_match('/\A[A-Z0-9.-]{1,32}\z/', $issuerCode) !== 1) {
            throw new InvalidIntegrationIdentifier;
        }

        return new PublishedFilingCollection($this->selector->forIssuer($request->integrationQuery($issuerCode)));
    }
}
