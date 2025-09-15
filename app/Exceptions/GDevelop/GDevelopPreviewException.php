<?php

namespace App\Exceptions\GDevelop;

use Exception;
use Throwable;

class GDevelopPreviewException extends Exception
{
    public function __construct(
        string $message,
        public readonly string $sessionId,
        public readonly ?string $previewPath = null,
        public readonly ?array $buildLogs = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Get user-friendly error message
     */
    public function getUserFriendlyMessage(): string
    {
        if (str_contains($this->message, 'build failed')) {
            return 'Failed to generate game preview. There might be an issue with your game structure.';
        }

        if (str_contains($this->message, 'assets missing')) {
            return 'Some game assets are missing or corrupted. Please try regenerating the game.';
        }

        if (str_contains($this->message, 'timeout')) {
            return 'Preview generation timed out. Your game might be too complex for quick preview.';
        }

        if (str_contains($this->message, 'disk space')) {
            return 'Insufficient disk space to generate preview. Please try again later.';
        }

        return 'Unable to generate game preview. Please try again or simplify your game.';
    }

    /**
     * Get debugging information
     */
    public function getDebugInfo(): array
    {
        return [
            'session_id' => $this->sessionId,
            'preview_path' => $this->previewPath,
            'build_logs' => $this->buildLogs,
            'error_message' => $this->getMessage(),
            'timestamp' => now()->toISOString(),
            'suggested_action' => $this->getSuggestedAction(),
        ];
    }

    /**
     * Get suggested action
     */
    public function getSuggestedAction(): string
    {
        if (str_contains($this->message, 'build failed')) {
            return 'Check game JSON structure and try regenerating with simpler requirements';
        }

        if (str_contains($this->message, 'assets missing')) {
            return 'Regenerate the game to ensure all assets are properly created';
        }

        if (str_contains($this->message, 'timeout')) {
            return 'Try creating a simpler game or increase preview timeout in configuration';
        }

        return 'Retry preview generation or contact support if the issue persists';
    }

    /**
     * Check if this error is retryable
     */
    public function isRetryable(): bool
    {
        // Don't retry for permanent errors
        if (str_contains($this->message, 'disk space')) {
            return false;
        }

        if (str_contains($this->message, 'permission denied')) {
            return false;
        }

        // Retry for temporary errors
        if (str_contains($this->message, 'timeout')) {
            return true;
        }

        if (str_contains($this->message, 'build failed')) {
            return true;
        }

        return true;
    }
}