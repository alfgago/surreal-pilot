<?php

namespace Tests\Feature;

use Tests\TestCase;

class PlayCanvasDocumentationTest extends TestCase
{
    public function test_playcanvas_documentation_exists()
    {
        $this->assertTrue(file_exists(base_path('docs/05-playcanvas.md')));
        $this->assertTrue(file_exists(base_path('docs/playcanvas-chat-commands.md')));
        $this->assertTrue(file_exists(base_path('docs/playcanvas-onboarding-checklist.md')));
    }

    public function test_mobile_tutorials_route_exists()
    {
        $response = $this->get('/mobile/tutorials');
        $response->assertStatus(200);
        $response->assertViewIs('mobile.tutorials');
    }

    public function test_documentation_contains_required_sections()
    {
        $mainDoc = file_get_contents(base_path('docs/05-playcanvas.md'));
        
        // Check for required sections
        $this->assertStringContainsString('Quick Start: From Idea to Published Game in 5 Minutes', $mainDoc);
        $this->assertStringContainsString('For Mobile Users', $mainDoc);
        $this->assertStringContainsString('For PC Users', $mainDoc);
        $this->assertStringContainsString('Built-in Templates', $mainDoc);
        $this->assertStringContainsString('Common Chat Commands', $mainDoc);
        $this->assertStringContainsString('Interactive Mobile Tutorials', $mainDoc);
        $this->assertStringContainsString('Advanced: Custom Demo Repositories', $mainDoc);
        $this->assertStringContainsString('Troubleshooting', $mainDoc);
        
        // Check for 5-minute workflow
        $this->assertStringContainsString('5-Minute Workflow', $mainDoc);
        $this->assertStringContainsString('Choose a Template', $mainDoc);
        $this->assertStringContainsString('Create Your Prototype', $mainDoc);
        $this->assertStringContainsString('Modify with Chat', $mainDoc);
        $this->assertStringContainsString('Publish & Share', $mainDoc);
    }

    public function test_chat_commands_documentation_comprehensive()
    {
        $commandsDoc = file_get_contents(base_path('docs/playcanvas-chat-commands.md'));
        
        // Check for major command categories
        $this->assertStringContainsString('Basic Movement & Controls', $commandsDoc);
        $this->assertStringContainsString('Visual Effects & Graphics', $commandsDoc);
        $this->assertStringContainsString('Game Mechanics', $commandsDoc);
        $this->assertStringContainsString('User Interface', $commandsDoc);
        $this->assertStringContainsString('Audio & Sound', $commandsDoc);
        $this->assertStringContainsString('Level Design & Environment', $commandsDoc);
        $this->assertStringContainsString('Advanced Features', $commandsDoc);
        $this->assertStringContainsString('Performance & Optimization', $commandsDoc);
        
        // Check for specific example commands
        $this->assertStringContainsString('Make the player move faster', $commandsDoc);
        $this->assertStringContainsString('Add particle effects when the player jumps', $commandsDoc);
        $this->assertStringContainsString('Add a health bar at the top of the screen', $commandsDoc);
    }

    public function test_onboarding_checklist_has_5_minute_workflow()
    {
        $checklistDoc = file_get_contents(base_path('docs/playcanvas-onboarding-checklist.md'));
        
        // Check for 5-minute workflow sections
        $this->assertStringContainsString('5-Minute Game Publishing Workflow', $checklistDoc);
        $this->assertStringContainsString('Game Creation (1 minute)', $checklistDoc);
        $this->assertStringContainsString('Basic Modification (2-3 minutes)', $checklistDoc);
        $this->assertStringContainsString('Publishing (30 seconds)', $checklistDoc);
        
        // Check for troubleshooting
        $this->assertStringContainsString('Troubleshooting Quick Fixes', $checklistDoc);
        
        // Check for success metrics
        $this->assertStringContainsString('Success Metrics', $checklistDoc);
        $this->assertStringContainsString('Under 5 minutes', $checklistDoc);
        $this->assertStringContainsString('under 1 second on mobile', $checklistDoc);
    }

    public function test_documentation_includes_mobile_optimization()
    {
        $mainDoc = file_get_contents(base_path('docs/05-playcanvas.md'));
        
        // Check for mobile-specific content
        $this->assertStringContainsString('mobile browser', $mainDoc);
        $this->assertStringContainsString('thumb-friendly', $mainDoc);
        $this->assertStringContainsString('portrait and landscape', $mainDoc);
        $this->assertStringContainsString('PWA score', $mainDoc);
        $this->assertStringContainsString('mobile devices', $mainDoc);
    }

    public function test_documentation_includes_template_examples()
    {
        $mainDoc = file_get_contents(base_path('docs/05-playcanvas.md'));
        
        // Check for all template types
        $this->assertStringContainsString('Starter FPS', $mainDoc);
        $this->assertStringContainsString('Third-Person', $mainDoc);
        $this->assertStringContainsString('2D Platformer', $mainDoc);
        $this->assertStringContainsString('Tower Defense', $mainDoc);
        
        // Check for template descriptions
        $this->assertStringContainsString('First-person shooter', $mainDoc);
        $this->assertStringContainsString('Character controller', $mainDoc);
        $this->assertStringContainsString('Side-scrolling', $mainDoc);
        $this->assertStringContainsString('Strategy game', $mainDoc);
    }

    public function test_documentation_includes_success_stories()
    {
        $mainDoc = file_get_contents(base_path('docs/05-playcanvas.md'));
        
        // Check for success stories section
        $this->assertStringContainsString('Success Stories', $mainDoc);
        $this->assertStringContainsString('3 minutes', $mainDoc);
        $this->assertStringContainsString('lunch break', $mainDoc);
        $this->assertStringContainsString('rapid prototyping', $mainDoc);
    }
}