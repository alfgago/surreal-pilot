<?php

namespace Tests\Unit;

use App\Services\GDevelopTemplateService;
use InvalidArgumentException;
use Tests\TestCase;
use Illuminate\Support\Facades\File;

class GDevelopTemplateServiceTest extends TestCase
{
    private GDevelopTemplateService $templateService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->templateService = new GDevelopTemplateService();
    }

    public function test_get_available_templates_returns_expected_templates()
    {
        $templates = $this->templateService->getAvailableTemplates();
        
        $this->assertIsArray($templates);
        $this->assertArrayHasKey('basic', $templates);
        $this->assertArrayHasKey('platformer', $templates);
        $this->assertArrayHasKey('tower-defense', $templates);
        $this->assertArrayHasKey('puzzle', $templates);
        $this->assertArrayHasKey('arcade', $templates);
    }

    public function test_load_template_returns_valid_json_structure()
    {
        $template = $this->templateService->loadTemplate('basic');
        
        $this->assertIsArray($template);
        $this->assertArrayHasKey('properties', $template);
        $this->assertArrayHasKey('resources', $template);
        $this->assertArrayHasKey('objects', $template);
        $this->assertArrayHasKey('layouts', $template);
        
        // Verify properties structure
        $this->assertArrayHasKey('name', $template['properties']);
        $this->assertArrayHasKey('version', $template['properties']);
        $this->assertArrayHasKey('projectUuid', $template['properties']);
    }

    public function test_load_template_throws_exception_for_invalid_template()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Template 'nonexistent' not found");
        
        $this->templateService->loadTemplate('nonexistent');
    }

    public function test_get_template_by_type_maps_correctly()
    {
        // Test platformer mappings
        $template = $this->templateService->getTemplateByType('platformer');
        $this->assertEquals('Platformer Game', $template['properties']['name']);
        
        $template = $this->templateService->getTemplateByType('jump game');
        $this->assertEquals('Platformer Game', $template['properties']['name']);
        
        // Test tower defense mappings
        $template = $this->templateService->getTemplateByType('tower defense');
        $this->assertEquals('Tower Defense Game', $template['properties']['name']);
        
        $template = $this->templateService->getTemplateByType('td game');
        $this->assertEquals('Tower Defense Game', $template['properties']['name']);
        
        // Test puzzle mappings
        $template = $this->templateService->getTemplateByType('puzzle');
        $this->assertEquals('Puzzle Game', $template['properties']['name']);
        
        $template = $this->templateService->getTemplateByType('match-3');
        $this->assertEquals('Puzzle Game', $template['properties']['name']);
        
        // Test arcade mappings
        $template = $this->templateService->getTemplateByType('arcade');
        $this->assertEquals('Arcade Game', $template['properties']['name']);
        
        $template = $this->templateService->getTemplateByType('shooter');
        $this->assertEquals('Arcade Game', $template['properties']['name']);
        
        // Test default fallback
        $template = $this->templateService->getTemplateByType('unknown game type');
        $this->assertEquals('Basic Game', $template['properties']['name']);
    }

    public function test_create_game_from_template_generates_unique_uuid()
    {
        $game1 = $this->templateService->createGameFromTemplate('basic');
        $game2 = $this->templateService->createGameFromTemplate('basic');
        
        $this->assertNotEquals(
            $game1['properties']['projectUuid'],
            $game2['properties']['projectUuid']
        );
        
        // Verify UUID format
        $uuidPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
        $this->assertMatchesRegularExpression($uuidPattern, $game1['properties']['projectUuid']);
    }

    public function test_create_game_from_template_applies_custom_properties()
    {
        $customProperties = [
            'name' => 'My Custom Game',
            'description' => 'A custom game description',
            'author' => 'Test Author',
            'orientation' => 'landscape',
            'variables' => [
                [
                    'name' => 'CustomVar',
                    'type' => 'number',
                    'value' => 42
                ]
            ]
        ];
        
        $game = $this->templateService->createGameFromTemplate('basic', $customProperties);
        
        $this->assertEquals('My Custom Game', $game['properties']['name']);
        $this->assertEquals('A custom game description', $game['properties']['description']);
        $this->assertEquals('Test Author', $game['properties']['author']);
        $this->assertEquals('landscape', $game['properties']['orientation']);
        
        // Check if custom variable was added
        $customVarFound = false;
        foreach ($game['variables'] as $variable) {
            if ($variable['name'] === 'CustomVar') {
                $customVarFound = true;
                $this->assertEquals('number', $variable['type']);
                $this->assertEquals(42, $variable['value']);
                break;
            }
        }
        $this->assertTrue($customVarFound, 'Custom variable not found in game');
    }

    public function test_template_exists_returns_correct_boolean()
    {
        $this->assertTrue($this->templateService->templateExists('basic'));
        $this->assertTrue($this->templateService->templateExists('platformer'));
        $this->assertFalse($this->templateService->templateExists('nonexistent'));
    }

    public function test_get_template_metadata_returns_expected_structure()
    {
        $metadata = $this->templateService->getTemplateMetadata('platformer');
        
        $this->assertIsArray($metadata);
        $this->assertArrayHasKey('name', $metadata);
        $this->assertArrayHasKey('description', $metadata);
        $this->assertArrayHasKey('author', $metadata);
        $this->assertArrayHasKey('version', $metadata);
        $this->assertArrayHasKey('orientation', $metadata);
        $this->assertArrayHasKey('objectCount', $metadata);
        $this->assertArrayHasKey('layoutCount', $metadata);
        $this->assertArrayHasKey('variableCount', $metadata);
        
        $this->assertEquals('Platformer Game', $metadata['name']);
        $this->assertIsInt($metadata['objectCount']);
        $this->assertIsInt($metadata['layoutCount']);
        $this->assertIsInt($metadata['variableCount']);
    }

    public function test_get_template_metadata_throws_exception_for_invalid_template()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Template 'nonexistent' not found");
        
        $this->templateService->getTemplateMetadata('nonexistent');
    }

    public function test_all_templates_have_valid_json_structure()
    {
        $templates = $this->templateService->getAvailableTemplates();
        
        foreach (array_keys($templates) as $templateName) {
            $template = $this->templateService->loadTemplate($templateName);
            
            // Verify basic structure
            $this->assertArrayHasKey('properties', $template, "Template {$templateName} missing properties");
            $this->assertArrayHasKey('resources', $template, "Template {$templateName} missing resources");
            $this->assertArrayHasKey('objects', $template, "Template {$templateName} missing objects");
            $this->assertArrayHasKey('layouts', $template, "Template {$templateName} missing layouts");
            
            // Verify at least one layout exists
            $this->assertNotEmpty($template['layouts'], "Template {$templateName} has no layouts");
            
            // Verify properties have required fields
            $this->assertArrayHasKey('name', $template['properties'], "Template {$templateName} missing name property");
            $this->assertArrayHasKey('version', $template['properties'], "Template {$templateName} missing version property");
            $this->assertArrayHasKey('projectUuid', $template['properties'], "Template {$templateName} missing projectUuid property");
        }
    }
}