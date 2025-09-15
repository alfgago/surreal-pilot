<?php

namespace App\Exceptions\GDevelop;

use Exception;
use Throwable;

class GDevelopCliException extends Exception
{
    public function __construct(
        string $message,
        public readonly string $command,
        public readonly string $stdout,
        public readonly string $stderr,
        public readonly int $exitCode,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $exitCode, $previous);
    }

    /**
     * Get user-friendly error message based on the CLI error
     */
    public function getUserFriendlyMessage(): string
    {
        // Check for common GDevelop CLI errors and provide helpful messages
        if (str_contains($this->stderr, 'ENOENT') || str_contains($this->stderr, 'command not found')) {
            return 'GDevelop CLI is not installed or not found in the system PATH. Please ensure GDevelop CLI is properly installed.';
        }

        if (str_contains($this->stderr, 'permission denied') || str_contains($this->stderr, 'EACCES')) {
            return 'Permission denied when executing GDevelop CLI. Please check file permissions and try again.';
        }

        if (str_contains($this->stderr, 'out of memory') || str_contains($this->stderr, 'ENOMEM')) {
            return 'Insufficient memory to complete the operation. Please try with a simpler game or contact support.';
        }

        if (str_contains($this->stderr, 'timeout') || $this->exitCode === 124) {
            return 'The operation timed out. This might be due to a complex game or system load. Please try again.';
        }

        if (str_contains($this->stderr, 'invalid project') || str_contains($this->stderr, 'malformed')) {
            return 'The game project appears to be corrupted or invalid. Please try creating a new game.';
        }

        // Default message for unknown errors
        return 'An error occurred while processing your game. Please try again or contact support if the problem persists.';
    }

    /**
     * Get debugging information for developers
     */
    public function getDebugInfo(): array
    {
        return [
            'command' => $this->command,
            'exit_code' => $this->exitCode,
            'stdout' => $this->stdout,
            'stderr' => $this->stderr,
            'timestamp' => now()->toISOString(),
            'suggested_action' => $this->getSuggestedAction(),
        ];
    }

    /**
     * Get suggested action based on the error
     */
    public function getSuggestedAction(): string
    {
        if (str_contains($this->stderr, 'ENOENT') || str_contains($this->stderr, 'command not found')) {
            return 'Install GDevelop CLI using: npm install -g gdevelop-cli';
        }

        if (str_contains($this->stderr, 'permission denied')) {
            return 'Check file permissions and ensure the process has write access to the workspace directory';
        }

        if (str_contains($this->stderr, 'out of memory')) {
            return 'Reduce game complexity or increase system memory allocation';
        }

        if (str_contains($this->stderr, 'timeout')) {
            return 'Retry the operation or increase the timeout limit in configuration';
        }

        return 'Check the error details and retry the operation';
    }

    /**
     * Determine if this error is retryable
     */
    public function isRetryable(): bool
    {
        // Don't retry for permanent errors
        if (str_contains($this->stderr, 'ENOENT') || str_contains($this->stderr, 'command not found')) {
            return false;
        }

        if (str_contains($this->stderr, 'permission denied')) {
            return false;
        }

        if (str_contains($this->stderr, 'invalid project') || str_contains($this->stderr, 'malformed')) {
            return false;
        }

        // Retry for temporary errors
        if (str_contains($this->stderr, 'timeout') || $this->exitCode === 124) {
            return true;
        }

        if (str_contains($this->stderr, 'busy') || str_contains($this->stderr, 'locked')) {
            return true;
        }

        // Default to retryable for unknown errors
        return true;
    }
}