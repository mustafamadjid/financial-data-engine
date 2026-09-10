<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

it('registers exactly four public read-only api v1 routes', function (): void {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_starts_with($route->uri(), 'api/v1/'))
        ->values();

    expect($routes)->toHaveCount(4)
        ->and($routes->pluck('methods')->flatten()->filter(fn (string $method): bool => $method !== 'HEAD')->unique()->all())->toBe(['GET'])
        ->and($routes->every(fn ($route): bool => ! in_array('auth', $route->gatherMiddleware(), true)))->toBeTrue()
        ->and($routes->pluck('uri')->all())->toEqualCanonicalizing([
            'api/v1/filings/{filing_id}',
            'api/v1/snapshots/{snapshot_id}',
            'api/v1/issuers/{issuer_code}/filings',
            'api/v1/filings/{filing_id}/export',
        ]);
});

it('does not expose an unversioned phase 11 alias', function (): void {
    expect(Route::getRoutes()->match(Request::create('/api/filings/FIL-1', 'GET'))->getStatusCode())
        ->toBe(404);
})->throws(NotFoundHttpException::class);
