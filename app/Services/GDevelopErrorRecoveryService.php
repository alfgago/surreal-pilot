<?php

namespace App\Services;

use App\Exceptions\GDevelop\GDevelopCliException;
use App\Exceptions\GDevelop\GameJsonValidationException;
use App\Exceptions\GDevelop\GDevelopPreviewException;
use App\Exceptions\GDevelop\GDevelopExportException;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class GDevelopErrorRecoveryService
{
    private const MAX_RETRIES = 3;
    private const RETRY_DELAY_SECONDS = 2;
    private const BACKOFF_MULTIPLIER = 2;

    /**
     * Execute a callable with retry logic for GDevelop operations
     */
    public function executeWithRetry(callable $operation, string $operationType, array $context = []): mixed
    {
        $attempt = 1;
        $delay = self::RETRY_DELAY_SECONDS;

        while ($attempt <= self::MAX_RETRIES) {
            try {
                Log::info("GDevelop operation attempt {$attempt}/{$this->getMaxRetries()}", [
                    'operation_type' => $operationType,
                    'attempt' => $attempt,
                    'context' => $context,
                ]);

                $result = $operation();
                
                // Log successful recovery if this wasn't the first attempt
                if ($attempt > 1) {
                    Log::info("GDevelop operation recovered successfully", [
                        'operation_type' => $operationType,
                        'successful_attempt' => $attempt,
                        'context' => $context,
                    ]);
                }

                return $result;

            } catch (GDevelopCliException $e) {
                if (!$e->isRetryable() || $attempt >= self::MAX_RETRIES) {
                    $this->logFinalFailure($e, $operationType, $attempt, $context);
                    throw $e;
                }

                $this->logRetryableError($e, $operationType, $attempt, $delay, $context);
                
            } catch (GameJsonValidationException $e) {
                if (!$e->isRecoverable() || $attempt >= self::MAX_RETRIES) {
                    $this->logFinalFailure($e, $operationType, $attempt, $context);
                    throw $e;
                }

                $this->logRetryableError($e, $operationType, $attempt, $delay, $context);
                
            } catch (GDevelopPreviewException|GDevelopExportException $e) {
                if (!$e->isRetryable() || $attempt >= self::MAX_RETRIES) {
                    $this->logFinalFailure($e, $operationType, $attempt, $context);
                    throw $e;
                }

                $this->logRetryableError($e, $operationType, $attempt, $delay, $context);
                
            } catch (Exception $e) {
                // For unknown exceptions, only retry once
                if ($attempt >= 2) {
                    $this->logFinalFailure($e, $operationType, $attempt, $context);
                    throw $e;
                }

                $this->logRetryableError($e, $operationType, $attempt, $delay, $context);
            }

            // Wait before retrying with exponential backoff
            sleep($delay);
            $delay *= self::BACKOFF_MULTIPLIER;
            $attempt++;
        }

        throw new Exception("Maximum retry attempts exceeded for operation: {$operationType}");
    }

    /**
     * Handle CLI command errors with specific recovery strategies
     */
    public function handleCliError(GDevelopCliException $e, string $sessionId): array
    {
        $errorInfo = [
            'error_type' => 'cli_error',
            'user_message' => $e->getUserFriendlyMessage(),
            'debug_info' => $e->getDebugInfo(),
            'suggested_action' => $e->getSuggestedAction(),
            'is_retryable' => $e->isRetryable(),
            'session_id' => $sessionId,
        ];

        // Log the error for monitoring
        Log::error('GDevelop CLI error occurred', [
            'session_id' => $sessionId,
            'command' => $e->command,
            'exit_code' => $e->exitCode,
            'stderr' => $e->stderr,
            'user_message' => $e->getUserFriendlyMessage(),
        ]);

        // Track error frequency for this session
        $this->trackErrorFrequency($sessionId, 'cli_error');

        return $errorInfo;
    }

    /**
     * Handle JSON validation errors with recovery suggestions
     */
    public function handleValidationError(GameJsonValidationException $e, string $sessionId): array
    {
        $errorInfo = [
            'error_type' => 'validation_error',
            'user_message' => $e->getUserFriendlyMessage(),
            'debug_info' => $e->getDebugInfo(),
            'validation_errors' => $e->getValidationErrors(),
            'critical_error' => $e->getCriticalError(),
            'is_recoverable' => $e->isRecoverable(),
            'session_id' => $sessionId,
        ];

        // Log the validation error
        Log::error('GDevelop JSON validation error', [
            'session_id' => $sessionId,
            'validation_errors' => $e->getValidationErrors(),
            'error_count' => count($e->getValidationErrors()),
            'is_recoverable' => $e->isRecoverable(),
        ]);

        // Track validation error patterns
        $this->trackValidationErrorPatterns($sessionId, $e->getValidationErrors());

        return $errorInfo;
    }

    /**
     * Handle preview generation errors
     */
    public function handlePreviewError(GDevelopPreviewException $e, string $sessionId): array
    {
        $errorInfo = [
            'error_type' => 'preview_error',
            'user_message' => $e->getUserFriendlyMessage(),
            'debug_info' => $e->getDebugInfo(),
            'suggested_action' => $e->getSuggestedAction(),
            'is_retryable' => $e->isRetryable(),
            'session_id' => $sessionId,
        ];

        Log::error('GDevelop preview error', [
            'session_id' => $sessionId,
            'preview_path' => $e->previewPath,
            'error_message' => $e->getMessage(),
            'is_retryable' => $e->isRetryable(),
        ]);

        $this->trackErrorFrequency($sessionId, 'preview_error');

        return $errorInfo;
    }

    /**
     * Handle export errors
     */
    public function handleExportError(GDevelopExportException $e, string $sessionId): array
    {
        $errorInfo = [
            'error_type' => 'export_error',
            'user_message' => $e->getUserFriendlyMessage(),
            'debug_info' => $e->getDebugInfo(),
            'suggested_action' => $e->getSuggestedAction(),
            'is_retryable' => $e->isRetryable(),
            'session_id' => $sessionId,
        ];

        Log::error('GDevelop export error', [
            'session_id' => $sessionId,
            'export_path' => $e->exportPath,
            'export_options' => $e->exportOptions,
            'error_message' => $e->getMessage(),
            'is_retryable' => $e->isRetryable(),
        ]);

        $this->trackErrorFrequency($sessionId, 'export_error');

        return $errorInfo;
    }

    /**
     * Get system health status for error context
     */
    public function getSystemHealthStatus(): array
    {
        return [
            'gdevelop_cli_available' => $this->checkGDevelopCliAvailability(),
            'disk_space_available' => $this->checkDiskSpace(),
            'memory_usage' => $this->getMemoryUsage(),
            'active_sessions' => $this->getActiveSessionCount(),
            'error_rate' => $this->getRecentErrorRate(),
        ];
    }

    /**
     * Check if we should suggest fallback options
     */
    public function shouldSuggestFallback(string $sessionId, string $errorType): bool
    {
        $errorCount = $this->getErrorCount($sessionId, $errorType);
        
        // Suggest fallback after 2 consecutive errors of the same type
        return $errorCount >= 2;
    }

    /**
     * Get fallback suggestions based on error patterns
     */
    public function getFallbackSuggestions(string $sessionId, string $errorType): array
    {
        $suggestions = [];

        switch ($errorType) {
            case 'cli_error':
                $suggestions[] = 'Try creating a simpler game with fewer objects';
                $suggestions[] = 'Use a basic game template instead of complex generation';
                break;

            case 'validation_error':
                $suggestions[] = 'Start with a basic game template';
                $suggestions[] = 'Try describing your game in simpler terms';
                break;

            case 'preview_error':
                $suggestions[] = 'Try exporting the game directly instead of preview';
                $suggestions[] = 'Simplify the game by removing complex elements';
                break;

            case 'export_error':
                $suggestions[] = 'Try exporting without mobile optimization';
                $suggestions[] = 'Use standard compression instead of maximum';
                break;
        }

        return $suggestions;
    }

    private function getMaxRetries(): int
    {
        return config('gdevelop.error_recovery.max_retries', self::MAX_RETRIES);
    }

    private function logRetryableError(Exception $e, string $operationType, int $attempt, int $delay, array $context): void
    {
        Log::warning("GDevelop operation failed, retrying", [
            'operation_type' => $operationType,
            'attempt' => $attempt,
            'max_retries' => $this->getMaxRetries(),
            'retry_delay' => $delay,
            'error_message' => $e->getMessage(),
            'context' => $context,
        ]);
    }

    private function logFinalFailure(Exception $e, string $operationType, int $attempt, array $context): void
    {
        Log::error("GDevelop operation failed permanently", [
            'operation_type' => $operationType,
            'final_attempt' => $attempt,
            'error_message' => $e->getMessage(),
            'error_class' => get_class($e),
            'context' => $context,
        ]);
    }

    private function trackErrorFrequency(string $sessionId, string $errorType): void
    {
        $key = "gdevelop_errors:{$sessionId}:{$errorType}";
        $count = Cache::get($key, 0);
        Cache::put($key, $count + 1, now()->addHours(24));
    }

    private function trackValidationErrorPatterns(string $sessionId, array $validationErrors): void
    {
        foreach ($validationErrors as $error) {
            $pattern = $error['type'] ?? 'unknown';
            $key = "gdevelop_validation_patterns:{$pattern}";
            $count = Cache::get($key, 0);
            Cache::put($key, $count + 1, now()->addDays(7));
        }
    }

    private function getErrorCount(string $sessionId, string $errorType): int
    {
        $key = "gdevelop_errors:{$sessionId}:{$errorType}";
        return Cache::get($key, 0);
    }

    private function checkGDevelopCliAvailability(): bool
    {
        try {
            $result = shell_exec('which gdevelop-cli 2>/dev/null');
            return !empty($result);
        } catch (Exception $e) {
            return false;
        }
    }

    private function checkDiskSpace(): array
    {
        $path = storage_path('gdevelop');
        $bytes = disk_free_space($path);
        
        return [
            'free_bytes' => $bytes,
            'free_gb' => round($bytes / (1024 ** 3), 2),
            'sufficient' => $bytes > (1024 ** 3), // At least 1GB free
        ];
    }

    private function getMemoryUsage(): array
    {
        return [
            'current_mb' => round(memory_get_usage(true) / (1024 ** 2), 2),
            'peak_mb' => round(memory_get_peak_usage(true) / (1024 ** 2), 2),
            'limit_mb' => ini_get('memory_limit'),
        ];
    }

    private function getActiveSessionCount(): int
    {
        // This would typically query the database for active sessions
        return \App\Models\GDevelopGameSession::where('updated_at', '>', now()->subHours(1))->count();
    }

    private function getRecentErrorRate(): float
    {
        // Calculate error rate from cache data
        $totalOperations = Cache::get('gdevelop_total_operations_24h', 1);
        $totalErrors = Cache::get('gdevelop_total_errors_24h', 0);
        
        return $totalOperations > 0 ? ($totalErrors / $totalOperations) * 100 : 0;
    }
}