<?php

namespace Tests\Unit;

use App\Services\GDevelopJsonValidator;
use InvalidArgumentException;
use Tests\TestCase;

class GDevelopJsonValidatorTest extends TestCase
{
    private GDevelopJsonValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new GDevelopJsonValidator();
    }

    public function test_validate_returns_empty_array_for_valid_game_json()
    {
        $validGameJson = $this->getValidGameJson();
        
        $errors = $this->validator->validate($validGameJson);
        
        $this->assertEmpty($errors);
    }

    public function test_validate_detects_missing_required_root_fields()
    {
        $invalidGameJson = [
            'properties' => [],
            // Missing resources, objects, layouts
        ];
        
        $errors = $this->validator->validate($invalidGameJson);
        
        $this->assertContains('Missing required field: resources', $errors);
        $this->assertContains('Missing required field: objects', $errors);
        $this->assertContains('Missing required field: layouts', $errors);
    }

    public function test_validate_detects_invalid_array_fields()
    {
        $invalidGameJson = $this->getValidGameJson();
        $invalidGameJson['objects'] = 'not an array';
        $invalidGameJson['layouts'] = 'not an array';
        
        $errors = $this->validator->validate($invalidGameJson);
        
        $this->assertContains("Field 'objects' must be an array", $errors);
        $this->assertContains("Field 'layouts' must be an array", $errors);
    }

    public function test_validate_detects_missing_required_properties()
    {
        $invalidGameJson = $this->getValidGameJson();
        unset($invalidGameJson['properties']['name']);
        unset($invalidGameJson['properties']['version']);
        unset($invalidGameJson['properties']['projectUuid']);
        
        $errors = $this->validator->validate($invalidGameJson);
        
        $this->assertContains('Missing or empty required property: name', $errors);
        $this->assertContains('Missing or empty required property: version', $errors);
        $this->assertContains('Missing or empty required property: projectUuid', $errors);
    }

    public function test_validate_detects_invalid_orientation()
    {
        $invalidGameJson = $this->getValidGameJson();
        $invalidGameJson['properties']['orientation'] = 'invalid';
        
        $errors = $this->validator->validate($invalidGameJson);
        
        $this->assertContains('Invalid orientation. Must be one of: default, landscape, portrait', $errors);
    }

    public function test_validate_detects_invalid_uuid_format()
    {
        $invalidGameJson = $this->getValidGameJson();
        $invalidGameJson['properties']['projectUuid'] = 'invalid-uuid';
        
        $errors = $this->validator->validate($invalidGameJson);
        
        $this->assertContains('Invalid projectUuid format', $errors);
    }

    public function test_validate_detects_duplicate_object_names()
    {
        $invalidGameJson = $this->getValidGameJson();
        $invalidGameJson['objects'] = [
            ['name' => 'Player', 'type' => 'Sprite'],
            ['name' => 'Player', 'type' => 'Sprite'], // Duplicate name
        ];
        
        $errors = $this->validator->validate($invalidGameJson);
        
        $this->assertContains('Duplicate object name: Player', $errors);
    }

    public function test_validate_detects_missing_object_fields()
    {
        $invalidGameJson = $this->getValidGameJson();
        $invalidGameJson['objects'] = [
            ['type' => 'Sprite'], // Missing name
            ['name' => 'Player'], // Missing type
        ];
        
        $errors = $this->validator->validate($invalidGameJson);
        
        $this->assertContains("Object at index 0: Missing required field 'name'", $errors);
        $this->assertContains("Object at index 1: Missing required field 'type'", $errors);
    }

    public function test_validate_detects_invalid_object_type()
    {
        $invalidGameJson = $this->getValidGameJson();
        $invalidGameJson['objects'] = [
            ['name' => 'Player', 'type' => 'InvalidType'],
        ];
        
        $errors = $this->validator->validate($invalidGameJson);
        
        $this->assertContains("Object at index 0: Unknown object type 'InvalidType'", $errors);
    }

    public function test_validate_allows_extension_object_types()
    {
        $validGameJson = $this->getValidGameJson();
        $validGameJson['objects'] = [
            ['name' => 'Player', 'type' => 'CustomExtension::CustomObject'],
        ];
        
        $errors = $this->validator->validate($validGameJson);
        
        // Should not contain object type error for extension types
        $typeErrors = array_filter($errors, function($error) {
            return str_contains($error, 'Unknown object type');
        });
        $this->assertEmpty($typeErrors);
    }

    public function test_validate_detects_empty_layouts()
    {
        $invalidGameJson = $this->getValidGameJson();
        $invalidGameJson['layouts'] = [];
        
        $errors = $this->validator->validate($invalidGameJson);
        
        $this->assertContains('At least one layout is required', $errors);
    }

    public function test_validate_detects_duplicate_layout_names()
    {
        $invalidGameJson = $this->getValidGameJson();
        $invalidGameJson['layouts'] = [
            ['name' => 'Scene', 'layers' => [['name' => 'Layer1', 'visibility' => true]]],
            ['name' => 'Scene', 'layers' => [['name' => 'Layer2', 'visibility' => true]]], // Duplicate name
        ];
        
        $errors = $this->validator->validate($invalidGameJson);
        
        $this->assertContains('Duplicate layout name: Scene', $errors);
    }

    public function test_validate_detects_missing_layout_fields()
    {
        $invalidGameJson = $this->getValidGameJson();
        $invalidGameJson['layouts'] = [
            ['layers' => []], // Missing name
            ['name' => 'Scene'], // Missing layers
        ];
        
        $errors = $this->validator->validate($invalidGameJson);
        
        $this->assertContains("Layout at index 0: Missing required field 'name'", $errors);
        $this->assertContains("Layout at index 1: Missing required field 'layers'", $errors);
    }

    public function test_validate_detects_empty_layers_in_layout()
    {
        $invalidGameJson = $this->getValidGameJson();
        $invalidGameJson['layouts'] = [
            ['name' => 'Scene', 'layers' => []], // Empty layers
        ];
        
        $errors = $this->validator->validate($invalidGameJson);
        
        $this->assertContains('Layout at index 0: At least one layer is required', $errors);
    }

    public function test_validate_detects_invalid_variable_types()
    {
        $invalidGameJson = $this->getValidGameJson();
        $invalidGameJson['variables'] = [
            ['name' => 'Score', 'type' => 'invalid_type', 'value' => 0],
        ];
        
        $errors = $this->validator->validate($invalidGameJson);
        
        $this->assertContains("Global variable 0: Invalid variable type 'invalid_type'. Must be one of: number, string, boolean, structure, array", $errors);
    }

    public function test_validate_detects_mismatched_variable_value_types()
    {
        $invalidGameJson = $this->getValidGameJson();
        $invalidGameJson['variables'] = [
            ['name' => 'Score', 'type' => 'number', 'value' => 'not a number'],
            ['name' => 'Name', 'type' => 'string', 'value' => 123],
            ['name' => 'Active', 'type' => 'boolean', 'value' => 'not a boolean'],
        ];
        
        $errors = $this->validator->validate($invalidGameJson);
        
        $this->assertContains("Global variable 0: Variable value must be numeric for type 'number'", $errors);
        $this->assertContains("Global variable 1: Variable value must be string for type 'string'", $errors);
        $this->assertContains("Global variable 2: Variable value must be boolean for type 'boolean'", $errors);
    }

    public function test_validate_detects_duplicate_variable_names()
    {
        $invalidGameJson = $this->getValidGameJson();
        $invalidGameJson['variables'] = [
            ['name' => 'Score', 'type' => 'number', 'value' => 0],
            ['name' => 'Score', 'type' => 'number', 'value' => 100], // Duplicate name
        ];
        
        $errors = $this->validator->validate($invalidGameJson);
        
        $this->assertContains('Duplicate variable name: Score', $errors);
    }

    public function test_is_valid_returns_true_for_valid_json()
    {
        $validGameJson = $this->getValidGameJson();
        
        $this->assertTrue($this->validator->isValid($validGameJson));
    }

    public function test_is_valid_returns_false_for_invalid_json()
    {
        $invalidGameJson = ['invalid' => 'structure'];
        
        $this->assertFalse($this->validator->isValid($invalidGameJson));
    }

    public function test_validate_or_throw_passes_for_valid_json()
    {
        $validGameJson = $this->getValidGameJson();
        
        // Should not throw exception
        $this->validator->validateOrThrow($validGameJson);
        
        // If we reach here, the test passes
        $this->assertTrue(true);
    }

    public function test_validate_or_throw_throws_exception_for_invalid_json()
    {
        $invalidGameJson = ['invalid' => 'structure'];
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid GDevelop game JSON:');
        
        $this->validator->validateOrThrow($invalidGameJson);
    }

    public function test_get_validation_rules_returns_documentation()
    {
        $rules = $this->validator->getValidationRules();
        
        $this->assertIsArray($rules);
        $this->assertArrayHasKey('root', $rules);
        $this->assertArrayHasKey('properties', $rules);
        $this->assertArrayHasKey('objects', $rules);
        $this->assertArrayHasKey('layouts', $rules);
        $this->assertArrayHasKey('variables', $rules);
    }

    /**
     * Helper method to get a valid game JSON structure for testing
     */
    private function getValidGameJson(): array
    {
        return [
            'properties' => [
                'name' => 'Test Game',
                'description' => 'A test game',
                'author' => 'Test Author',
                'version' => '1.0.0',
                'orientation' => 'default',
                'sizeOnStartupMode' => 'adaptWidth',
                'adaptGameResolutionAtRuntime' => true,
                'antialiasingMode' => 'MSAA',
                'pixelsRounding' => false,
                'projectUuid' => '12345678-1234-1234-1234-123456789012'
            ],
            'resources' => [
                'resources' => []
            ],
            'objects' => [
                [
                    'name' => 'Player',
                    'type' => 'Sprite',
                    'variables' => [
                        ['name' => 'Health', 'type' => 'number', 'value' => 100]
                    ],
                    'animations' => [
                        [
                            'name' => 'Idle',
                            'directions' => [
                                ['sprites' => []]
                            ]
                        ]
                    ]
                ]
            ],
            'objectsGroups' => [],
            'variables' => [
                ['name' => 'Score', 'type' => 'number', 'value' => 0],
                ['name' => 'PlayerName', 'type' => 'string', 'value' => 'Player'],
                ['name' => 'GameActive', 'type' => 'boolean', 'value' => true]
            ],
            'layouts' => [
                [
                    'name' => 'MainScene',
                    'layers' => [
                        [
                            'name' => 'Background',
                            'visibility' => true
                        ]
                    ]
                ]
            ],
            'externalEvents' => [],
            'eventsFunctionsExtensions' => [],
            'externalLayouts' => [],
            'externalSourceFiles' => []
        ];
    }
}