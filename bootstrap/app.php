
<?php

use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\CheckRole;
use App\Support\Api\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(AssignRequestId::class);

        $middleware->alias([
            'role' => CheckRole::class,
            'permission' => CheckPermission::class,
        ]);

        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->is('api/*')
                ? null
                : route('login')
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*')
                || $request->expectsJson(),
        );

        $exceptions->render(function (
            ValidationException $exception,
            Request $request
        ) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                code: 'VALIDATION_ERROR',
                message: 'Please correct the highlighted fields.',
                status: 422,
                fields: $exception->errors(),
            );
        });

        $exceptions->render(function (
            AuthenticationException $exception,
            Request $request
        ) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                code: 'UNAUTHENTICATED',
                message: 'Authentication is required.',
                status: 401,
            );
        });

        $exceptions->render(function (
            AuthorizationException $exception,
            Request $request
        ) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                code: 'FORBIDDEN',
                message: 'You are not authorized to perform this action.',
                status: 403,
            );
        });

        $exceptions->render(function (
            ModelNotFoundException $exception,
            Request $request
        ) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                code: 'RESOURCE_NOT_FOUND',
                message: 'The requested resource was not found.',
                status: 404,
            );
        });

        $exceptions->render(function (
            HttpExceptionInterface $exception,
            Request $request
        ) {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = $exception->getStatusCode();

            $messages = [
                404 => 'The requested endpoint was not found.',
                405 => 'The requested HTTP method is not allowed.',
                429 => 'Too many requests. Please try again later.',
            ];

            return ApiResponse::error(
                code: 'HTTP_ERROR',
                message: $messages[$status] ?? 'The request could not be processed.',
                status: $status,
            );
        });

        $exceptions->render(function (
            Throwable $exception,
            Request $request
        ) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                code: 'INTERNAL_SERVER_ERROR',
                message: 'An unexpected server error occurred.',
                status: 500,
            );
        });

    })->create();
