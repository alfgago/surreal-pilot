<?php

namespace Tests\Unit;

use App\Services\GDevelopTemplateService;
use App\Services\GDevelopJsonValidator;
use Tests\TestCase;

class GDevelopTemplateValidationIntegrationTest extends TestCase
{
    private GDevelopTemplateService $templateService;
    private GDevelopJsonValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->templateService = new GDevelopTemplateService();
        $this->validator = new GDevelopJsonValidator();
    }

    public function test_all_templates_pass_validation()
    {
        $templates = $this->templateService->getAvailableTemplates();
        
        foreach (array_keys($templates) as $templateName) {
            $template = $this->templateService->loadTemplate($templateName);
            $errors = $this->validator->validate($template);
            
            $this->assertEmpty(
                $errors, 
                "Template '{$templateName}' failed validation with errors: " . implode(', ', $errors)
            );
        }
    }

    public function test_created_games_from_templates_pass_validation()
    {
        $templates = $this->templateService->getAvailableTemplates();
        
        foreach (array_keys($templates) as $templateName) {
            $game = $this->templateService->createGameFromTemplate($templateName);
            $errors = $this->validator->validate($game);
            
            $this->assertEmpty(
                $errors, 
                "Game created from template '{$templateName}' failed validation with errors: " . implode(', ', $errors)
            );
        }
    }

    public function test_games_with_custom_properties_pass_validation()
    {
        $customProperties = [
            'name' => 'Custom Test Game',
            'description' => 'A custom test game',
            'author' => 'Test Author',
            'orientation' => 'landscape',
            'variables' => [
                [
                    'name' => 'TestVar',
                    'type' => 'number',
                    'value' => 100
                ],
                [
                    'name' => 'TestString',
                    'type' => 'string',
                    'value' => 'test'
                ],
                [
                    'name' => 'TestBool',
                    'type' => 'boolean',
                    'value' => true
                ]
            ]
        ];

        $templates = $this->templateService->getAvailableTemplates();
        
        foreach (array_keys($templates) as $templateName) {
            $game = $this->templateService->createGameFromTemplate($templateName, $customProperties);
            $errors = $this->validator->validate($game);
            
            $this->assertEmpty(
                $errors, 
                "Game with custom properties from template '{$templateName}' failed validation with errors: " . implode(', ', $errors)
            );
        }
    }

    public function test_template_by_type_games_pass_validation()
    {
        $gameTypes = [
            'platformer',
            'tower defense',
            'puzzle',
            'arcade',
            'basic',
            'jump game',
            'td game',
            'match-3',
            'shooter',
            'unknown type' // Should fallback to basic
        ];

        foreach ($gameTypes as $gameType) {
            $game = $this->templateService->getTemplateByType($gameType);
            $errors = $this->validator->validate($game);
            
            $this->assertEmpty(
                $errors, 
                "Game created for type '{$gameType}' failed validation with errors: " . implode(', ', $errors)
            );
        }
    }

    public function test_validator_is_valid_method_works_with_templates()
    {
        $templates = $this->templateService->getAvailableTemplates();
        
        foreach (array_keys($templates) as $templateName) {
            $template = $this->templateService->loadTemplate($templateName);
            
            $this->assertTrue(
                $this->validator->isValid($template),
                "Template '{$templateName}' should be valid according to isValid() method"
            );
        }
    }

    public function test_validator_validate_or_throw_works_with_templates()
    {
        $templates = $this->templateService->getAvailableTemplates();
        
        foreach (array_keys($templates) as $templateName) {
            $template = $this->templateService->loadTemplate($templateName);
            
            // Should not throw exception
            $this->validator->validateOrThrow($template);
            
            // If we reach here for each template, the test passes
            $this->assertTrue(true);
        }
    }

    public function test_template_metadata_matches_actual_template_content()
    {
        $templates = $this->templateService->getAvailableTemplates();
        
        foreach (array_keys($templates) as $templateName) {
            $template = $this->templateService->loadTemplate($templateName);
            $metadata = $this->templateService->getTemplateMetadata($templateName);
            
            // Verify metadata matches actual template content
            $this->assertEquals($template['properties']['name'], $metadata['name']);
            $this->assertEquals($template['properties']['description'] ?? '', $metadata['description']);
            $this->assertEquals($template['properties']['author'] ?? 'Unknown', $metadata['author']);
            $this->assertEquals($template['properties']['version'] ?? '1.0.0', $metadata['version']);
            $this->assertEquals($template['properties']['orientation'] ?? 'default', $metadata['orientation']);
            $this->assertEquals(count($template['objects'] ?? []), $metadata['objectCount']);
            $this->assertEquals(count($template['layouts'] ?? []), $metadata['layoutCount']);
            $this->assertEquals(count($template['variables'] ?? []), $metadata['variableCount']);
        }
    }
}