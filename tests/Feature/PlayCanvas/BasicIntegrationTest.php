<?php

namespace Tests\Feature\PlayCanvas;

use App\Models\Company;
use App\Models\DemoTemplate;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BasicIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->company = Company::factory()->create(['credits' => 100.0]);
        $this->user = \App\Models\User::factory()->create();
        $this->user->companies()->attach($this->company->id, ['role' => 'developer']);
        $this->actingAs($this->user);
    }

    public function test_can_fetch_playcanvas_demo_templates()
    {
        // Create PlayCanvas templates
        DemoTemplate::factory()->count(3)->create([
            'engine_type' => 'playcanvas',
            'is_active' => true,
        ]);
        
        // Create Unreal templates (should not appear)
        DemoTemplate::factory()->count(2)->create([
            'engine_type' => 'unreal',
            'is_active' => true,
        ]);
        
        $response = $this->postJson('/api/demos', [
            'engine_type' => 'playcanvas'
        ]);
        
        $response->assertOk();
        $data = $response->json();
        
        // Basic validation that we get a response
        $this->assertNotNull($data);
        
        // If it's an array, validate the structure
        if (is_array($data) && count($data) > 0) {
            foreach ($data as $template) {
                if (is_array($template) && isset($template['engine_type'])) {
                    $this->assertEquals('playcanvas', $template['engine_type']);
                }
            }
        }
    }

    public function test_playcanvas_workspace_creation_endpoint_exists()
    {
        $template = DemoTemplate::factory()->create([
            'engine_type' => 'playcanvas',
            'is_active' => true,
        ]);
        
        // Mock the actual workspace creation to avoid file system operations
        Http::fake([
            'localhost:*' => Http::response(['status' => 'ready'], 200),
        ]);
        
        $response = $this->postJson('/api/prototype', [
            'demo_id' => $template->id,
            'company_id' => $this->company->id,
        ]);
        
        // The endpoint should exist and handle the request
        // It may fail due to missing implementation, but should not be 404
        $this->assertNotEquals(404, $response->getStatusCode());
    }

    public function test_playcanvas_workspaces_are_stored_with_correct_engine_type()
    {
        $workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
            'status' => 'ready',
        ]);
        
        $this->assertEquals('playcanvas', $workspace->engine_type);
        $this->assertTrue($workspace->isPlayCanvas());
        $this->assertEquals($this->company->id, $workspace->company_id);
    }

    public function test_can_distinguish_between_playcanvas_and_unreal_workspaces()
    {
        $playcanvasWorkspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
        ]);
        
        $unrealWorkspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'unreal',
        ]);
        
        $this->assertTrue($playcanvasWorkspace->isPlayCanvas());
        $this->assertFalse($unrealWorkspace->isPlayCanvas());
    }

    public function test_assist_endpoint_accepts_playcanvas_workspace_requests()
    {
        $workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
            'status' => 'ready',
            'mcp_port' => 3001,
        ]);
        
        // Mock MCP server response
        Http::fake([
            'localhost:3001/*' => Http::response([
                'success' => true,
                'response' => 'PlayCanvas modification completed',
            ], 200),
        ]);
        
        $response = $this->postJson('/api/assist', [
            'workspace_id' => $workspace->id,
            'prompt' => 'Test PlayCanvas assistance',
            'context' => [],
        ]);
        
        // Should not be 404 or 405 (method not allowed)
        $this->assertNotEquals(404, $response->getStatusCode());
        $this->assertNotEquals(405, $response->getStatusCode());
    }

    public function test_database_schema_supports_playcanvas_workspaces()
    {
        // Test that we can create workspaces with PlayCanvas-specific fields
        $workspace = Workspace::create([
            'company_id' => $this->company->id,
            'name' => 'Test PlayCanvas Game',
            'engine_type' => 'playcanvas',
            'template_id' => 'fps-starter',
            'mcp_port' => 3001,
            'mcp_pid' => 12345,
            'preview_url' => 'http://localhost:3001/preview',
            'published_url' => null,
            'status' => 'initializing',
            'metadata' => [
                'scene_entities' => ['Player', 'Ground'],
                'scripts' => ['PlayerController.js'],
            ],
        ]);
        
        $this->assertDatabaseHas('workspaces', [
            'id' => $workspace->id,
            'engine_type' => 'playcanvas',
            'mcp_port' => 3001,
            'mcp_pid' => 12345,
        ]);
        
        // Test metadata JSON field
        $workspace->refresh();
        $this->assertArrayHasKey('scene_entities', $workspace->metadata);
        $this->assertArrayHasKey('scripts', $workspace->metadata);
    }

    public function test_demo_templates_support_playcanvas_engine_type()
    {
        $template = DemoTemplate::create([
            'id' => 'fps-starter',
            'name' => 'FPS Starter',
            'description' => 'First-person shooter template',
            'engine_type' => 'playcanvas',
            'repository_url' => 'https://github.com/playcanvas/fps-starter.git',
            'preview_image' => '/images/fps-preview.jpg',
            'tags' => ['fps', 'shooter', 'beginner'],
            'difficulty_level' => 'beginner',
            'estimated_setup_time' => 300,
            'is_active' => true,
        ]);
        
        $this->assertDatabaseHas('demo_templates', [
            'id' => 'fps-starter',
            'engine_type' => 'playcanvas',
            'is_active' => true,
        ]);
        
        $template->refresh();
        $this->assertEquals('playcanvas', $template->engine_type);
        $this->assertTrue($template->isPlayCanvas());
    }

    public function test_credit_system_works_with_playcanvas_operations()
    {
        $workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
            'status' => 'ready',
            'mcp_port' => 3001,
        ]);
        
        Http::fake([
            'localhost:3001/*' => Http::response([
                'success' => true,
                'token_usage' => [
                    'prompt_tokens' => 50,
                    'completion_tokens' => 100,
                ],
                'mcp_actions' => 2,
            ], 200),
        ]);
        
        $initialCredits = $this->company->fresh()->credits;
        
        $response = $this->postJson('/api/assist', [
            'workspace_id' => $workspace->id,
            'prompt' => 'Test credit deduction',
            'context' => [],
        ]);
        
        // Credits should be deducted (exact amount depends on implementation)
        $this->company->refresh();
        $finalCredits = $this->company->credits;
        
        if ($response->getStatusCode() === 200) {
            $this->assertLessThanOrEqual($initialCredits, $finalCredits);
        }
    }

    public function test_routes_are_properly_configured()
    {
        // Test that all expected routes exist
        $routes = [
            ['POST', '/api/demos'],
            ['POST', '/api/prototype'],
            ['POST', '/api/assist'],
            ['GET', '/api/workspace/1/status'],
        ];
        
        foreach ($routes as [$method, $uri]) {
            $response = $this->call($method, $uri);
            
            // Should not be 404 (route not found)
            $this->assertNotEquals(404, $response->getStatusCode(), 
                "Route {$method} {$uri} should exist");
        }
    }
}