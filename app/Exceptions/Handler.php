<?php

namespace App\Exceptions;

use App\Services\ApiErrorHandler;
use App\Services\ErrorMonitoringService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        ValidationException::class,
        AuthenticationException::class,
        NotFoundHttpException::class,
        MethodNotAllowedHttpException::class,
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
        'api_key',
        'anthropic_key',
        'openai_key',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            // Track errors in our monitoring system for API requests
            if (request()->is('api/*')) {
                try {
                    $errorMonitoring = app(ErrorMonitoringService::class);
                    $errorMonitoring->trackError(
                        $this->getErrorType($e),
                        $e->getMessage(),
                        request()->user(),
                        request()->user()?->currentCompany,
                        [
                            'exception_class' => get_class($e),
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                            'url' => request()->fullUrl(),
                            'method' => request()->method(),
                        ]
                    );
                } catch (\Throwable $monitoringError) {
                    // Don't let monitoring errors break the application
                }
            }
        });
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $e)
    {
        // Handle API requests with consistent error format
        if ($request->is('api/*') || $request->expectsJson()) {
            return $this->renderApiException($request, $e);
        }

        return parent::render($request, $e);
    }

    /**
     * Render API exceptions with consistent format.
     */
    protected function renderApiException(Request $request, Throwable $e)
    {
        $errorHandler = app(ApiErrorHandler::class);

        // Authentication errors
        if ($e instanceof AuthenticationException) {
            return $errorHandler->handleAuthenticationError(
                'Authentication required to access this resource'
            );
        }

        // Validation errors
        if ($e instanceof ValidationException) {
            return $errorHandler->handleValidationError(
                $e->errors(),
                'The given data was invalid'
            );
        }

        // Model not found errors
        if ($e instanceof ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'error' => 'resource_not_found',
                'error_code' => 'RESOURCE_NOT_FOUND',
                'message' => 'The requested resource was not found.',
                'user_message' => 'The item you\'re looking for doesn\'t exist or you don\'t have permission to access it.',
                'data' => [
                    'resource_type' => $this->getModelName($e),
                    'actions' => [
                        'go_back' => 'Go back to the previous page',
                        'refresh' => 'Refresh and try again',
                    ],
                ],
            ], 404);
        }

        // HTTP exceptions
        if ($e instanceof HttpException) {
            return $this->renderHttpException($e, $errorHandler);
        }

        // Rate limiting errors
        if ($e instanceof TooManyRequestsHttpException) {
            $retryAfter = $e->getHeaders()['Retry-After'] ?? 60;
            return $errorHandler->handleRateLimitError((int) $retryAfter);
        }

        // Method not allowed
        if ($e instanceof MethodNotAllowedHttpException) {
            return response()->json([
                'success' => false,
                'error' => 'method_not_allowed',
                'error_code' => 'METHOD_NOT_ALLOWED',
                'message' => 'The HTTP method is not allowed for this endpoint.',
                'user_message' => 'This action is not supported. Please check the documentation.',
                'data' => [
                    'allowed_methods' => $e->getHeaders()['Allow'] ?? [],
                    'current_method' => $request->method(),
                    'actions' => [
                        'check_documentation' => 'Review the API documentation',
                    ],
                ],
            ], 405);
        }

        // Database connection errors
        if ($this->isDatabaseError($e)) {
            return response()->json([
                'success' => false,
                'error' => 'database_error',
                'error_code' => 'DATABASE_ERROR',
                'message' => 'A database error occurred.',
                'user_message' => 'We\'re experiencing technical difficulties. Please try again in a moment.',
                'data' => [
                    'actions' => [
                        'retry' => 'Try the request again',
                        'contact_support' => 'Contact support if the problem persists',
                    ],
                ],
            ], 503);
        }

        // Timeout errors
        if ($this->isTimeoutError($e)) {
            return $errorHandler->handleTimeoutError();
        }

        // General server errors
        return $errorHandler->handleGeneralError(
            $e,
            'An unexpected error occurred. Please try again.'
        );
    }

    /**
     * Render HTTP exceptions.
     */
    protected function renderHttpException(HttpExceptionInterface $e)
    {
        $errorHandler = app(ApiErrorHandler::class);
        $statusCode = $e->getStatusCode();

        switch ($statusCode) {
            case 400:
                return response()->json([
                    'success' => false,
                    'error' => 'bad_request',
                    'error_code' => 'BAD_REQUEST',
                    'message' => $e->getMessage() ?: 'The request was invalid.',
                    'user_message' => 'There was a problem with your request. Please check your input and try again.',
                    'data' => [
                        'actions' => [
                            'check_input' => 'Verify your input data',
                            'retry' => 'Try the request again',
                        ],
                    ],
                ], 400);

            case 403:
                return $errorHandler->handleAuthorizationError(
                    $e->getMessage() ?: 'Access denied'
                );

            case 404:
                return response()->json([
                    'success' => false,
                    'error' => 'not_found',
                    'error_code' => 'NOT_FOUND',
                    'message' => $e->getMessage() ?: 'The requested resource was not found.',
                    'user_message' => 'The page or resource you\'re looking for doesn\'t exist.',
                    'data' => [
                        'actions' => [
                            'go_back' => 'Go back to the previous page',
                            'check_url' => 'Check the URL for typos',
                        ],
                    ],
                ], 404);

            case 422:
                return response()->json([
                    'success' => false,
                    'error' => 'unprocessable_entity',
                    'error_code' => 'UNPROCESSABLE_ENTITY',
                    'message' => $e->getMessage() ?: 'The request was well-formed but contains semantic errors.',
                    'user_message' => 'The data you provided couldn\'t be processed. Please check your input.',
                    'data' => [
                        'actions' => [
                            'check_input' => 'Review and correct your input',
                            'retry' => 'Try the request again',
                        ],
                    ],
                ], 422);

            case 500:
                return $errorHandler->handleGeneralError(
                    $e,
                    'An internal server error occurred'
                );

            case 503:
                return response()->json([
                    'success' => false,
                    'error' => 'service_unavailable',
                    'error_code' => 'SERVICE_UNAVAILABLE',
                    'message' => $e->getMessage() ?: 'The service is temporarily unavailable.',
                    'user_message' => 'The service is temporarily down for maintenance. Please try again later.',
                    'data' => [
                        'actions' => [
                            'retry_later' => 'Try again in a few minutes',
                            'check_status' => 'Check our status page',
                        ],
                    ],
                ], 503);

            default:
                return response()->json([
                    'success' => false,
                    'error' => 'http_error',
                    'error_code' => 'HTTP_ERROR_' . $statusCode,
                    'message' => $e->getMessage() ?: 'An HTTP error occurred.',
                    'user_message' => 'An error occurred while processing your request.',
                    'data' => [
                        'status_code' => $statusCode,
                        'actions' => [
                            'retry' => 'Try the request again',
                            'contact_support' => 'Contact support if the problem persists',
                        ],
                    ],
                ], $statusCode);
        }
    }

    /**
     * Get error type for monitoring.
     */
    protected function getErrorType(Throwable $e): string
    {
        if ($e instanceof ValidationException) {
            return 'validation_error';
        }

        if ($e instanceof AuthenticationException) {
            return 'authentication_error';
        }

        if ($e instanceof ModelNotFoundException) {
            return 'model_not_found';
        }

        if ($e instanceof HttpException) {
            return 'http_error_' . $e->getStatusCode();
        }

        if ($e instanceof TooManyRequestsHttpException) {
            return 'rate_limit_exceeded';
        }

        if ($this->isDatabaseError($e)) {
            return 'database_error';
        }

        if ($this->isTimeoutError($e)) {
            return 'timeout_error';
        }

        return 'general_error';
    }

    /**
     * Get model name from ModelNotFoundException.
     */
    protected function getModelName(ModelNotFoundException $e): string
    {
        $model = $e->getModel();
        return class_basename($model);
    }

    /**
     * Check if exception is a database error.
     */
    protected function isDatabaseError(Throwable $e): bool
    {
        $databaseExceptions = [
            \Illuminate\Database\QueryException::class,
            \PDOException::class,
            \Illuminate\Database\ConnectionException::class,
        ];

        foreach ($databaseExceptions as $exceptionClass) {
            if ($e instanceof $exceptionClass) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if exception is a timeout error.
     */
    protected function isTimeoutError(Throwable $e): bool
    {
        $timeoutExceptions = [
            \Illuminate\Http\Client\ConnectionException::class,
            \GuzzleHttp\Exception\ConnectException::class,
            \GuzzleHttp\Exception\RequestException::class,
        ];

        foreach ($timeoutExceptions as $exceptionClass) {
            if ($e instanceof $exceptionClass) {
                return str_contains(strtolower($e->getMessage()), 'timeout') ||
                       str_contains(strtolower($e->getMessage()), 'timed out');
            }
        }

        return false;
    }
}


