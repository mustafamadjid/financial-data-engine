<?php

namespace App\Http\Responses\Api\V1;

use App\Domain\FinancialData\Integration\Exceptions\IntegrationContractViolation;
use App\Domain\FinancialData\Integration\Exceptions\InvalidIntegrationIdentifier;
use App\Domain\FinancialData\Integration\Exceptions\InvalidIntegrationQuery;
use App\Domain\FinancialData\Integration\Exceptions\PublishedFilingNotFound;
use App\Domain\FinancialData\Integration\Exceptions\PublishedSnapshotNotFound;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

final class IntegrationErrorResponse
{
    public static function fromThrowable(
        ?Throwable $exception,
        Request $request,
        int $status = 500,
        ?string $code = null,
        string $message = 'An internal integration error occurred.',
        array $details = [],
    ): JsonResponse {
        [$resolvedStatus, $resolvedCode, $resolvedMessage] = self::resolve($exception, $status, $code, $message);
        $requestId = (string) ($request->attributes->get('integration_request_id') ?: $request->header('X-Request-ID') ?: 'unknown');
        $response = response()->json([
            'api_version' => 'v1',
            'error' => ['code' => $resolvedCode, 'message' => $resolvedMessage, 'details' => $details],
            'meta' => ['request_id' => $requestId],
        ], $resolvedStatus);
        $response->headers->set('X-Request-ID', $requestId);

        return $response;
    }

    /** @return array{0: int, 1: string, 2: string} */
    private static function resolve(?Throwable $exception, int $status, ?string $code, string $message): array
    {
        if ($code !== null) {
            return [$status, $code, $message];
        }
        if ($exception instanceof PublishedFilingNotFound) {
            return [404, 'PUBLISHED_FILING_NOT_FOUND', 'Published filing was not found.'];
        }
        if ($exception instanceof PublishedSnapshotNotFound) {
            return [404, 'PUBLISHED_SNAPSHOT_NOT_FOUND', 'Published snapshot was not found.'];
        }
        if ($exception instanceof InvalidIntegrationQuery || $exception instanceof ValidationException) {
            return [422, 'INVALID_QUERY', 'The integration query is invalid.'];
        }
        if ($exception instanceof InvalidIntegrationIdentifier) {
            return [422, 'INVALID_IDENTIFIER', 'The integration identifier is invalid.'];
        }
        if ($exception instanceof TooManyRequestsHttpException) {
            return [429, 'RATE_LIMITED', 'Too many requests.'];
        }
        if ($exception instanceof IntegrationContractViolation) {
            return [500, 'INTEGRATION_CONTRACT_VIOLATION', 'The published snapshot does not satisfy the integration contract.'];
        }

        return [500, 'INTERNAL_ERROR', 'An internal integration error occurred.'];
    }
}
