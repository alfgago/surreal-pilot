<?php

namespace Tests\Unit;

use App\Exceptions\GDevelop\GDevelopCliException;
use App\Exceptions\GDevelop\GameJsonValidationException;
use App\Exceptions\GDevelop\GDevelopPreviewException;
use App\Exceptions\GDevelop\GDevelopExportException;
use App\Services\GDevelopErrorRecoveryService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class GDevelopErrorRecoveryServiceTest extends TestCase
{
    use RefreshDatabase;

    private GDevelopErrorRecoveryService $errorRecovery;

    protected function setUp(): void
    {
        parent::setUp();
        $this->errorRecovery = new GDevelopErrorRecoveryService();
    }

    public function test_execute_with_retry_succeeds_on_first_attempt()
    {
        $result = $this->errorRecovery->executeWithRetry(
            operation: fn() => 'success',
            operationType: 'test_operation'
        );

        $this->assertEquals('success', $result);
    }

    public function test_execute_with_retry_succeeds_after_retryable_error()
    {
        $attempt = 0;
        
        $result = $this->errorRecovery->executeWithRetry(
            operation: function () use (&$attempt) {
                $attempt++;
                if ($attempt === 1) {
                    throw new GDevelopCliException(
                        message: 'timeout error',
                        command: 'test command',
                        stdout: '',
                        stderr: 'timeout occurred',
                        exitCode: 124
                    );
                }
                return 'success after retry';
            },
            operationType: 'test_operation'
        );

        $this->assertEquals('success after retry', $result);
        $this->assertEquals(2, $attempt);
    }

    public function test_execute_with_retry_fails_for_non_retryable_error()
    {
        $this->expectException(GDevelopCliException::class);
        $this->expectExceptionMessage('CLI not found');

        $this->errorRecovery->executeWithRetry(
            operation: function () {
                throw new GDevelopCliException(
                    message: 'CLI not found',
                    command: 'gdevelop-cli',
                    stdout: '',
                    stderr: 'command not found',
                    exitCode: 127
                );
            },
            operationType: 'test_operation'
        );
    }

    public function test_execute_with_retry_fails_after_max_attempts()
    {
        $this->expectException(GDevelopCliException::class);

        $this->errorRecovery->executeWithRetry(
            operation: function () {
                throw new GDevelopCliException(
                    message: 'timeout error',
                    command: 'test command',
                    stdout: '',
                    stderr: 'timeout occurred',
                    exitCode: 124
                );
            },
            operationType: 'test_operation'
        );
    }

    public function test_handle_cli_error_returns_proper_error_info()
    {
        $exception = new GDevelopCliException(
            message: 'CLI command failed',
            command: 'gdevelop-cli build',
            stdout: 'Building...',
            stderr: 'permission denied',
            exitCode: 1
        );

        $errorInfo = $this->errorRecovery->handleCliError($exception, 'test-session');

        $this->assertEquals('cli_error', $errorInfo['error_type']);
        $this->assertStringContainsString('Permission denied', $errorInfo['user_message']);
        $this->assertArrayHasKey('debug_info', $errorInfo);
        $this->assertArrayHasKey('suggested_action', $errorInfo);
        $this->assertFalse($errorInfo['is_retryable']); // Permission errors are not retryable
    }

    public function test_handle_validation_error_returns_proper_error_info()
    {
        $validationErrors = [
            ['type' => 'required', 'field' => 'properties.name', 'message' => 'is required'],
            ['type' => 'type', 'field' => 'layouts', 'message' => 'must be an array']
        ];

        $exception = new GameJsonValidationException(
            message: 'Validation failed',
            validationErrors: $validationErrors,
            gameJson: ['invalid' => 'json']
        );

        $errorInfo = $this->errorRecovery->handleValidationError($exception, 'test-session');

        $this->assertEquals('validation_error', $errorInfo['error_type']);
        $this->assertCount(2, $errorInfo['validation_errors']);
        $this->assertArrayHasKey('critical_error', $errorInfo);
        $this->assertFalse($errorInfo['is_recoverable']); // Required field errors are not recoverable
    }

    public function test_handle_preview_error_returns_proper_error_info()
    {
        $exception = new GDevelopPreviewException(
            message: 'Preview build failed',
            sessionId: 'test-session',
            previewPath: '/path/to/preview',
            buildLogs: ['Error: Build failed']
        );

        $errorInfo = $this->errorRecovery->handlePreviewError($exception, 'test-session');

        $this->assertEquals('preview_error', $errorInfo['error_type']);
        $this->assertStringContainsString('preview', strtolower($errorInfo['user_message']));
        $this->assertTrue($errorInfo['is_retryable']);
    }

    public function test_handle_export_error_returns_proper_error_info()
    {
        $exception = new GDevelopExportException(
            message: 'Export failed',
            sessionId: 'test-session',
            exportPath: '/path/to/export',
            exportOptions: ['minify' => true]
        );

        $errorInfo = $this->errorRecovery->handleExportError($exception, 'test-session');

        $this->assertEquals('export_error', $errorInfo['error_type']);
        $this->assertStringContainsString('export', strtolower($errorInfo['user_message']));
        $this->assertTrue($errorInfo['is_retryable']);
    }

    public function test_get_system_health_status_returns_health_info()
    {
        $healthStatus = $this->errorRecovery->getSystemHealthStatus();

        $this->assertArrayHasKey('gdevelop_cli_available', $healthStatus);
        $this->assertArrayHasKey('disk_space_available', $healthStatus);
        $this->assertArrayHasKey('memory_usage', $healthStatus);
        $this->assertArrayHasKey('active_sessions', $healthStatus);
        $this->assertArrayHasKey('error_rate', $healthStatus);
    }

    public function test_should_suggest_fallback_after_multiple_errors()
    {
        $sessionId = 'test-session';
        $errorType = 'cli_error';

        // Simulate multiple errors
        Cache::put("gdevelop_errors:{$sessionId}:{$errorType}", 3, now()->addHours(24));

        $shouldSuggest = $this->errorRecovery->shouldSuggestFallback($sessionId, $errorType);

        $this->assertTrue($shouldSuggest);
    }

    public function test_get_fallback_suggestions_returns_appropriate_suggestions()
    {
        $suggestions = $this->errorRecovery->getFallbackSuggestions('test-session', 'cli_error');

        $this->assertIsArray($suggestions);
        $this->assertNotEmpty($suggestions);
        $this->assertStringContainsString('simpler', $suggestions[0]);
    }

    public function test_validation_error_patterns_are_tracked()
    {
        $validationErrors = [
            ['type' => 'required', 'field' => 'properties.name'],
            ['type' => 'type', 'field' => 'layouts']
        ];

        $exception = new GameJsonValidationException(
            message: 'Validation failed',
            validationErrors: $validationErrors,
            gameJson: []
        );

        $this->errorRecovery->handleValidationError($exception, 'test-session');

        // Check that patterns were tracked
        $requiredCount = Cache::get('gdevelop_validation_patterns:required', 0);
        $typeCount = Cache::get('gdevelop_validation_patterns:type', 0);

        $this->assertGreaterThan(0, $requiredCount);
        $this->assertGreaterThan(0, $typeCount);
    }

    public function test_error_frequency_is_tracked_per_session()
    {
        $sessionId = 'test-session';
        $errorType = 'cli_error';

        $exception = new GDevelopCliException(
            message: 'Test error',
            command: 'test',
            stdout: '',
            stderr: 'error',
            exitCode: 1
        );

        // Handle error multiple times
        $this->errorRecovery->handleCliError($exception, $sessionId);
        $this->errorRecovery->handleCliError($exception, $sessionId);

        $errorCount = Cache::get("gdevelop_errors:{$sessionId}:{$errorType}", 0);
        $this->assertEquals(2, $errorCount);
    }
}