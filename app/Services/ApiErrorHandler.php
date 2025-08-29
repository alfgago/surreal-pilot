<?php

namespace App\Services;

use App\Services\ErrorMonitoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ApiErrorHandler
{
    public function __construct(
        private ErrorMonitoringService $errorMonitoring
    ) {}

    /**
     * Handle authentication errors.
     */
    public function handleAuthenticationError(string $message = 'Authentication required'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => 'authentication_required',
            'error_code' => 'AUTHENTICATION_REQUIRED',
            'message' => $message,
            'user_message' => 'Please log in to access this resource.',
            'data' => [
                'actions' => [
                    'login' => '/login',
                    'register' => '/register',
                ],
            ],
        ], 401);
    }

    /**
     * Handle authorization errors.
     */
    public function handleAuthorizationError(string $message = 'Access denied'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => 'access_denied',
            'error_code' => 'ACCESS_DENIED',
            'message' => $message,
            'user_message' => 'You don\'t have permission to access this resource.',
            'data' => [
                'actions' => [
                    'contact_admin' => 'Contact your administrator',
                    'upgrade_plan' => '/dashboard/billing/plans',
                ],
            ],
        ], 403);
    }

    /**
     * Handle validation errors.
     */
    public function handleValidationError(array $errors, string $message = 'Validation failed'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => 'validation_failed',
            'error_code' => 'VALIDATION_FAILED',
            'message' => $message,
            'user_message' => 'Please check your input and try again.',
            'data' => [
                'validation_errors' => $errors,
                'actions' => [
                    'fix_errors' => 'Correct the highlighted fields',
                    'retry' => 'Try submitting again',
                ],
            ],
        ], 422);
    }

    /**
     * Handle rate limit errors.
     */
    public function handleRateLimitError(int $retryAfter = 60): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => 'rate_limit_exceeded',
            'error_code' => 'RATE_LIMIT_EXCEEDED',
            'message' => 'Too many requests. Please try again later.',
            'user_message' => "You've made too many requests. Please wait {$retryAfter} seconds before trying again.",
            'data' => [
                'retry_after' => $retryAfter,
                'actions' => [
                    'wait_and_retry' => "Wait {$retryAfter} seconds and try again",
                    'upgrade_plan' => 'Upgrade your plan for higher limits',
                ],
            ],
        ], 429, [
            'Retry-After' => $retryAfter,
        ]);
    }

    /**
     * Handle timeout errors.
     */
    public function handleTimeoutError(string $message = 'Request timeout'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => 'request_timeout',
            'error_code' => 'REQUEST_TIMEOUT',
            'message' => $message,
            'user_message' => 'The request took too long to complete. Please try again.',
            'data' => [
                'actions' => [
                    'retry' => 'Try the request again',
                    'simplify_request' => 'Try with less data',
                    'contact_support' => 'Contact support if the problem persists',
                ],
            ],
        ], 408);
    }

    /**
     * Handle company not found errors.
     */
    public function handleCompanyNotFound(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => 'company_not_found',
            'error_code' => 'COMPANY_NOT_FOUND',
            'message' => 'No company associated with your account.',
            'user_message' => 'You need to be part of a company to access this resource.',
            'data' => [
                'actions' => [
                    'create_company' => '/dashboard/company/create',
                    'join_company' => 'Ask for an invitation to join a company',
                    'contact_support' => 'Contact support for help',
                ],
            ],
        ], 404);
    }

    /**
     * Handle general errors.
     */
    public function handleGeneralError(\Throwable $exception, string $userMessage = 'An error occurred'): JsonResponse
    {
        $isDevelopment = config('app.debug', false);
        
        // Log the error
        Log::error('General API error', [
            'exception' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $isDevelopment ? $exception->getTraceAsString() : null,
            'request_id' => request()->header('X-Request-ID'),
            'url' => request()->fullUrl(),
            'method' => request()->method(),
            'user_id' => auth()->id(),
        ]);

        // Track error in monitoring system
        $this->errorMonitoring->trackError(
            'general_api_error',
            $exception->getMessage(),
            auth()->user(),
            auth()->user()?->currentCompany,
            [
                'exception_class' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]
        );

        $responseData = [
            'success' => false,
            'error' => 'internal_server_error',
            'error_code' => 'INTERNAL_SERVER_ERROR',
            'message' => $isDevelopment ? $exception->getMessage() : 'An internal server error occurred.',
            'user_message' => $userMessage,
            'data' => [
                'request_id' => request()->header('X-Request-ID'),
                'actions' => [
                    'retry' => 'Try the request again',
                    'contact_support' => 'Contact support with the request ID',
                ],
            ],
        ];

        // Add debug information in development
        if ($isDevelopment) {
            $responseData['debug'] = [
                'exception_class' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ];
        }

        return response()->json($responseData, 500);
    }

    /**
     * Handle service unavailable errors.
     */
    public function handleServiceUnavailable(string $service = 'service', string $message = null): JsonResponse
    {
        $message = $message ?? "The {$service} is temporarily unavailable.";
        
        return response()->json([
            'success' => false,
            'error' => 'service_unavailable',
            'error_code' => 'SERVICE_UNAVAILABLE',
            'message' => $message,
            'user_message' => 'This service is temporarily down for maintenance. Please try again later.',
            'data' => [
                'service' => $service,
                'actions' => [
                    'retry_later' => 'Try again in a few minutes',
                    'check_status' => '/api/status',
                    'contact_support' => 'Contact support if the problem persists',
                ],
            ],
        ], 503);
    }

    /**
     * Handle resource not found errors.
     */
    public function handleResourceNotFound(string $resource = 'resource', int $resourceId = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => 'resource_not_found',
            'error_code' => 'RESOURCE_NOT_FOUND',
            'message' => "The requested {$resource} was not found.",
            'user_message' => "The {$resource} you're looking for doesn't exist or you don't have permission to access it.",
            'data' => [
                'resource_type' => $resource,
                'resource_id' => $resourceId,
                'actions' => [
                    'go_back' => 'Go back to the previous page',
                    'check_permissions' => 'Verify you have the correct permissions',
                    'contact_admin' => 'Contact your administrator',
                ],
            ],
        ], 404);
    }

    /**
     * Handle quota exceeded errors.
     */
    public function handleQuotaExceeded(string $quotaType, int $limit, int $current): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => 'quota_exceeded',
            'error_code' => 'QUOTA_EXCEEDED',
            'message' => "You have exceeded your {$quotaType} quota.",
            'user_message' => "You've reached your limit of {$limit} {$quotaType}. Please upgrade your plan or delete some items.",
            'data' => [
                'quota_type' => $quotaType,
                'limit' => $limit,
                'current' => $current,
                'actions' => [
                    'upgrade_plan' => '/dashboard/billing/plans',
                    'delete_items' => "Delete some {$quotaType} to free up space",
                    'contact_sales' => 'Contact sales for custom limits',
                ],
            ],
        ], 422);
    }

    /**
     * Handle maintenance mode errors.
     */
    public function handleMaintenanceMode(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => 'maintenance_mode',
            'error_code' => 'MAINTENANCE_MODE',
            'message' => 'The application is currently under maintenance.',
            'user_message' => 'We\'re performing scheduled maintenance. Please check back in a few minutes.',
            'data' => [
                'actions' => [
                    'retry_later' => 'Try again in a few minutes',
                    'check_status' => '/api/status',
                    'follow_updates' => 'Follow our status page for updates',
                ],
            ],
        ], 503);
    }

    /**
     * Handle AI provider unavailable errors.
     */
    public function handleProviderUnavailable(string $provider = 'AI provider', string $message = null): JsonResponse
    {
        $message = $message ?? "The {$provider} is currently unavailable.";
        
        return response()->json([
            'success' => false,
            'error' => 'provider_unavailable',
            'error_code' => 'PROVIDER_UNAVAILABLE',
            'message' => $message,
            'user_message' => 'The AI service is temporarily unavailable. Please try again later.',
            'data' => [
                'provider' => $provider,
                'actions' => [
                    'retry_later' => 'Try again in a few minutes',
                    'try_different_model' => 'Try using a different AI model',
                    'contact_support' => 'Contact support if the problem persists',
                ],
            ],
        ], 503);
    }
}