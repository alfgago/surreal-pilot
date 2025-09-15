<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class GDevelopTemplateService
{
    private const TEMPLATE_PATH = 'gdevelop/templates';
    
    private array $availableTemplates = [
        'basic' => 'Basic Game',
        'platformer' => 'Platformer Game',
        'tower-defense' => 'Tower Defense Game',
        'puzzle' => 'Puzzle Game',
        'arcade' => 'Arcade Game',
    ];

    /**
     * Get all available game templates
     */
    public function getAvailableTemplates(): array
    {
        return $this->availableTemplates;
    }

    /**
     * Load a specific game template by name
     */
    public function loadTemplate(string $templateName): array
    {
        if (!array_key_exists($templateName, $this->availableTemplates)) {
            throw new InvalidArgumentException("Template '{$templateName}' not found");
        }

        $templatePath = storage_path(self::TEMPLATE_PATH . "/{$templateName}.json");
        
        if (!File::exists($templatePath)) {
            throw new InvalidArgumentException("Template file for '{$templateName}' not found");
        }

        $templateContent = File::get($templatePath);
        $templateData = json_decode($templateContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException("Invalid JSON in template '{$templateName}': " . json_last_error_msg());
        }

        return $templateData;
    }

    /**
     * Get template by game type description
     */
    public function getTemplateByType(string $gameType): array
    {
        $gameType = strtolower(trim($gameType));
        
        // Map common game type descriptions to templates
        $typeMapping = [
            'platformer' => 'platformer',
            'platform' => 'platformer',
            'jump' => 'platformer',
            'mario' => 'platformer',
            
            'tower defense' => 'tower-defense',
            'tower defence' => 'tower-defense',
            'td' => 'tower-defense',
            'defense' => 'tower-defense',
            'defence' => 'tower-defense',
            
            'puzzle' => 'puzzle',
            'match' => 'puzzle',
            'grid' => 'puzzle',
            'tile' => 'puzzle',
            'match-3' => 'puzzle',
            
            'arcade' => 'arcade',
            'shooter' => 'arcade',
            'space' => 'arcade',
            'action' => 'arcade',
            'retro' => 'arcade',
            
            'basic' => 'basic',
            'simple' => 'basic',
            'empty' => 'basic',
            'blank' => 'basic',
        ];

        // Check for direct matches first
        if (isset($typeMapping[$gameType])) {
            return $this->loadTemplate($typeMapping[$gameType]);
        }

        // Check for partial matches
        foreach ($typeMapping as $keyword => $template) {
            if (str_contains($gameType, $keyword)) {
                return $this->loadTemplate($template);
            }
        }

        // Default to basic template if no match found
        return $this->loadTemplate('basic');
    }

    /**
     * Create a new game from template with custom properties
     */
    public function createGameFromTemplate(string $templateName, array $customProperties = []): array
    {
        $template = $this->loadTemplate($templateName);
        
        // Apply custom properties if provided
        if (!empty($customProperties)) {
            $template = $this->applyCustomProperties($template, $customProperties);
        }

        // Generate unique project UUID
        $template['properties']['projectUuid'] = $this->generateUuid();
        
        return $template;
    }

    /**
     * Apply custom properties to a template
     */
    private function applyCustomProperties(array $template, array $customProperties): array
    {
        // Merge custom properties into template properties
        if (isset($customProperties['name'])) {
            $template['properties']['name'] = $customProperties['name'];
        }
        
        if (isset($customProperties['description'])) {
            $template['properties']['description'] = $customProperties['description'];
        }
        
        if (isset($customProperties['author'])) {
            $template['properties']['author'] = $customProperties['author'];
        }
        
        if (isset($customProperties['orientation'])) {
            $template['properties']['orientation'] = $customProperties['orientation'];
        }

        // Apply custom variables if provided
        if (isset($customProperties['variables']) && is_array($customProperties['variables'])) {
            foreach ($customProperties['variables'] as $variable) {
                if (isset($variable['name'], $variable['type'], $variable['value'])) {
                    $template['variables'][] = $variable;
                }
            }
        }

        return $template;
    }

    /**
     * Generate a UUID for the project
     */
    private function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    /**
     * Validate if a template exists
     */
    public function templateExists(string $templateName): bool
    {
        return array_key_exists($templateName, $this->availableTemplates) &&
               File::exists(storage_path(self::TEMPLATE_PATH . "/{$templateName}.json"));
    }

    /**
     * Get template metadata without loading full content
     */
    public function getTemplateMetadata(string $templateName): array
    {
        if (!$this->templateExists($templateName)) {
            throw new InvalidArgumentException("Template '{$templateName}' not found");
        }

        $template = $this->loadTemplate($templateName);
        
        return [
            'name' => $template['properties']['name'] ?? $templateName,
            'description' => $template['properties']['description'] ?? '',
            'author' => $template['properties']['author'] ?? 'Unknown',
            'version' => $template['properties']['version'] ?? '1.0.0',
            'orientation' => $template['properties']['orientation'] ?? 'default',
            'objectCount' => count($template['objects'] ?? []),
            'layoutCount' => count($template['layouts'] ?? []),
            'variableCount' => count($template['variables'] ?? []),
        ];
    }
}