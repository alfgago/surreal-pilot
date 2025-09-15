<?php

namespace Tests\Unit;

use App\Exceptions\GDevelop\GDevelopCliException;
use App\Exceptions\GDevelop\GameJsonValidationException;
use App\Exceptions\GDevelop\GDevelopPreviewException;
use App\Exceptions\GDevelop\GDevelopExportException;
use Tests\TestCase;

class GDevelopExceptionsTest extends TestCase
{
    public function test_gdevelop_cli_exception_properties()
    {
        $exception = new GDevelopCliException(
            message: 'CLI command failed',
            command: 'gdevelop-cli build game.json',
            stdout: 'Building game...',
            stderr: 'Error: Permission denied',
            exitCode: 1
        );

        $this->assertEquals('CLI command failed', $exception->getMessage());
        $this->assertEquals('gdevelop-cli build game.json', $exception->command);
        $this->assertEquals('Building game...', $exception->stdout);
        $this->assertEquals('Error: Permission denied', $exception->stderr);
        $this->assertEquals(1, $exception->exitCode);
    }

    public function test_cli_exception_user_friendly_messages()
    {
        // Test command not found error
        $exception = new GDevelopCliException(
            message: 'Command failed',
            command: 'gdevelop-cli',
            stdout: '',
            stderr: 'command not found',
            exitCode: 127
        );

        $message = $exception->getUserFriendlyMessage();
        $this->assertStringContainsString('not installed', $message);
        $this->assertStringContainsString('PATH', $message);

        // Test permission denied error
        $exception = new GDevelopCliException(
            message: 'Command failed',
            command: 'gdevelop-cli',
            stdout: '',
            stderr: 'permission denied',
            exitCode: 1
        );

        $message = $exception->getUserFriendlyMessage();
        $this->assertStringContainsString('Permission denied', $message);

        // Test timeout error
        $exception = new GDevelopCliException(
            message: 'Command failed',
            command: 'gdevelop-cli',
            stdout: '',
            stderr: 'timeout',
            exitCode: 124
        );

        $message = $exception->getUserFriendlyMessage();
        $this->assertStringContainsString('timed out', $message);
    }

    public function test_cli_exception_retryable_logic()
    {
        // Non-retryable: command not found
        $exception = new GDevelopCliException(
            message: 'Command failed',
            command: 'gdevelop-cli',
            stdout: '',
            stderr: 'command not found',
            exitCode: 127
        );
        $this->assertFalse($exception->isRetryable());

        // Non-retryable: permission denied
        $exception = new GDevelopCliException(
            message: 'Command failed',
            command: 'gdevelop-cli',
            stdout: '',
            stderr: 'permission denied',
            exitCode: 1
        );
        $this->assertFalse($exception->isRetryable());

        // Retryable: timeout
        $exception = new GDevelopCliException(
            message: 'Command failed',
            command: 'gdevelop-cli',
            stdout: '',
            stderr: 'timeout',
            exitCode: 124
        );
        $this->assertTrue($exception->isRetryable());

        // Retryable: busy/locked
        $exception = new GDevelopCliException(
            message: 'Command failed',
            command: 'gdevelop-cli',
            stdout: '',
            stderr: 'resource busy',
            exitCode: 1
        );
        $this->assertTrue($exception->isRetryable());
    }

    public function test_cli_exception_suggested_actions()
    {
        $exception = new GDevelopCliException(
            message: 'Command failed',
            command: 'gdevelop-cli',
            stdout: '',
            stderr: 'command not found',
            exitCode: 127
        );

        $action = $exception->getSuggestedAction();
        $this->assertStringContainsString('npm install', $action);
        $this->assertStringContainsString('gdevelop-cli', $action);
    }

    public function test_game_json_validation_exception_properties()
    {
        $validationErrors = [
            ['type' => 'required', 'field' => 'properties.name', 'message' => 'is required'],
            ['type' => 'type', 'field' => 'layouts', 'message' => 'must be an array']
        ];

        $gameJson = ['invalid' => 'structure'];

        $exception = new GameJsonValidationException(
            message: 'Validation failed',
            validationErrors: $validationErrors,
            gameJson: $gameJson
        );

        $this->assertEquals('Validation failed', $exception->getMessage());
        $this->assertEquals($validationErrors, $exception->getValidationErrors());
        $this->assertEquals($gameJson, $exception->getGameJson());
    }

    public function test_validation_exception_user_friendly_messages()
    {
        // Single error
        $validationErrors = [
            ['type' => 'required', 'field' => 'properties.name', 'message' => 'is required']
        ];

        $exception = new GameJsonValidationException(
            message: 'Validation failed',
            validationErrors: $validationErrors,
            gameJson: []
        );

        $message = $exception->getUserFriendlyMessage();
        $this->assertStringContainsString('game name', $message);
        $this->assertStringContainsString('required', $message);

        // Multiple errors
        $validationErrors = [
            ['type' => 'required', 'field' => 'properties.name', 'message' => 'is required'],
            ['type' => 'type', 'field' => 'layouts', 'message' => 'must be an array']
        ];

        $exception = new GameJsonValidationException(
            message: 'Validation failed',
            validationErrors: $validationErrors,
            gameJson: []
        );

        $message = $exception->getUserFriendlyMessage();
        $this->assertStringContainsString('2 validation errors', $message);
    }

    public function test_validation_exception_critical_error_prioritization()
    {
        $validationErrors = [
            ['type' => 'constraint', 'field' => 'objects.0.name', 'message' => 'too long'],
            ['type' => 'required', 'field' => 'properties.name', 'message' => 'is required'],
            ['type' => 'type', 'field' => 'layouts', 'message' => 'must be an array']
        ];

        $exception = new GameJsonValidationException(
            message: 'Validation failed',
            validationErrors: $validationErrors,
            gameJson: []
        );

        $criticalError = $exception->getCriticalError();
        $this->assertEquals('required', $criticalError['type']);
        $this->assertEquals('properties.name', $criticalError['field']);
    }

    public function test_validation_exception_recoverable_logic()
    {
        // Recoverable: simple validation errors
        $validationErrors = [
            ['type' => 'constraint', 'field' => 'objects.0.name', 'message' => 'too long']
        ];

        $exception = new GameJsonValidationException(
            message: 'Validation failed',
            validationErrors: $validationErrors,
            gameJson: []
        );
        $this->assertTrue($exception->isRecoverable());

        // Non-recoverable: structure errors
        $validationErrors = [
            ['type' => 'structure', 'field' => 'root', 'message' => 'invalid structure']
        ];

        $exception = new GameJsonValidationException(
            message: 'Validation failed',
            validationErrors: $validationErrors,
            gameJson: []
        );
        $this->assertFalse($exception->isRecoverable());

        // Non-recoverable: missing required root properties
        $validationErrors = [
            ['type' => 'required', 'field' => 'properties.name', 'message' => 'is required']
        ];

        $exception = new GameJsonValidationException(
            message: 'Validation failed',
            validationErrors: $validationErrors,
            gameJson: []
        );
        $this->assertFalse($exception->isRecoverable());
    }

    public function test_preview_exception_properties()
    {
        $exception = new GDevelopPreviewException(
            message: 'Preview build failed',
            sessionId: 'test-session-123',
            previewPath: '/path/to/preview',
            buildLogs: ['Error: Build failed', 'Warning: Missing asset']
        );

        $this->assertEquals('Preview build failed', $exception->getMessage());
        $this->assertEquals('test-session-123', $exception->sessionId);
        $this->assertEquals('/path/to/preview', $exception->previewPath);
        $this->assertEquals(['Error: Build failed', 'Warning: Missing asset'], $exception->buildLogs);
    }

    public function test_preview_exception_user_friendly_messages()
    {
        $exception = new GDevelopPreviewException(
            message: 'build failed',
            sessionId: 'test-session'
        );

        $message = $exception->getUserFriendlyMessage();
        $this->assertStringContainsString('preview', $message);
        $this->assertStringContainsString('game structure', $message);
    }

    public function test_export_exception_properties()
    {
        $exportOptions = ['minify' => true, 'mobile_optimized' => false];

        $exception = new GDevelopExportException(
            message: 'Export failed',
            sessionId: 'test-session-456',
            exportPath: '/path/to/export',
            exportOptions: $exportOptions,
            buildLogs: ['Starting export...', 'Error: Export failed']
        );

        $this->assertEquals('Export failed', $exception->getMessage());
        $this->assertEquals('test-session-456', $exception->sessionId);
        $this->assertEquals('/path/to/export', $exception->exportPath);
        $this->assertEquals($exportOptions, $exception->exportOptions);
        $this->assertEquals(['Starting export...', 'Error: Export failed'], $exception->buildLogs);
    }

    public function test_export_exception_user_friendly_messages()
    {
        $exception = new GDevelopExportException(
            message: 'zip creation failed',
            sessionId: 'test-session'
        );

        $message = $exception->getUserFriendlyMessage();
        $this->assertStringContainsString('downloadable', $message);
        $this->assertStringContainsString('package', $message);
    }

    public function test_all_exceptions_provide_debug_info()
    {
        $cliException = new GDevelopCliException(
            message: 'CLI failed',
            command: 'test',
            stdout: 'output',
            stderr: 'error',
            exitCode: 1
        );
        $debugInfo = $cliException->getDebugInfo();
        $this->assertArrayHasKey('command', $debugInfo);
        $this->assertArrayHasKey('timestamp', $debugInfo);

        $validationException = new GameJsonValidationException(
            message: 'Validation failed',
            validationErrors: [['type' => 'test']],
            gameJson: []
        );
        $debugInfo = $validationException->getDebugInfo();
        $this->assertArrayHasKey('validation_errors', $debugInfo);
        $this->assertArrayHasKey('timestamp', $debugInfo);

        $previewException = new GDevelopPreviewException(
            message: 'Preview failed',
            sessionId: 'test'
        );
        $debugInfo = $previewException->getDebugInfo();
        $this->assertArrayHasKey('session_id', $debugInfo);
        $this->assertArrayHasKey('timestamp', $debugInfo);

        $exportException = new GDevelopExportException(
            message: 'Export failed',
            sessionId: 'test'
        );
        $debugInfo = $exportException->getDebugInfo();
        $this->assertArrayHasKey('session_id', $debugInfo);
        $this->assertArrayHasKey('timestamp', $debugInfo);
    }
}