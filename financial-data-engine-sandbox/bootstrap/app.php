<?php

use App\Domain\FinancialData\Integration\Exceptions\InvalidIntegrationQuery;
use App\Http\Middleware\AssignIntegrationRequestId;
use App\Http\Middleware\AuthenticateHissaIntegration;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Responses\Api\V1\IntegrationErrorResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        api: __DIR__.'/../routes/api.php',
        health: '/up',
    )
    ->withCommands([__DIR__.'/../app/Console/Commands'])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [HandleInertiaRequests::class]);
        $middleware->alias([
            'hissa.integration' => AuthenticateHissaIntegration::class,
            'assign.integration.request.id' => AssignIntegrationRequestId::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/v1/*')) {
                return null;
            }
            if ($exception instanceof ThrottleRequestsException) {
                $response = IntegrationErrorResponse::fromThrowable($exception, $request);
                $retryAfter = $exception->getHeaders()['Retry-After'] ?? null;
                if ($retryAfter !== null) {
                    $response->headers->set('Retry-After', (string) $retryAfter);
                }

                return $response;
            }
            if ($exception instanceof ValidationException || $exception instanceof InvalidIntegrationQuery || $exception instanceof HttpExceptionInterface) {
                return IntegrationErrorResponse::fromThrowable($exception, $request);
            }

            return IntegrationErrorResponse::fromThrowable($exception, $request);
        });
    })->create();
