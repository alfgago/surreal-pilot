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
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        
        $this->errorCode = $errorCode;
        $this->userMessage = $userMessage ?: $message;
        $this->errorData = $errorData;
        $this->statusCode = $statusCode;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getUserMessage(): string
    {
        return $this->userMessage;
    }

    public function getErrorData(): array
    {
        return $this->errorData;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function toJsonResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => strtolower(str_replace('_', '_', $this->errorCode)),
            'error_code' => $this->errorCode,
            'message' => $this->getMessage(),
            'user_message' => $this->userMessage,
            'data' => $this->errorData,
        ], $this->statusCode);
    }
}