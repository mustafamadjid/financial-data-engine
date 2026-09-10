<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class AssignApiRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $candidate = (string) $request->header('X-Request-ID');
        $requestId = preg_match('/\A[A-Za-z0-9._:-]{1,128}\z/', $candidate) === 1
            ? $candidate
            : (string) Str::uuid();
        $request->attributes->set('api_request_id', $requestId);

        $startedAt = microtime(true);
        $response = $next($request);
        $response->headers->set('X-Request-ID', $requestId);
        $route = $request->route();
        $pathSegments = array_values(array_filter(explode('/', trim($request->path(), '/'))));
        Log::info('public.api.request', [
            'route_name' => $route?->getName(),
            'status_code' => $response->getStatusCode(),
            'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'request_id' => $requestId,
            'filing_id' => $route?->parameter('filing_id') ?? ($pathSegments[3] ?? null),
            'snapshot_id' => $route?->parameter('snapshot_id') ?? ($pathSegments[3] ?? null),
        ]);

        return $response;
    }
}
