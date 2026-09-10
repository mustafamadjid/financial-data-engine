<?php

namespace App\Http\Middleware;

use App\Http\Responses\Api\V1\IntegrationErrorResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateHissaIntegration
{
    public function handle(Request $request, Closure $next): Response
    {
        $configuredToken = config('integration-api.token');
        $configuredHash = config('integration-api.token_hash');
        $credential = $request->bearerToken();
        $valid = false;

        if (config('integration-api.enabled') && is_string($credential) && $credential !== '') {
            if (is_string($configuredHash) && $configuredHash !== '') {
                $valid = hash_equals($configuredHash, hash('sha256', $credential));
            } elseif (is_string($configuredToken) && $configuredToken !== '') {
                $valid = hash_equals($configuredToken, $credential);
            }
        }

        if (! $valid) {
            return IntegrationErrorResponse::fromThrowable(
                null,
                $request,
                Response::HTTP_UNAUTHORIZED,
                'UNAUTHENTICATED',
                'Authentication is required.',
            );
        }

        $request->attributes->set('integration_identity', (string) config('integration-api.identity_label'));

        return $next($request);
    }
}
