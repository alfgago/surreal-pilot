<?php

namespace App\Exceptions\GDevelop;

use Exception;
use Throwable;

class GameJsonValidationException extends Exception
{
    public function __construct(
        string $message,
        public readonly array $validationErrors,
        public readonly array $gameJson,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Get user-friendly error message based on validation errors
     */
    public function getUserFriendlyMessage(): string
    {
        if (empty($this->validationErrors)) {
            return 'The game data is invalid. Please try regenerating the game.';
        }

        $errorCount = count($this->validationErrors);
        
        if ($errorCount === 1) {
            $error = $this->validationErrors[0];
            return $this->formatSingleError($error);
        }

        return "Found {$errorCount} validation errors in the game data. Please check the game structure and try again.";
    }

    /**
     * Format a single validation error for user display
     */
    private function formatSingleError(array $error): string
    {
        $field = $error['field'] ?? 'unknown field';
        $message = $error['message'] ?? 'is invalid';

        // Provide user-friendly field names
        $friendlyFields = [
            'properties.name' => 'game name',
            'properties.version' => 'game version',
            'layouts' => 'game scenes',
            'objects' => 'game objects',
            'resources' => 'game assets',
            'variables' => 'game variables',
        ];

        $friendlyField = $friendlyFields[$field] ?? $field;

        // Provide user-friendly error messages
        if (str_contains($message, 'required')) {
            return "The {$friendlyField} is required but missing from the game data.";
        }

        if (str_contains($message, 'invalid type')) {
            return "The {$friendlyField} has an invalid format.";
        }

        if (str_contains($message, 'too long')) {
            return "The {$friendlyField} is too long. Please use a shorter value.";
        }

        if (str_contains($message, 'too short')) {
            return "The {$friendlyField} is too short. Please provide a longer value.";
        }

        return "The {$friendlyField} {$message}.";
    }

    /**
     * Get detailed validation errors for debugging
     */
    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }

    /**
     * Get the invalid game JSON
     */
    public function getGameJson(): array
    {
        return $this->gameJson;
    }

    /**
     * Get debugging information for developers
     */
    public function getDebugInfo(): array
    {
        return [
            'validation_errors' => $this->validationErrors,
            'error_count' => count($this->validationErrors),
            'game_json_size' => strlen(json_encode($this->gameJson)),
            'has_properties' => isset($this->gameJson['properties']),
            'has_layouts' => isset($this->gameJson['layouts']),
            'has_objects' => isset($this->gameJson['objects']),
            'timestamp' => now()->toISOString(),
            'suggested_action' => $this->getSuggestedAction(),
        ];
    }

    /**
     * Get suggested action based on validation errors
     */
    public function getSuggestedAction(): string
    {
        if (empty($this->validationErrors)) {
            return 'Regenerate the game using a different approach';
        }

        $errorTypes = array_column($this->validationErrors, 'type');

        if (in_array('required', $errorTypes)) {
            return 'Ensure all required fields are present in the game JSON';
        }

        if (in_array('type', $errorTypes)) {
            return 'Check that all fields have the correct data types';
        }

        if (in_array('format', $errorTypes)) {
            return 'Verify that all fields follow the expected format';
        }

        return 'Review the game JSON structure and fix the validation errors';
    }

    /**
     * Get the most critical error that should be addressed first
     */
    public function getCriticalError(): ?array
    {
        if (empty($this->validationErrors)) {
            return null;
        }

        // Prioritize errors by severity
        $priorities = [
            'required' => 1,
            'type' => 2,
            'format' => 3,
            'constraint' => 4,
        ];

        $sortedErrors = $this->validationErrors;
        usort($sortedErrors, function ($a, $b) use ($priorities) {
            $priorityA = $priorities[$a['type'] ?? 'constraint'] ?? 5;
            $priorityB = $priorities[$b['type'] ?? 'constraint'] ?? 5;
            return $priorityA <=> $priorityB;
        });

        return $sortedErrors[0];
    }

    /**
     * Check if the validation errors are recoverable
     */
    public function isRecoverable(): bool
    {
        if (empty($this->validationErrors)) {
            return false;
        }

        // Check for unrecoverable errors
        foreach ($this->validationErrors as $error) {
            $type = $error['type'] ?? '';
            
            // Structure errors are usually unrecoverable
            if ($type === 'structure' || $type === 'schema') {
                return false;
            }

            // Missing required root properties are hard to recover from
            if ($type === 'required' && str_starts_with($error['field'] ?? '', 'properties.')) {
                return false;
            }
        }

        return true;
    }
}