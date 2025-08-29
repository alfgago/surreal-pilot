<?php

namespace App\Http\Middleware;

use App\Services\ApiErrorHandler;
use App\Services\ErrorMonitoringService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ApiErrorHandling
{
    public function __construct(
        private ApiErrorHandler $errorHandler,
        private ErrorMonitoringService $errorMonitoring
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Add request ID for tracking
        if (!$request->hasHeader('X-Request-ID')) {
            $request->headers->set('X-Request-ID', $this->generateRequestId());
        }

        try {
            $response = $next($request);

            // Log successful API requests for monitoring
            if ($request->is('api/*')) {
                $this->logApiRequest($request, $response);
            }

            return $response;
        } catch (\Throwable $e) {
            // This middleware catches any exceptions that weren't handled by the controllers
            Log::error('Unhandled exception in API middleware', [
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'request_id' => $request->header('X-Request-ID'),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'user_id' => $request->user()?->id,
                'company_id' => $request->user()?->currentCompany?->id,
            ]);

            // Track the error
            $this->errorMonitoring->trackError(
                'middleware_exception',
                $e->getMessage(),
                $request->user(),
                $request->user()?->currentCompany,
                [
                    'exception_class' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            );

            // Return a generic error response
            return $this->errorHandler->handleGeneralError(
                $e,
                'An unexpected error occurred while processing your request'
            );
        }
    }

    /**
     * Generate a unique request ID.
     */
    private function generateRequestId(): string
    {
        return 'req_' . uniqid() . '_' . substr(md5(microtime()), 0, 8);
    }

    /**
     * Log API request for monitoring.
     */
    private function logApiRequest(Request $request, Response $response): void
    {
        try {
            $logData = [
                'request_id' => $request->header('X-Request-ID'),
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'status_code' => $response->getStatusCode(),
                'user_id' => $request->user()?->id,
                'company_id' => $request->user()?->currentCompany?->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'response_time' => $this->getResponseTime($request),
            ];

            // Only log errors and important endpoints to avoid spam
            if ($response->getStatusCode() >= 400 || $this->shouldLogRequest($request)) {
                Log::info('API Request', $logData);
            }
        } catch (\Throwable $e) {
            // Don't let logging errors break the application
        }
    }

    /**
     * Determine if request should be logged.
     */
    private function shouldLogRequest(Request $request): bool
    {
        $importantEndpoints = [
            'api/chat',
            'api/assist',
            'api/prototype',
            'api/engines',
            'api/workspaces',
            'api/conversations',
            'api/games',
            'api/chat/settings',
        ];

        foreach ($importantEndpoints as $endpoint) {
            if (str_contains($request->path(), $endpoint)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get response time in milliseconds.
     */
    private function getResponseTime(Request $request): ?float
    {
        $startTime = $request->server('REQUEST_TIME_FLOAT');
        if ($startTime) {
            return round((microtime(true) - $startTime) * 1000, 2);
        }
        return null;
    }
}