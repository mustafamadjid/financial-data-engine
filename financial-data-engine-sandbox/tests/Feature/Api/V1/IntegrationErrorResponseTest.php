<?php

use App\Domain\FinancialData\Integration\Exceptions\InvalidIntegrationQuery;
use App\Domain\FinancialData\Integration\Exceptions\PublishedFilingNotFound;
use App\Http\Responses\Api\V1\IntegrationErrorResponse;
use Illuminate\Http\Request;

it('renders stable safe error envelopes for typed integration failures', function (string $exception, int $status, string $code): void {
    $request = Request::create('/api/v1/test', 'GET');
    $request->attributes->set('integration_request_id', 'request-error-1');
    $response = IntegrationErrorResponse::fromThrowable(new $exception, $request, $status, $code);
    $body = $response->getData(true);

    expect($response->status())->toBe($status)
        ->and($body)->toHaveKeys(['api_version', 'error', 'meta'])
        ->and($body['error']['code'])->toBe($code)
        ->and($body['meta']['request_id'])->toBe('request-error-1')
        ->and(json_encode($body))->not->toContain('RuntimeException')
        ->and(json_encode($body))->not->toContain('stack');
})->with([
    [PublishedFilingNotFound::class, 404, 'PUBLISHED_FILING_NOT_FOUND'],
    [InvalidIntegrationQuery::class, 422, 'INVALID_QUERY'],
]);
