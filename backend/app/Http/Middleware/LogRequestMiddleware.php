<?php

namespace App\Http\Middleware;

use Closure;
use App\Services\Monitoring\StructuredLogContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogRequestMiddleware
{
    public function __construct(
        private readonly StructuredLogContextService $structuredLogs
    ) {
    }

    /**
     * Handle an incoming request.
     *
     * WHY:
     * Provides centralized logging for all API requests
     * to improve debugging, monitoring and performance tracking.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);

        $response = $next($request);

        $duration = round((microtime(true) - $start) * 1000, 2);

        $context = $this->structuredLogs->withRequestContext($request, [
            'event' => 'http.request.completed',
            'category' => 'request',
            'module' => 'api',
            'status' => $response->getStatusCode(),
            'duration_ms' => $duration,
        ]);

        $response->headers->set('X-Request-Id', (string) ($context['request_id'] ?? ''));

        Log::info('API Request', $context);

        return $response;
    }
}
