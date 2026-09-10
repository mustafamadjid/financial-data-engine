<?php

use App\Http\Middleware\AssignApiRequestId;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

it('uses the public API contract and rate limit', function (): void {
    expect(config('api.contract'))->toBe('hissa.financial-data.integration')
        ->and(config('api.contract_version'))->toBe('0.1.0')
        ->and(config('api.rate_limit_per_minute'))->toBe(60);
});

it('assigns a safe request id and preserves it on the response', function (): void {
    $request = Request::create('/api/v1/test', 'GET', [], [], [], ['HTTP_X_REQUEST_ID' => 'request-123']);
    $response = (new AssignApiRequestId)->handle($request, fn (Request $request) => response()->json(['ok' => true]));

    expect($response->headers->get('X-Request-ID'))->toBe('request-123')
        ->and($request->attributes->get('api_request_id'))->toBe('request-123');
});

it('logs operational request metadata without credentials or response payloads', function (): void {
    Log::spy();
    $request = Request::create('/api/v1/filings/FIL-LOG', 'GET');
    $response = (new AssignApiRequestId)->handle($request, fn (Request $request) => response()->json(['value' => 'financial-secret']));

    Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context) use ($response): bool {
        return $message === 'public.api.request'
            && $context['status_code'] === $response->status()
            && $context['filing_id'] === 'FIL-LOG'
            && ! isset($context['authorization'])
            && ! isset($context['body']);
    })->once();
});
