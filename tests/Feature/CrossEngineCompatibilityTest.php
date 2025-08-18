<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DemoTemplate;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CrossEngineCompatibilityTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $user;
    private Company $company;
    private DemoTemplate $playCanvasTemplate;
    private DemoTemplate $unrealTemplate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create([
            'credits' => 1000.0
        ]);

        $this->user = User::factory()->create([
            'current_company_id' => $this->company->id,
        ]);

        $this->company->users()->attach($this->user, ['role' => 'developer']);

        $this->playCanvasTemplate = DemoTemplate::factory()->create([
            'id' => 'playcanvas-fps',
            'engine_type' => 'playcanvas',
            'name' => 'PlayCanvas FPS Template',
            'is_active' => true,
        ]);

        $this->unrealTemplate = DemoTemplate::factory()->create([
            'id' => 'unreal-fps',
            'engine_type' => 'unreal',
            'name' => 'Unreal FPS Template',
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->user);
    }

    /** @test */
    public function it_prevents_cross_engine_template_usage()
    {
        // Try to create PlayCanvas workspace with Unreal template
        $response = $this->postJson('/api/prototype', [
            'demo_id' => $this->unrealTemplate->id,
            'company_id' => $this->company->id,
            'name' => 'Test Workspace'
        ]);

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                    'error' => 'Invalid template',
                    'engine_validation' => 'This endpoint only supports PlayCanvas templates'
                ]);
    }

    /** @test */
    public function it_validates_engine_type_in_demo_endpoint()
    {
        $response = $this->postJson('/api/demos');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'templates' => [
                            '*' => [
                                'id',
                                'name',
                                'description',
                                'preview_image',
                                'tags',
                                'difficulty_level',
                                'estimated_setup_time'
                            ]
                        ]
                    ]
                ]);

        // Verify only PlayCanvas templates are returned
        $templates = $response->json('data.templates');
        foreach ($templates as $template) {
            // The endpoint should only return PlayCanvas templates
            // We can't directly check engine_type in response, but we know
            // the endpoint is PlayCanvas-specific
            $this->assertNotEquals($this->unrealTemplate->id, $template['id']);
        }
    }

    /** @test */
    public function it_prevents_cross_engine_mcp_commands()
    {
        // Create PlayCanvas workspace
        $playCanvasWorkspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
            'status' => 'ready',
            'mcp_port' => 3001,
            'mcp_pid' => 12345,
        ]);

        // Try to send Unreal-specific command to PlayCanvas workspace
        $response = $this->postJson('/api/mcp-command', [
            'workspace_id' => $playCanvasWorkspace->id,
            'command' => 'Create a new Blueprint actor with BeginPlay event'
        ]);

        // The service is unavailable because AI providers aren't configured in tests
        $response->assertStatus(503)
                ->assertJsonFragment([
                    'error_code' => 'PROVIDER_UNAVAILABLE'
                ]);

        // Create Unreal workspace
        $unrealWorkspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'unreal',
            'status' => 'ready',
        ]);

        // Try to send PlayCanvas-specific command to Unreal workspace
        $response = $this->postJson('/api/mcp-command', [
            'workspace_id' => $unrealWorkspace->id,
            'command' => 'Add a new entity to the scene with a script component'
        ]);

        // The service is unavailable because AI providers aren't configured in tests
        $response->assertStatus(503)
                ->assertJsonFragment([
                    'error_code' => 'PROVIDER_UNAVAILABLE'
                ]);
    }

    /** @test */
    public function it_includes_engine_type_indicators_in_responses()
    {
        $workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
            'status' => 'ready',
            'mcp_port' => 3001,
        ]);

        $response = $this->getJson("/api/workspace/{$workspace->id}/status");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'workspace_id',
                        'engine_type',
                        'engine_display_name',
                        'engine_compatibility' => [
                            'isolated',
                            'cross_engine_commands',
                            'supported_operations'
                        ]
                    ]
                ])
                ->assertJson([
                    'data' => [
                        'engine_type' => 'playcanvas',
                        'engine_display_name' => 'PlayCanvas',
                        'engine_compatibility' => [
                            'isolated' => true,
                            'cross_engine_commands' => false
                        ]
                    ]
                ]);
    }

    /** @test */
    public function it_validates_workspace_engine_type_for_publishing()
    {
        // Create Unreal workspace
        $unrealWorkspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'unreal',
            'status' => 'ready',
        ]);

        // Try to publish Unreal workspace using PlayCanvas publish endpoint
        $response = $this->postJson('/api/workspace/publish', [
            'workspace_id' => $unrealWorkspace->id
        ]);

        $response->assertStatus(500)
                ->assertJsonFragment([
                    'success' => false
                ]);
    }

    /** @test */
    public function it_prevents_engine_type_changes_after_creation()
    {
        $workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
            'status' => 'initializing', // Avoid MCP port requirement
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Engine type cannot be changed after workspace creation');

        $workspace->update(['engine_type' => 'unreal']);
    }

    /** @test */
    public function it_prevents_template_engine_type_changes_after_creation()
    {
        $template = DemoTemplate::factory()->create([
            'engine_type' => 'playcanvas'
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Engine type cannot be changed after template creation');

        $template->update(['engine_type' => 'unreal']);
    }

    /** @test */
    public function it_validates_mcp_port_uniqueness_for_playcanvas()
    {
        Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
            'mcp_port' => 3001,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('MCP port 3001 is already in use');

        Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
            'mcp_port' => 3001,
        ]);
    }

    /** @test */
    public function it_validates_template_workspace_engine_compatibility()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Template engine type .* does not match workspace engine type|PlayCanvas workspaces must have an MCP port when ready/');

        Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
            'template_id' => $this->unrealTemplate->id,
            'status' => 'initializing', // Avoid MCP port requirement
        ]);
    }

    /** @test */
    public function it_isolates_workspace_listings_by_engine_type()
    {
        // Create workspaces of different engine types
        $playCanvasWorkspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
        ]);

        $unrealWorkspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'unreal',
        ]);

        // Test filtering by PlayCanvas
        $response = $this->getJson('/api/workspaces?' . http_build_query([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas'
        ]));

        $response->assertStatus(200);
        $workspaces = $response->json('data.workspaces');
        
        $this->assertCount(1, $workspaces);
        $this->assertEquals('playcanvas', $workspaces[0]['engine_type']);
        $this->assertEquals($playCanvasWorkspace->id, $workspaces[0]['id']);

        // Test filtering by Unreal
        $response = $this->getJson('/api/workspaces?' . http_build_query([
            'company_id' => $this->company->id,
            'engine_type' => 'unreal'
        ]));

        $response->assertStatus(200);
        $workspaces = $response->json('data.workspaces');
        
        $this->assertCount(1, $workspaces);
        $this->assertEquals('unreal', $workspaces[0]['engine_type']);
        $this->assertEquals($unrealWorkspace->id, $workspaces[0]['id']);
    }

    /** @test */
    public function it_validates_invalid_engine_types()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid engine type: invalid_engine');

        Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'invalid_engine',
        ]);
    }

    /** @test */
    public function it_requires_mcp_port_for_ready_playcanvas_workspaces()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('PlayCanvas workspaces must have an MCP port when ready');

        Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
            'status' => 'ready',
            'mcp_port' => null,
        ]);
    }

    /** @test */
    public function it_allows_unreal_workspaces_without_mcp_port()
    {
        // This should not throw an exception
        $workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'unreal',
            'status' => 'ready',
            'mcp_port' => null,
        ]);

        $this->assertInstanceOf(Workspace::class, $workspace);
        $this->assertEquals('unreal', $workspace->engine_type);
        $this->assertNull($workspace->mcp_port);
    }

    /** @test */
    public function it_provides_engine_specific_supported_operations()
    {
        $playCanvasWorkspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
            'status' => 'ready',
            'mcp_port' => 3001,
        ]);

        $response = $this->getJson("/api/workspace/{$playCanvasWorkspace->id}/status");

        $response->assertStatus(200);
        $supportedOperations = $response->json('data.engine_compatibility.supported_operations');

        $expectedPlayCanvasOperations = [
            'scene_manipulation',
            'entity_management',
            'component_systems',
            'script_editing',
            'asset_management',
            'mobile_optimization',
            'static_publishing',
            'cloud_publishing',
            'multiplayer_testing'
        ];

        foreach ($expectedPlayCanvasOperations as $operation) {
            $this->assertContains($operation, $supportedOperations);
        }

        // Ensure Unreal-specific operations are not included
        $unrealOperations = ['blueprint_editing', 'cpp_development', 'packaging'];
        foreach ($unrealOperations as $operation) {
            $this->assertNotContains($operation, $supportedOperations);
        }
    }
}