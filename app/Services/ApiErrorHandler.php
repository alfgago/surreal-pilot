<?php

namespace App\Services;

use App\Models\Company;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class ApiErrorHandler
{
    /**
     * Handle insufficient credits error.
     */
    public function handleInsufficientCredits(Company $company, int $estimatedTokens = 0, array $context = []): JsonResponse
    {
        $this->logError('insufficient_credits', 'Company has insufficient credits', array_merge([
            'company_id' => $company->id,
            'company_name' => $company->name,
            'current_credits' => $company->credits,
            'estimated_tokens' => $estimatedTokens,
        ], $context));

        // Persist to error logs for analytics/monitoring
        try {
            $errorMonitoring = app(\App\Services\ErrorMonitoringService::class);
            $errorMonitoring->trackError(
                'insufficient_credits',
                'Company has insufficient credits',
                request()->user(),
                $company,
                array_merge([
                    'estimated_tokens' => $estimatedTokens,
                ], $context)
            );
        } catch (\Throwable $e) {
            // Swallow monitoring errors
        }

        return response()->json([
            'error' => 'insufficient_credits',
            'error_code' => 'INSUFFICIENT_CREDITS',
            'message' => 'Your company has insufficient credits to process this request.',
            'user_message' => 'Not enough credits available. Please purchase more credits or upgrade your plan.',
            'data' => [
                'credits_available' => $company->credits,
                'estimated_tokens_needed' => $estimatedTokens,
                'credits_needed' => max(0, $estimatedTokens - $company->credits),
                'plan' => $company->plan,
                'actions' => [
                    'purchase_credits' => '/dashboard/billing/credits',
                    'upgrade_plan' => '/dashboard/billing/plans',
                    'view_usage' => '/dashboard/usage',
                ],
            ],
        ], 402);
    }

    /**
     * Handle provider unavailable error.
     */
    public function handleProviderUnavailable(string $requestedProvider = null, array $availableProviders = [], array $context = []): JsonResponse
    {
        $this->logError('provider_unavailable', 'AI provider is unavailable', array_merge([
            'requested_provider' => $requestedProvider,
            'available_providers' => $availableProviders,
        ], $context));

        return response()->json([
            'error' => 'provider_unavailable',
            'error_code' => 'PROVIDER_UNAVAILABLE',
            'message' => $requestedProvider 
                ? "The requested AI provider '{$requestedProvider}' is currently unavailable."
                : 'No AI providers are currently available.',
            'user_message' => 'AI service is temporarily unavailable. Please try again in a few moments or select a different provider.',
            'data' => [
                'requested_provider' => $requestedProvider,
                'available_providers' => $availableProviders,
                'fallback_suggestions' => $this->getFallbackSuggestions($availableProviders),
                'actions' => [
                    'retry' => 'Try the request again',
                    'change_provider' => 'Select a different AI provider',
                    'check_status' => '/api/providers',
                ],
            ],
        ], 503);
    }

    /**
     * Handle authentication errors.
     */
    public function handleAuthenticationError(string $message = 'Authentication required', array $context = []): JsonResponse
    {
        $this->logError('authentication_error', $message, $context);

        return response()->json([
            'error' => 'authentication_required',
            'error_code' => 'AUTHENTICATION_REQUIRED',
            'message' => $message,
            'user_message' => 'Please log in to access this feature.',
            'data' => [
                'actions' => [
                    'login' => '/login',
                    'register' => '/register',
                ],
            ],
        ], 401);
    }

    /**
     * Handle authorization/permission errors.
     */
    public function handleAuthorizationError(string $message = 'Insufficient permissions', array $additionalData = [], array $context = []): JsonResponse
    {
        $this->logError('authorization_error', $message, array_merge($additionalData, $context));

        return response()->json([
            'error' => 'access_denied',
            'error_code' => 'INSUFFICIENT_PERMISSIONS',
            'message' => $message,
            'user_message' => 'You don\'t have permission to perform this action. Contact your administrator if you need access.',
            'data' => array_merge([
                'actions' => [
                    'contact_admin' => 'Contact your company administrator',
                    'view_permissions' => '/dashboard/profile',
                ],
            ], $additionalData),
        ], 403);
    }

    /**
     * Handle rate limiting errors.
     */
    public function handleRateLimitError(int $retryAfter = 60, array $context = []): JsonResponse
    {
        $this->logError('rate_limit_exceeded', 'Rate limit exceeded', array_merge([
            'retry_after' => $retryAfter,
        ], $context));

        return response()->json([
            'error' => 'rate_limit_exceeded',
            'error_code' => 'RATE_LIMIT_EXCEEDED',
            'message' => 'Too many requests. Please slow down.',
            'user_message' => "You're making requests too quickly. Please wait {$retryAfter} seconds before trying again.",
            'data' => [
                'retry_after' => $retryAfter,
                'retry_after_human' => $this->formatRetryAfter($retryAfter),
                'actions' => [
                    'wait_and_retry' => "Wait {$retryAfter} seconds and try again",
                ],
            ],
        ], 429);
    }

    /**
     * Handle validation errors.
     */
    public function handleValidationError(array $errors, string $message = 'Validation failed', array $context = []): JsonResponse
    {
        $this->logError('validation_error', $message, array_merge([
            'validation_errors' => $errors,
        ], $context));

        return response()->json([
            'error' => 'validation_failed',
            'error_code' => 'VALIDATION_FAILED',
            'message' => $message,
            'user_message' => 'Please check your input and try again.',
            'data' => [
                'errors' => $errors,
                'actions' => [
                    'fix_input' => 'Correct the highlighted fields and resubmit',
                ],
            ],
        ], 422);
    }

    /**
     * Handle streaming errors.
     */
    public function handleStreamingError(string $message = 'Streaming error occurred', array $context = []): JsonResponse
    {
        $this->logError('streaming_error', $message, $context);

        return response()->json([
            'error' => 'streaming_error',
            'error_code' => 'STREAMING_ERROR',
            'message' => $message,
            'user_message' => 'An error occurred while streaming the response. Please try again.',
            'data' => [
                'actions' => [
                    'retry' => 'Try the request again',
                    'disable_streaming' => 'Try without streaming',
                ],
            ],
        ], 500);
    }

    /**
     * Handle credit transaction errors.
     */
    public function handleCreditTransactionError(string $message = 'Credit transaction failed', array $context = []): JsonResponse
    {
        $this->logError('credit_transaction_error', $message, $context);

        return response()->json([
            'error' => 'credit_transaction_failed',
            'error_code' => 'CREDIT_TRANSACTION_FAILED',
            'message' => $message,
            'user_message' => 'There was an issue processing your credits. Please contact support if this persists.',
            'data' => [
                'actions' => [
                    'retry' => 'Try the request again',
                    'contact_support' => 'Contact customer support',
                    'check_balance' => '/dashboard/billing',
                ],
            ],
        ], 500);
    }

    /**
     * Handle company not found errors.
     */
    public function handleCompanyNotFound(array $context = []): JsonResponse
    {
        $this->logError('company_not_found', 'No active company found', $context);

        return response()->json([
            'error' => 'no_active_company',
            'error_code' => 'NO_ACTIVE_COMPANY',
            'message' => 'No active company found for the current user.',
            'user_message' => 'You need to be part of a company to use this feature. Please join or create a company.',
            'data' => [
                'actions' => [
                    'create_company' => '/dashboard/companies/create',
                    'join_company' => '/dashboard/companies/join',
                ],
            ],
        ], 400);
    }

    /**
     * Handle general API errors.
     */
    public function handleGeneralError(Throwable $exception, string $userMessage = 'An unexpected error occurred', array $context = []): JsonResponse
    {
        $this->logError('general_error', $exception->getMessage(), array_merge([
            'exception_class' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ], $context));

        return response()->json([
            'error' => 'internal_server_error',
            'error_code' => 'INTERNAL_SERVER_ERROR',
            'message' => 'An internal server error occurred.',
            'user_message' => $userMessage,
            'data' => [
                'error_id' => $this->generateErrorId(),
                'actions' => [
                    'retry' => 'Try the request again',
                    'contact_support' => 'Contact support with error ID if problem persists',
                ],
            ],
        ], 500);
    }

    /**
     * Handle provider API errors (from external services).
     */
    public function handleProviderApiError(string $provider, Throwable $exception, array $context = []): JsonResponse
    {
        $this->logError('provider_api_error', "Provider {$provider} API error: " . $exception->getMessage(), array_merge([
            'provider' => $provider,
            'exception_class' => get_class($exception),
        ], $context));

        return response()->json([
            'error' => 'provider_api_error',
            'error_code' => 'PROVIDER_API_ERROR',
            'message' => "The {$provider} AI service encountered an error.",
            'user_message' => 'The AI service is experiencing issues. Please try again or select a different provider.',
            'data' => [
                'provider' => $provider,
                'actions' => [
                    'retry' => 'Try the request again',
                    'change_provider' => 'Select a different AI provider',
                ],
            ],
        ], 502);
    }

    /**
     * Handle timeout errors.
     */
    public function handleTimeoutError(int $timeoutSeconds = 30, array $context = []): JsonResponse
    {
        $this->logError('timeout_error', "Request timed out after {$timeoutSeconds} seconds", array_merge([
            'timeout_seconds' => $timeoutSeconds,
        ], $context));

        return response()->json([
            'error' => 'request_timeout',
            'error_code' => 'REQUEST_TIMEOUT',
            'message' => 'The request timed out.',
            'user_message' => 'The request took too long to process. Please try again with a shorter prompt or different settings.',
            'data' => [
                'timeout_seconds' => $timeoutSeconds,
                'actions' => [
                    'retry' => 'Try the request again',
                    'reduce_complexity' => 'Try with a shorter or simpler prompt',
                ],
            ],
        ], 408);
    }

    /**
     * Send Server-Sent Event error.
     */
    public function sendSSEError(string $errorType, string $message, array $data = []): void
    {
        $errorData = array_merge([
            'error' => $errorType,
            'message' => $message,
            'timestamp' => now()->toISOString(),
        ], $data);

        echo "event: error\n";
        echo "data: " . json_encode($errorData) . "\n\n";
        
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
    }

    /**
     * Log error with consistent format.
     */
    private function logError(string $errorType, string $message, array $context = []): void
    {
        try {
            Log::error("API Error: {$errorType}", array_merge([
            'error_type' => $errorType,
            'message' => $message,
            'timestamp' => now()->toISOString(),
            'request_id' => request()->header('X-Request-ID') ?? $this->generateErrorId(),
            ], $context));
        } catch (\Throwable $ignored) {}
    }

    /**
     * Generate unique error ID for tracking.
     */
    private function generateErrorId(): string
    {
        return 'err_' . uniqid() . '_' . substr(md5(microtime()), 0, 8);
    }

    /**
     * Format retry after seconds into human readable format.
     */
    private function formatRetryAfter(int $seconds): string
    {
        if ($seconds < 60) {
            return "{$seconds} seconds";
        }
        
        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;
        
        if ($remainingSeconds === 0) {
            return $minutes == 1 ? "1 minute" : "{$minutes} minutes";
        }
        
        return $minutes == 1 
            ? "1 minute and {$remainingSeconds} seconds"
            : "{$minutes} minutes and {$remainingSeconds} seconds";
    }

    /**
     * Get fallback suggestions based on available providers.
     */
    private function getFallbackSuggestions(array $availableProviders): array
    {
        if (empty($availableProviders)) {
            return [
                'message' => 'No providers are currently available. Please try again later.',
                'suggestions' => [],
            ];
        }

        return [
            'message' => 'Try one of these available providers:',
            'suggestions' => array_map(function ($provider) {
                return [
                    'provider' => $provider,
                    'display_name' => ucfirst($provider),
                ];
            }, $availableProviders),
        ];
    }

    /**
     * Check if error should be retryable.
     */
    public function isRetryableError(string $errorType): bool
    {
        $retryableErrors = [
            'provider_unavailable',
            'timeout_error',
            'rate_limit_exceeded',
            'provider_api_error',
            'streaming_error',
        ];

        return in_array($errorType, $retryableErrors);
    }

    /**
     * Get error severity level.
     */
    public function getErrorSeverity(string $errorType): string
    {
        $severityMap = [
            'authentication_error' => 'low',
            'authorization_error' => 'low',
            'validation_error' => 'low',
            'insufficient_credits' => 'medium',
            'rate_limit_exceeded' => 'medium',
            'provider_unavailable' => 'medium',
            'timeout_error' => 'medium',
            'company_not_found' => 'medium',
            'streaming_error' => 'high',
            'credit_transaction_error' => 'high',
            'provider_api_error' => 'high',
            'general_error' => 'critical',
        ];

        return $severityMap[$errorType] ?? 'medium';
    }
}