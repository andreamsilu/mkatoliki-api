<?php

use App\Http\Middleware\ApiRequestContext;
use App\Http\Middleware\RequireActiveAdministrator;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', api: __DIR__.'/../routes/api.php', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [ApiRequestContext::class]);
        $middleware->alias(['administrator' => RequireActiveAdministrator::class]);
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());
        $exceptions->report(function (QueryException $exception): bool {
            \Illuminate\Support\Facades\Log::error('database.query_failed', ['sqlstate' => $exception->getCode(), 'request_id' => request()->attributes->get('request_id')]);

            return false;
        });
        $exceptions->dontFlash(['password', 'password_confirmation', 'token']);
        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }
            if ($exception instanceof ValidationException) {
                return ApiResponse::error('VALIDATION_ERROR', 'The submitted data is invalid.', 422, $exception->errors());
            }
            if ($exception instanceof AuthenticationException) {
                return ApiResponse::error('UNAUTHENTICATED', 'Authentication is required.', 401);
            }
            if ($exception instanceof AuthorizationException) {
                return ApiResponse::error('FORBIDDEN', 'You are not authorized to perform this action.', 403);
            }
            if ($exception instanceof QueryException) {
                return in_array((string) $exception->getCode(), ['23000', '23505'], true)
                    ? ApiResponse::error('DATA_CONFLICT', 'The change conflicts with an existing record or relationship.', 409)
                    : ApiResponse::error('SERVICE_UNAVAILABLE', 'The database is temporarily unavailable.', 503);
            }
            $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;
            $code = match ($status) {
                400 => 'BAD_REQUEST', 401 => 'UNAUTHENTICATED', 403 => 'FORBIDDEN', 404 => 'NOT_FOUND',
                405 => 'METHOD_NOT_ALLOWED', 409 => 'CONFLICT', 413 => 'PAYLOAD_TOO_LARGE', 429 => 'RATE_LIMIT_EXCEEDED',
                503 => 'SERVICE_UNAVAILABLE', default => 'INTERNAL_ERROR',
            };
            if ($status === 404 && $request->route('entity')) {
                $code = strtoupper(Str::singular($request->route('entity'))).'_NOT_FOUND';
            }
            $message = match ($status) {
                401 => 'Authentication is required.', 403 => 'You are not authorized to perform this action.',
                404 => 'The requested resource was not found.', 405 => 'This HTTP method is not supported.',
                409 => 'The request conflicts with the current state.', 413 => 'The request is too large.',
                429 => 'Too many requests. Please try again later.', 503 => 'The service is temporarily unavailable.',
                default => 'An unexpected error occurred.',
            };
            $response = ApiResponse::error($code, $message, $status);
            if ($exception instanceof HttpExceptionInterface) {
                $response->headers->add($exception->getHeaders());
            }

            return $response;
        });
    })->create();
