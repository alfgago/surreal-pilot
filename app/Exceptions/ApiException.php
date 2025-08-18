<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class ApiException extends Exception
{
    protected string $errorCode;
    protected string $userMessage;
    protected array $errorData;
    protected int $statusCode;

    public function __construct(
        string $message,
        string $errorCode,
        string $userMessage = '',
        array $errorData = [],
        int $statusCode = 500,
        Exception $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        
        $this->errorCode = $errorCode;
        $this->userMessage = $userMessage ?: $message;
        $this->errorData = $errorData;
        $this->statusCode = $statusCode;
    }

    /**
     * Create an insufficient credits exception.
     */
    public static function insufficientCredits(int $available, int $needed): self
    {
        return new self(
            "Insufficient credits: {$available} available, {$needed} needed",
            'INSUFFICIENT_CREDITS',
            'Not enough credits available. Please purchase more credits or upgrade your plan.',
            [
                'credits_available' => $available,
                'credits_needed' => $needed,
            ],
            402
        );
    }

    /**
     * Create a provider unavailable exception.
     */
    public static function providerUnavailable(string $provider, array $availableProviders = []): self
    {
        return new self(
            "Provider '{$provider}' is unavailable",
            'PROVIDER_UNAVAILABLE',
            'AI service is temporarily unavailable. Please try again or select a different provider.',
            [
                'requested_provider' => $provider,
                'available_providers' => $availableProviders,
            ],
            503
        );
    }

    /**
     * Create an authentication required exception.
     */
    public static function authenticationRequired(string $message = 'Authentication required'): self
    {
        return new self(
            $message,
            'AUTHENTICATION_REQUIRED',
            'Please log in to access this feature.',
            [],
            401
        );
    }

    /**
     * Create an authorization failed exception.
     */
    public static function authorizationFailed(string $message = 'Insufficient permissions', array $data = []): self
    {
        return new self(
            $message,
            'INSUFFICIENT_PERMISSIONS',
            'You don\'t have permission to perform this action.',
            $data,
            403
        );
    }

    /**
     * Create a rate limit exceeded exception.
     */
    public static function rateLimitExceeded(int $retryAfter = 60): self
    {
        return new self(
            'Rate limit exceeded',
            'RATE_LIMIT_EXCEEDED',
            "You're making requests too quickly. Please wait {$retryAfter} seconds before trying again.",
            ['retry_after' => $retryAfter],
            429
        );
    }

    /**
     * Create a validation failed exception.
     */
    public static function validationFailed(array $errors, string $message = 'Validation failed'): self
    {
        return new self(
            $message,
            'VALIDATION_FAILED',
            'Please check your input and try again.',
            ['errors' => $errors],
            422
        );
    }

    /**
     * Create a company not found exception.
     */
    public static function companyNotFound(): self
    {
        return new self(
            'No active company found',
            'NO_ACTIVE_COMPANY',
            'You need to be part of a company to use this feature.',
            [],
            400
        );
    }

    /**
     * Create a streaming error exception.
     */
    public static function streamingError(string $message = 'Streaming error occurred'): self
    {
        return new self(
            $message,
            'STREAMING_ERROR',
            'An error occurred while streaming the response. Please try again.',
            [],
            500
        );
    }

    /**
     * Create a provider API error exception.
     */
    public static function providerApiError(string $provider, string $message): self
    {
        return new self(
            "Provider {$provider} API error: {$message}",
            'PROVIDER_API_ERROR',
            'The AI service is experiencing issues. Please try again or select a different provider.',
            ['provider' => $provider],
            502
        );
    }

    /**
     * Create a timeout error exception.
     */
    public static function timeoutError(int $timeoutSeconds = 30): self
    {
        return new self(
            "Request timed out after {$timeoutSeconds} seconds",
            'REQUEST_TIMEOUT',
            'The request took too long to process. Please try again with a shorter prompt.',
            ['timeout_seconds' => $timeoutSeconds],
            408
        );
    }

    /**
     * Get the error code.
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * Get the user-friendly message.
     */
    public function getUserMessage(): string
    {
        return $this->userMessage;
    }

    /**
     * Get the error data.
     */
    public function getErrorData(): array
    {
        return $this->errorData;
    }

    /**
     * Get the HTTP status code.
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Convert to JSON response.
     */
    public function toJsonResponse(): JsonResponse
    {
        return response()->json([
            'error' => strtolower(str_replace('_', '_', $this->errorCode)),
            'error_code' => $this->errorCode,
            'message' => $this->getMessage(),
            'user_message' => $this->userMessage,
            'data' => $this->errorData,
        ], $this->statusCode);
    }
}