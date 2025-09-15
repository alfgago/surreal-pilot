<?php

namespace App\Services;

use App\Exceptions\GameJsonValidationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class GDevelopJsonValidationService
{
    /**
     * Maximum allowed JSON size (5MB)
     */
    private const MAX_JSON_SIZE = 5 * 1024 * 1024;

    /**
     * Maximum allowed string length for text fields
     */
    private const MAX_STRING_LENGTH = 1000;

    /**
     * Maximum allowed array size
     */
    private const MAX_ARRAY_SIZE = 1000;

    /**
     * Maximum nesting depth for JSON objects
     */
    private const MAX_NESTING_DEPTH = 10;

    /**
     * Allowed file extensions for assets
     */
    private const ALLOWED_ASSET_EXTENSIONS = [
        'png', 'jpg', 'jpeg', 'gif', 'webp', 'svg',
        'mp3', 'wav', 'ogg', 'json', 'ttf', 'otf'
    ];

    /**
     * Validate and sanitize GDevelop game JSON
     *
     * @param array $gameJson
     * @return array
     * @throws GameJsonValidationException
     */
    public function validateAndSanitizeGameJson(array $gameJson): array
    {
        // Check JSON size
        $jsonSize = strlen(json_encode($gameJson));
        if ($jsonSize > self::MAX_JSON_SIZE) {
            throw new GameJsonValidationException("Game JSON exceeds maximum size limit of " . (self::MAX_JSON_SIZE / 1024 / 1024) . "MB");
        }

        // Validate basic structure
        $this->validateBasicStructure($gameJson);

        // Sanitize and validate each section
        $sanitizedJson = [
            'firstLayout' => $this->sanitizeString($gameJson['firstLayout'] ?? ''),
            'gdVersion' => $this->sanitizeString($gameJson['gdVersion'] ?? '4.0.98'),
            'properties' => $this->validateAndSanitizeProperties($gameJson['properties'] ?? []),
            'resources' => $this->validateAndSanitizeResources($gameJson['resources'] ?? []),
            'objects' => $this->validateAndSanitizeObjects($gameJson['objects'] ?? []),
            'objectsGroups' => $this->validateAndSanitizeObjectGroups($gameJson['objectsGroups'] ?? []),
            'variables' => $this->validateAndSanitizeVariables($gameJson['variables'] ?? []),
            'layouts' => $this->validateAndSanitizeLayouts($gameJson['layouts'] ?? []),
            'externalEvents' => $this->validateAndSanitizeExternalEvents($gameJson['externalEvents'] ?? []),
            'eventsFunctionsExtensions' => $this->validateAndSanitizeExtensions($gameJson['eventsFunctionsExtensions'] ?? []),
            'externalLayouts' => $this->validateAndSanitizeExternalLayouts($gameJson['externalLayouts'] ?? []),
            'externalSourceFiles' => $this->validateAndSanitizeExternalSourceFiles($gameJson['externalSourceFiles'] ?? [])
        ];

        Log::info('GDevelop game JSON validated and sanitized', [
            'original_size' => $jsonSize,
            'layouts_count' => count($sanitizedJson['layouts']),
            'objects_count' => count($sanitizedJson['objects']),
            'resources_count' => count($sanitizedJson['resources'])
        ]);

        return $sanitizedJson;
    }

    /**
     * Validate basic JSON structure
     */
    private function validateBasicStructure(array $gameJson): void
    {
        $requiredFields = ['properties', 'resources', 'objects', 'layouts'];
        
        foreach ($requiredFields as $field) {
            if (!isset($gameJson[$field])) {
                throw new GameJsonValidationException("Missing required field: {$field}");
            }
            
            if (!is_array($gameJson[$field])) {
                throw new GameJsonValidationException("Field {$field} must be an array");
            }
        }

        // Check nesting depth
        $this->validateNestingDepth($gameJson, 0);
    }

    /**
     * Validate nesting depth to prevent deeply nested objects
     */
    private function validateNestingDepth($data, int $currentDepth): void
    {
        if ($currentDepth > self::MAX_NESTING_DEPTH) {
            throw new GameJsonValidationException("JSON nesting depth exceeds maximum allowed depth of " . self::MAX_NESTING_DEPTH);
        }

        if (is_array($data)) {
            foreach ($data as $item) {
                $this->validateNestingDepth($item, $currentDepth + 1);
            }
        }
    }

    /**
     * Validate and sanitize game properties
     */
    private function validateAndSanitizeProperties(array $properties): array
    {
        return [
            'adaptGameResolutionAtRuntime' => (bool)($properties['adaptGameResolutionAtRuntime'] ?? true),
            'folderProject' => false,
            'orientation' => $this->sanitizeString($properties['orientation'] ?? 'default'),
            'packageName' => $this->sanitizePackageName($properties['packageName'] ?? 'com.example.game'),
            'projectFile' => $this->sanitizeString($properties['projectFile'] ?? ''),
            'scaleMode' => $this->sanitizeString($properties['scaleMode'] ?? 'linear'),
            'sizeOnStartupMode' => $this->sanitizeString($properties['sizeOnStartupMode'] ?? ''),
            'useExternalSourceFiles' => (bool)($properties['useExternalSourceFiles'] ?? false),
            'version' => $this->sanitizeString($properties['version'] ?? '1.0.0'),
            'name' => $this->sanitizeString($properties['name'] ?? 'My Game'),
            'description' => $this->sanitizeString($properties['description'] ?? ''),
            'author' => $this->sanitizeString($properties['author'] ?? ''),
            'windowWidth' => $this->sanitizeInteger($properties['windowWidth'] ?? 800, 320, 4096),
            'windowHeight' => $this->sanitizeInteger($properties['windowHeight'] ?? 600, 240, 4096),
            'latestCompilationDirectory' => '',
            'maxFPS' => $this->sanitizeInteger($properties['maxFPS'] ?? 60, 30, 120),
            'minFPS' => $this->sanitizeInteger($properties['minFPS'] ?? 20, 10, 60),
            'verticalSync' => (bool)($properties['verticalSync'] ?? false),
            'platformSpecificAssets' => $properties['platformSpecificAssets'] ?? [],
            'loadingScreen' => $this->validateAndSanitizeLoadingScreen($properties['loadingScreen'] ?? []),
            'watermark' => $this->validateAndSanitizeWatermark($properties['watermark'] ?? [])
        ];
    }

    /**
     * Validate and sanitize resources
     */
    private function validateAndSanitizeResources(array $resources): array
    {
        if (count($resources) > self::MAX_ARRAY_SIZE) {
            throw new GameJsonValidationException("Too many resources. Maximum allowed: " . self::MAX_ARRAY_SIZE);
        }

        $sanitizedResources = [];
        
        foreach ($resources as $resource) {
            if (!is_array($resource)) {
                continue;
            }

            $sanitizedResource = [
                'alwaysLoaded' => (bool)($resource['alwaysLoaded'] ?? false),
                'file' => $this->sanitizeFilePath($resource['file'] ?? ''),
                'kind' => $this->sanitizeString($resource['kind'] ?? 'image'),
                'metadata' => $this->sanitizeString($resource['metadata'] ?? ''),
                'name' => $this->sanitizeString($resource['name'] ?? ''),
                'smoothed' => (bool)($resource['smoothed'] ?? true),
                'userAdded' => (bool)($resource['userAdded'] ?? true)
            ];

            // Validate file extension
            if ($sanitizedResource['file']) {
                $extension = strtolower(pathinfo($sanitizedResource['file'], PATHINFO_EXTENSION));
                if (!in_array($extension, self::ALLOWED_ASSET_EXTENSIONS)) {
                    Log::warning('Invalid asset extension detected', [
                        'file' => $sanitizedResource['file'],
                        'extension' => $extension
                    ]);
                    continue; // Skip invalid assets
                }
            }

            $sanitizedResources[] = $sanitizedResource;
        }

        return $sanitizedResources;
    }

    /**
     * Validate and sanitize objects
     */
    private function validateAndSanitizeObjects(array $objects): array
    {
        if (count($objects) > self::MAX_ARRAY_SIZE) {
            throw new GameJsonValidationException("Too many objects. Maximum allowed: " . self::MAX_ARRAY_SIZE);
        }

        $sanitizedObjects = [];
        
        foreach ($objects as $object) {
            if (!is_array($object)) {
                continue;
            }

            $sanitizedObject = [
                'name' => $this->sanitizeString($object['name'] ?? ''),
                'type' => $this->sanitizeString($object['type'] ?? ''),
                'updateIfNotVisible' => (bool)($object['updateIfNotVisible'] ?? false),
                'variables' => $this->validateAndSanitizeVariables($object['variables'] ?? []),
                'behaviors' => $this->validateAndSanitizeBehaviors($object['behaviors'] ?? []),
                'animations' => $this->validateAndSanitizeAnimations($object['animations'] ?? [])
            ];

            $sanitizedObjects[] = $sanitizedObject;
        }

        return $sanitizedObjects;
    }

    /**
     * Validate and sanitize layouts
     */
    private function validateAndSanitizeLayouts(array $layouts): array
    {
        if (count($layouts) > 100) { // Reasonable limit for layouts
            throw new GameJsonValidationException("Too many layouts. Maximum allowed: 100");
        }

        $sanitizedLayouts = [];
        
        foreach ($layouts as $layout) {
            if (!is_array($layout)) {
                continue;
            }

            $sanitizedLayout = [
                'b' => $this->sanitizeInteger($layout['b'] ?? 209, 0, 255),
                'disableInputWhenNotFocused' => (bool)($layout['disableInputWhenNotFocused'] ?? true),
                'mangledName' => $this->sanitizeString($layout['mangledName'] ?? ''),
                'name' => $this->sanitizeString($layout['name'] ?? ''),
                'r' => $this->sanitizeInteger($layout['r'] ?? 209, 0, 255),
                'standardSortMethod' => (bool)($layout['standardSortMethod'] ?? true),
                'stopSoundsOnStartup' => (bool)($layout['stopSoundsOnStartup'] ?? true),
                'title' => $this->sanitizeString($layout['title'] ?? ''),
                'v' => $this->sanitizeInteger($layout['v'] ?? 209, 0, 255),
                'uiSettings' => $layout['uiSettings'] ?? [],
                'objectsGroups' => $this->validateAndSanitizeObjectGroups($layout['objectsGroups'] ?? []),
                'variables' => $this->validateAndSanitizeVariables($layout['variables'] ?? []),
                'instances' => $this->validateAndSanitizeInstances($layout['instances'] ?? []),
                'objects' => $this->validateAndSanitizeObjects($layout['objects'] ?? []),
                'events' => $this->validateAndSanitizeEvents($layout['events'] ?? []),
                'layers' => $this->validateAndSanitizeLayers($layout['layers'] ?? [])
            ];

            $sanitizedLayouts[] = $sanitizedLayout;
        }

        return $sanitizedLayouts;
    }

    /**
     * Sanitize string input
     */
    private function sanitizeString(?string $input, int $maxLength = null): string
    {
        if ($input === null) {
            return '';
        }

        $maxLength = $maxLength ?? self::MAX_STRING_LENGTH;
        
        // Remove potentially dangerous characters
        $sanitized = preg_replace('/[<>"\']/', '', $input);
        $sanitized = trim($sanitized);
        
        // Limit length
        if (strlen($sanitized) > $maxLength) {
            $sanitized = substr($sanitized, 0, $maxLength);
        }

        return $sanitized;
    }

    /**
     * Sanitize integer input
     */
    private function sanitizeInteger($input, int $min = null, int $max = null): int
    {
        $value = (int) $input;
        
        if ($min !== null && $value < $min) {
            $value = $min;
        }
        
        if ($max !== null && $value > $max) {
            $value = $max;
        }

        return $value;
    }

    /**
     * Sanitize file path
     */
    private function sanitizeFilePath(?string $path): string
    {
        if (!$path) {
            return '';
        }

        // Remove directory traversal attempts
        $sanitized = str_replace(['../', '..\\', '../', '..\\'], '', $path);
        
        // Remove potentially dangerous characters
        $sanitized = preg_replace('/[<>:"|?*]/', '', $sanitized);
        
        return $this->sanitizeString($sanitized, 255);
    }

    /**
     * Sanitize package name
     */
    private function sanitizePackageName(?string $packageName): string
    {
        if (!$packageName) {
            return 'com.example.game';
        }

        // Ensure valid package name format
        $sanitized = preg_replace('/[^a-zA-Z0-9._]/', '', $packageName);
        
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9]*(\.[a-zA-Z][a-zA-Z0-9]*)*$/', $sanitized)) {
            return 'com.example.game';
        }

        return $sanitized;
    }

    // Additional helper methods for complex structures
    private function validateAndSanitizeVariables(array $variables): array
    {
        // Implementation for variables validation
        return array_slice($variables, 0, self::MAX_ARRAY_SIZE);
    }

    private function validateAndSanitizeObjectGroups(array $groups): array
    {
        // Implementation for object groups validation
        return array_slice($groups, 0, self::MAX_ARRAY_SIZE);
    }

    private function validateAndSanitizeBehaviors(array $behaviors): array
    {
        // Implementation for behaviors validation
        return array_slice($behaviors, 0, 50); // Reasonable limit for behaviors
    }

    private function validateAndSanitizeAnimations(array $animations): array
    {
        // Implementation for animations validation
        return array_slice($animations, 0, 100); // Reasonable limit for animations
    }

    private function validateAndSanitizeInstances(array $instances): array
    {
        // Implementation for instances validation
        return array_slice($instances, 0, self::MAX_ARRAY_SIZE);
    }

    private function validateAndSanitizeEvents(array $events): array
    {
        // Implementation for events validation
        return array_slice($events, 0, self::MAX_ARRAY_SIZE);
    }

    private function validateAndSanitizeLayers(array $layers): array
    {
        // Implementation for layers validation
        return array_slice($layers, 0, 20); // Reasonable limit for layers
    }

    private function validateAndSanitizeExternalEvents(array $events): array
    {
        // Implementation for external events validation
        return array_slice($events, 0, 100);
    }

    private function validateAndSanitizeExtensions(array $extensions): array
    {
        // Implementation for extensions validation
        return array_slice($extensions, 0, 50);
    }

    private function validateAndSanitizeExternalLayouts(array $layouts): array
    {
        // Implementation for external layouts validation
        return array_slice($layouts, 0, 50);
    }

    private function validateAndSanitizeExternalSourceFiles(array $files): array
    {
        // Implementation for external source files validation
        return array_slice($files, 0, 100);
    }

    private function validateAndSanitizeLoadingScreen(array $loadingScreen): array
    {
        return [
            'showGDevelopSplash' => (bool)($loadingScreen['showGDevelopSplash'] ?? true)
        ];
    }

    private function validateAndSanitizeWatermark(array $watermark): array
    {
        return [
            'placement' => $this->sanitizeString($watermark['placement'] ?? 'bottom-left'),
            'showWatermark' => (bool)($watermark['showWatermark'] ?? true)
        ];
    }
}