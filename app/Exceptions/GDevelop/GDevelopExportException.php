<?php

namespace App\Exceptions\GDevelop;

use Exception;
use Throwable;

class GDevelopExportException extends Exception
{
    public function __construct(
        string $message,
        public readonly string $sessionId,
        public readonly ?string $exportPath = null,
        public readonly ?array $exportOptions = null,
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
        if (str_contains($this->message, 'export failed')) {
            return 'Failed to export your game. There might be an issue with the game structure or export settings.';
        }

        if (str_contains($this->message, 'zip creation failed')) {
            return 'Failed to create the downloadable game package. Please try again.';
        }

        if (str_contains($this->message, 'assets missing')) {
            return 'Some game assets are missing and cannot be exported. Please regenerate your game.';
        }

        if (str_contains($this->message, 'timeout')) {
            return 'Export process timed out. Your game might be too large or complex.';
        }

        if (str_contains($this->message, 'disk space')) {
            return 'Insufficient disk space to complete the export. Please try again later.';
        }

        return 'Unable to export your game. Please try again or contact support.';
    }

    /**
     * Get debugging information
     */
    public function getDebugInfo(): array
    {
        return [
            'session_id' => $this->sessionId,
            'export_path' => $this->exportPath,
            'export_options' => $this->exportOptions,
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
        if (str_contains($this->message, 'export failed')) {
            return 'Check game structure and try with different export options';
        }

        if (str_contains($this->message, 'zip creation failed')) {
            return 'Retry export or try with lower compression settings';
        }

        if (str_contains($this->message, 'assets missing')) {
            return 'Regenerate the game to ensure all assets are available';
        }

        if (str_contains($this->message, 'timeout')) {
            return 'Try exporting with mobile optimization disabled or contact support';
        }

        return 'Retry export with different settings or contact support';
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

        if (str_contains($this->message, 'zip creation failed')) {
            return true;
        }

        if (str_contains($this->message, 'export failed')) {
            return true;
        }

        return true;
    }
}