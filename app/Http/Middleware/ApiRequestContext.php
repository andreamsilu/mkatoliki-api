<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ApiRequestContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $start = hrtime(true);
        $requestId = (string) Str::uuid();
        $request->attributes->set('request_id', $requestId);
        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $handler = app(ExceptionHandler::class);
            $handler->report($exception);
            $response = $handler->render($request, $exception);
        }
        $response->headers->set('X-Request-ID', $requestId);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Cache-Control', 'no-store, private');
        $privateReading = $request->is('api/v1/content/*', 'api/v1/me/bookmarks*', 'api/v1/parishes/*/reports*');
        Log::info('api.request', [
            'request_id' => $requestId, 'timestamp' => now()->toIso8601String(),
            'endpoint' => $request->route()?->uri() ?? ($privateReading ? 'api/v1/content/{unmatched}' : $request->path()), 'method' => $request->method(),
            'status' => $response->getStatusCode(), 'duration_ms' => round((hrtime(true) - $start) / 1e6, 2),
            'user_id' => $privateReading ? null : $request->user()?->id, 'ip_address' => $privateReading ? null : $request->ip(),
        ]);

        return $response;
    }
}
