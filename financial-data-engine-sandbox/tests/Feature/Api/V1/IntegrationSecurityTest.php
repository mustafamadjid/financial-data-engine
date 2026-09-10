<?php

use App\Http\Middleware\AssignIntegrationRequestId;
use App\Http\Middleware\AuthenticateHissaIntegration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

it('uses the configured draft identity and rate limit', function (): void {
    expect(config('integration-api.contract'))->toBe('hissa.financial-data.integration')
        ->and(config('integration-api.contract_version'))->toBe('0.1.0')
        ->and(config('integration-api.rate_limit_per_minute'))->toBe(60)
        ->and(config('integration-api.enabled'))->toBeTrue();
});

it('treats missing invalid and valid credentials through the authentication boundary', function (): void {
    config([
        'integration-api.enabled' => true,
        'integration-api.token' => 'secret-token',
        'integration-api.identity_label' => 'hissa-core-test',
    ]);
    $middleware = new AuthenticateHissaIntegration;
    $next = fn (Request $request) => response()->json(['ok' => true]);

    $missing = $middleware->handle(Request::create('/api/v1/test', 'GET'), $next);
    $invalid = $middleware->handle(Request::create('/api/v1/test', 'GET', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer wrong']), $next);
    $valid = $middleware->handle(Request::create('/api/v1/test', 'GET', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer secret-token']), $next);

    expect($missing->status())->toBe(401)
        ->and($invalid->status())->toBe(401)
        ->and($valid->status())->toBe(200)
        ->and($valid->getData(true))->toBe(['ok' => true]);
});

it('assigns a safe request id and preserves it on the response', function (): void {
    $request = Request::create('/api/v1/test', 'GET', [], [], [], ['HTTP_X_REQUEST_ID' => 'request-123']);
    $response = (new AssignIntegrationRequestId)->handle($request, fn (Request $request) => response()->json(['ok' => true]));

    expect($response->headers->get('X-Request-ID'))->toBe('request-123')
        ->and($request->attributes->get('integration_request_id'))->toBe('request-123');
});

it('logs operational request metadata without credentials or response payloads', function (): void {
    Log::spy();
    $request = Request::create('/api/v1/filings/FIL-LOG', 'GET');
    $request->attributes->set('integration_identity', 'hissa-core-test');
    $response = (new AssignIntegrationRequestId)->handle($request, fn (Request $request) => response()->json(['value' => 'financial-secret']));

    Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context) use ($response): bool {
        return $message === 'hissa.integration.request'
            && $context['status_code'] === $response->status()
            && $context['integration_identity'] === 'hissa-core-test'
            && $context['filing_id'] === 'FIL-LOG'
            && ! isset($context['authorization'])
            && ! isset($context['body']);
    })->once();
});
