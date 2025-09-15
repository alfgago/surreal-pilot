<?php

namespace App\Services;

use InvalidArgumentException;

class GDevelopJsonValidator
{
    /**
     * Validate a GDevelop game JSON structure
     */
    public function validate(array $gameJson): array
    {
        $errors = [];

        // Validate root structure
        $errors = array_merge($errors, $this->validateRootStructure($gameJson));

        // Validate properties
        if (isset($gameJson['properties'])) {
            $errors = array_merge($errors, $this->validateProperties($gameJson['properties']));
        }

        // Validate objects
        if (isset($gameJson['objects'])) {
            if (is_array($gameJson['objects'])) {
                $errors = array_merge($errors, $this->validateObjects($gameJson['objects']));
            }
        }

        // Validate layouts
        if (isset($gameJson['layouts'])) {
            if (is_array($gameJson['layouts'])) {
                $errors = array_merge($errors, $this->validateLayouts($gameJson['layouts']));
            }
        }

        // Validate variables
        if (isset($gameJson['variables'])) {
            if (is_array($gameJson['variables'])) {
                $errors = array_merge($errors, $this->validateVariables($gameJson['variables']));
            }
        }

        // Validate resources
        if (isset($gameJson['resources'])) {
            $errors = array_merge($errors, $this->validateResources($gameJson['resources']));
        }

        return $errors;
    }

    /**
     * Check if game JSON is valid (no validation errors)
     */
    public function isValid(array $gameJson): bool
    {
        return empty($this->validate($gameJson));
    }

    /**
     * Validate and throw exception if invalid
     */
    public function validateOrThrow(array $gameJson): void
    {
        $errors = $this->validate($gameJson);
        
        if (!empty($errors)) {
            throw new InvalidArgumentException('Invalid GDevelop game JSON: ' . implode(', ', $errors));
        }
    }

    /**
     * Validate root structure of the game JSON
     */
    private function validateRootStructure(array $gameJson): array
    {
        $errors = [];
        $requiredFields = ['properties', 'resources', 'objects', 'layouts'];

        foreach ($requiredFields as $field) {
            if (!isset($gameJson[$field])) {
                $errors[] = "Missing required field: {$field}";
            }
        }

        // Check for expected array fields
        $arrayFields = ['objects', 'objectsGroups', 'variables', 'layouts', 'externalEvents', 'eventsFunctionsExtensions', 'externalLayouts', 'externalSourceFiles'];
        
        foreach ($arrayFields as $field) {
            if (isset($gameJson[$field]) && !is_array($gameJson[$field])) {
                $errors[] = "Field '{$field}' must be an array";
            }
        }

        return $errors;
    }

    /**
     * Validate game properties
     */
    private function validateProperties(array $properties): array
    {
        $errors = [];
        $requiredFields = ['name', 'version', 'projectUuid'];

        foreach ($requiredFields as $field) {
            if (!isset($properties[$field]) || empty($properties[$field])) {
                $errors[] = "Missing or empty required property: {$field}";
            }
        }

        // Validate specific property values
        if (isset($properties['orientation'])) {
            $validOrientations = ['default', 'landscape', 'portrait'];
            if (!in_array($properties['orientation'], $validOrientations)) {
                $errors[] = "Invalid orientation. Must be one of: " . implode(', ', $validOrientations);
            }
        }

        if (isset($properties['sizeOnStartupMode'])) {
            $validModes = ['adaptWidth', 'adaptHeight', 'noChanges'];
            if (!in_array($properties['sizeOnStartupMode'], $validModes)) {
                $errors[] = "Invalid sizeOnStartupMode. Must be one of: " . implode(', ', $validModes);
            }
        }

        if (isset($properties['antialiasingMode'])) {
            $validModes = ['MSAA', 'none'];
            if (!in_array($properties['antialiasingMode'], $validModes)) {
                $errors[] = "Invalid antialiasingMode. Must be one of: " . implode(', ', $validModes);
            }
        }

        // Validate UUID format
        if (isset($properties['projectUuid'])) {
            $uuidPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
            if (!preg_match($uuidPattern, $properties['projectUuid'])) {
                $errors[] = "Invalid projectUuid format";
            }
        }

        return $errors;
    }

    /**
     * Validate game objects
     */
    private function validateObjects(array $objects): array
    {
        $errors = [];
        $objectNames = [];

        foreach ($objects as $index => $object) {
            $objectErrors = $this->validateObject($object, $index);
            $errors = array_merge($errors, $objectErrors);

            // Check for duplicate object names
            if (isset($object['name'])) {
                if (in_array($object['name'], $objectNames)) {
                    $errors[] = "Duplicate object name: {$object['name']}";
                } else {
                    $objectNames[] = $object['name'];
                }
            }
        }

        return $errors;
    }

    /**
     * Validate a single game object
     */
    private function validateObject(array $object, int $index): array
    {
        $errors = [];
        $requiredFields = ['name', 'type'];

        foreach ($requiredFields as $field) {
            if (!isset($object[$field]) || empty($object[$field])) {
                $errors[] = "Object at index {$index}: Missing required field '{$field}'";
            }
        }

        // Validate object type
        if (isset($object['type'])) {
            $validTypes = ['Sprite', 'TextObject::Text', 'TiledSpriteObject::TiledSprite', 'PanelSpriteObject::PanelSprite'];
            if (!in_array($object['type'], $validTypes) && !str_contains($object['type'], '::')) {
                // Allow extension types (containing ::)
                $errors[] = "Object at index {$index}: Unknown object type '{$object['type']}'";
            }
        }

        // Validate animations for sprite objects
        if (isset($object['type']) && $object['type'] === 'Sprite' && isset($object['animations'])) {
            if (!is_array($object['animations'])) {
                $errors[] = "Object at index {$index}: animations must be an array";
            } else {
                foreach ($object['animations'] as $animIndex => $animation) {
                    if (!isset($animation['name'])) {
                        $errors[] = "Object at index {$index}, animation {$animIndex}: Missing animation name";
                    }
                    if (!isset($animation['directions']) || !is_array($animation['directions'])) {
                        $errors[] = "Object at index {$index}, animation {$animIndex}: Missing or invalid directions array";
                    }
                }
            }
        }

        // Validate variables
        if (isset($object['variables']) && is_array($object['variables'])) {
            foreach ($object['variables'] as $varIndex => $variable) {
                $varErrors = $this->validateVariable($variable, "Object {$index}, variable {$varIndex}");
                $errors = array_merge($errors, $varErrors);
            }
        }

        return $errors;
    }

    /**
     * Validate game layouts (scenes)
     */
    private function validateLayouts(array $layouts): array
    {
        $errors = [];
        $layoutNames = [];

        if (empty($layouts)) {
            $errors[] = "At least one layout is required";
            return $errors;
        }

        foreach ($layouts as $index => $layout) {
            $layoutErrors = $this->validateLayout($layout, $index);
            $errors = array_merge($errors, $layoutErrors);

            // Check for duplicate layout names
            if (isset($layout['name'])) {
                if (in_array($layout['name'], $layoutNames)) {
                    $errors[] = "Duplicate layout name: {$layout['name']}";
                } else {
                    $layoutNames[] = $layout['name'];
                }
            }
        }

        return $errors;
    }

    /**
     * Validate a single layout
     */
    private function validateLayout(array $layout, int $index): array
    {
        $errors = [];
        $requiredFields = ['name', 'layers'];

        foreach ($requiredFields as $field) {
            if (!isset($layout[$field])) {
                $errors[] = "Layout at index {$index}: Missing required field '{$field}'";
            }
        }

        // Validate layers
        if (isset($layout['layers']) && is_array($layout['layers'])) {
            if (empty($layout['layers'])) {
                $errors[] = "Layout at index {$index}: At least one layer is required";
            } else {
                foreach ($layout['layers'] as $layerIndex => $layer) {
                    if (!isset($layer['name'])) {
                        $errors[] = "Layout at index {$index}, layer {$layerIndex}: Missing layer name";
                    }
                    if (!isset($layer['visibility'])) {
                        $errors[] = "Layout at index {$index}, layer {$layerIndex}: Missing visibility property";
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Validate game variables
     */
    private function validateVariables(array $variables): array
    {
        $errors = [];
        $variableNames = [];

        foreach ($variables as $index => $variable) {
            $varErrors = $this->validateVariable($variable, "Global variable {$index}");
            $errors = array_merge($errors, $varErrors);

            // Check for duplicate variable names
            if (isset($variable['name'])) {
                if (in_array($variable['name'], $variableNames)) {
                    $errors[] = "Duplicate variable name: {$variable['name']}";
                } else {
                    $variableNames[] = $variable['name'];
                }
            }
        }

        return $errors;
    }

    /**
     * Validate a single variable
     */
    private function validateVariable(array $variable, string $context): array
    {
        $errors = [];
        $requiredFields = ['name', 'type'];

        foreach ($requiredFields as $field) {
            if (!isset($variable[$field]) || empty($variable[$field])) {
                $errors[] = "{$context}: Missing required field '{$field}'";
            }
        }

        // Validate variable type
        if (isset($variable['type'])) {
            $validTypes = ['number', 'string', 'boolean', 'structure', 'array'];
            if (!in_array($variable['type'], $validTypes)) {
                $errors[] = "{$context}: Invalid variable type '{$variable['type']}'. Must be one of: " . implode(', ', $validTypes);
            }
        }

        // Validate variable value based on type
        if (isset($variable['type']) && isset($variable['value'])) {
            switch ($variable['type']) {
                case 'number':
                    if (!is_numeric($variable['value'])) {
                        $errors[] = "{$context}: Variable value must be numeric for type 'number'";
                    }
                    break;
                case 'string':
                    if (!is_string($variable['value'])) {
                        $errors[] = "{$context}: Variable value must be string for type 'string'";
                    }
                    break;
                case 'boolean':
                    if (!is_bool($variable['value'])) {
                        $errors[] = "{$context}: Variable value must be boolean for type 'boolean'";
                    }
                    break;
            }
        }

        return $errors;
    }

    /**
     * Validate game resources
     */
    private function validateResources(array $resources): array
    {
        $errors = [];

        if (!isset($resources['resources']) || !is_array($resources['resources'])) {
            $errors[] = "Resources must contain a 'resources' array";
        }

        return $errors;
    }

    /**
     * Get a list of all validation rules for documentation
     */
    public function getValidationRules(): array
    {
        return [
            'root' => [
                'Required fields: properties, resources, objects, layouts',
                'Array fields: objects, objectsGroups, variables, layouts, externalEvents, eventsFunctionsExtensions, externalLayouts, externalSourceFiles'
            ],
            'properties' => [
                'Required fields: name, version, projectUuid',
                'Valid orientations: default, landscape, portrait',
                'Valid sizeOnStartupMode: adaptWidth, adaptHeight, noChanges',
                'Valid antialiasingMode: MSAA, none',
                'projectUuid must be valid UUID format'
            ],
            'objects' => [
                'Required fields: name, type',
                'Valid types: Sprite, TextObject::Text, TiledSpriteObject::TiledSprite, PanelSpriteObject::PanelSprite, or extension types',
                'Sprite objects must have animations array',
                'Object names must be unique'
            ],
            'layouts' => [
                'Required fields: name, layers',
                'At least one layout required',
                'At least one layer per layout required',
                'Layout names must be unique'
            ],
            'variables' => [
                'Required fields: name, type',
                'Valid types: number, string, boolean, structure, array',
                'Variable names must be unique',
                'Variable values must match declared type'
            ]
        ];
    }
}