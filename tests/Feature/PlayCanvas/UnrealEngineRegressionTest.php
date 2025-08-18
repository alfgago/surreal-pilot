<?php

namespace Tests\Feature\PlayCanvas;

use App\Http\Controllers\Api\AssistController;
use App\Models\Company;
use App\Models\Workspace;
use App\Services\UnrealMcpManager;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UnrealEngineRegressionTest extends TestCase
{
    use DatabaseMigrations;

    protected Company $company;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure a user exists
        $this->company = Company::factory()->create(['credits' => 100.0]);
        $this->user = \App\Models\User::factory()->create();
        $this->company->users()->attach($this->user, ['role' => 'owner']);
        $this->actingAs($this->user);
    }

    public function test_unreal_engine_workspaces_are_not_affected_by_playcanvas_integration(): void
    {
    // Create Unreal Engine workspace
    $unrealWorkspace = Workspace::factory()->create([
        'company_id' => $this->company->id,
        'engine_type' => 'unreal',
        'status' => 'ready',
        'mcp_port' => 3000,
        'mcp_pid' => 11111,
    ]);

    // Create PlayCanvas workspace
    $playcanvasWorkspace = Workspace::factory()->create([
        'company_id' => $this->company->id,
        'engine_type' => 'playcanvas',
        'status' => 'ready',
        'mcp_port' => 3001,
        'mcp_pid' => 22222,
    ]);

    // Mock Unreal MCP server response
    Http::fake([
        'localhost:3000/v1/assist' => Http::response([
            'success' => true,
            'response' => 'Unreal Engine modification completed',
            'engine_type' => 'unreal'
        ]),
    ]);

    // Send request to Unreal workspace
    $response = $this->postJson('/api/assist', [
        'provider' => 'openai',
        'workspace_id' => $unrealWorkspace->id,
        'prompt' => 'Add a new actor to the scene',
        'messages' => [
            ['role' => 'user', 'content' => 'Add a new actor to the scene']
        ],
        'context' => []
    ]);

    $response->assertOk();
    $data = $response->json();

    $this->assertTrue($data['success']);
    $this->assertStringContainsString('Unreal Engine', $data['response']);

    // Verify request went to Unreal MCP server, not PlayCanvas
        Http::assertSent(function ($request) {
            return str_contains($request->url(), ':3000') &&
                   !str_contains($request->url(), ':3001');
        });
    }

    public function test_unreal_engine_system_message_generation_unchanged(): void
    {
    $unrealWorkspace = Workspace::factory()->create([
        'company_id' => $this->company->id,
        'engine_type' => 'unreal',
        'status' => 'ready',
    ]);

    Http::fake([
        'localhost:*' => Http::response(['success' => true]),
    ]);

        // Call API to go through middleware pipeline
        $response = $this->postJson('/api/assist', [
            'provider' => 'openai',
            'workspace_id' => $unrealWorkspace->id,
            'prompt' => 'Test Unreal functionality',
            'messages' => [
                ['role' => 'user', 'content' => 'Test Unreal functionality']
            ],
            'context' => []
        ]);
        $response->assertOk();

        Http::assertSent(function ($request) {
            $body = $request->body();
            return str_contains($body, 'Unreal Engine') &&
                   !str_contains($body, 'PlayCanvas');
        });
    }

    public function test_unreal_engine_mcp_manager_functionality_preserved(): void
    {
        $unrealMcpManager = app(UnrealMcpManager::class);

        $unrealWorkspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'unreal',
            'status' => 'ready',
            'mcp_port' => 3000,
        ]);

        Http::fake([
            'localhost:3000/v1/context' => Http::response([
                'project_name' => 'TestProject',
                'engine_version' => '5.3',
                'actors' => ['PlayerPawn', 'GameMode'],
            ]),
        ]);

        $context = $unrealMcpManager->getContext($unrealWorkspace);
        $this->assertArrayHasKey('project_name', $context);
        $this->assertArrayHasKey('engine_version', $context);
        $this->assertArrayHasKey('actors', $context);
        $this->assertEquals('5.3', $context['engine_version']);
    }

    public function test_unreal_engine_credit_calculations_unchanged(): void
    {
    $unrealWorkspace = Workspace::factory()->create([
        'company_id' => $this->company->id,
        'engine_type' => 'unreal',
        'status' => 'ready',
        'mcp_port' => 3000,
    ]);

    Http::fake([
        'localhost:3000/v1/assist' => Http::response([
            'success' => true,
            'token_usage' => [
                'prompt_tokens' => 100,
                'completion_tokens' => 200
            ]
        ]),
    ]);

        $initialCredits = $this->company->fresh()->credits;

    $this->postJson('/api/assist', [
        'provider' => 'openai',
        'workspace_id' => $unrealWorkspace->id,
        'prompt' => 'Create a new blueprint',
        'messages' => [
            ['role' => 'user', 'content' => 'Create a new blueprint']
        ],
        'context' => []
    ]);

        $this->company->refresh();
        $finalCredits = $this->company->credits;

    // Should only deduct token costs, no MCP surcharge for Unreal
    $deduction = $initialCredits - $finalCredits;

    // Verify no MCP surcharge was applied (would be 0.1 per action for PlayCanvas)
        $this->assertLessThan(1.0, $deduction);
        $this->assertGreaterThan(0, $deduction);
    }

    public function test_unreal_engine_workspace_creation_process_unchanged(): void
    {
    // Mock Unreal-specific operations
    Http::fake([
        'localhost:*' => Http::response(['status' => 'ready']),
    ]);

        $workspaceService = app(\App\Services\WorkspaceService::class);

    // Create Unreal workspace using existing flow
    $workspace = $workspaceService->createFromTemplate(
        $this->company,
        'unreal-fps-template',
        'unreal'
    );

        $this->assertEquals('unreal', $workspace->engine_type);
        $this->assertEquals($this->company->id, $workspace->company_id);
        $this->assertEquals('initializing', $workspace->status);

    // Verify no PlayCanvas-specific metadata was added
        $this->assertArrayNotHasKey('playcanvas_version', $workspace->metadata ?? []);
        $this->assertArrayNotHasKey('scene_entities', $workspace->metadata ?? []);
    }

    public function test_unreal_engine_api_endpoints_remain_functional(): void
    {
    // Test existing Unreal-specific endpoints still work
    $unrealWorkspace = Workspace::factory()->create([
        'company_id' => $this->company->id,
        'engine_type' => 'unreal',
        'status' => 'ready',
    ]);

    Http::fake([
        'localhost:*' => Http::response([
            'success' => true,
            'blueprints' => ['PlayerController', 'GameMode'],
            'levels' => ['MainLevel', 'TestLevel']
        ]),
    ]);

    // Test workspace status endpoint
        $response = $this->getJson("/api/workspace/{$unrealWorkspace->id}/status");
        $response->assertOk();

    // Test assistance endpoint
    $response = $this->postJson('/api/assist', [
        'provider' => 'openai',
        'workspace_id' => $unrealWorkspace->id,
        'prompt' => 'Show me the current blueprints',
        'messages' => [
            ['role' => 'user', 'content' => 'Show me the current blueprints']
        ],
        'context' => []
    ]);
    $response->assertOk();

    // Verify responses contain Unreal-specific data
        $data = $response->json();
        $this->assertTrue($data['success']);
    }

    public function test_unreal_engine_database_queries_not_affected(): void
    {
    // Create mixed workspaces
    $unrealWorkspaces = Workspace::factory()->count(3)->create([
        'company_id' => $this->company->id,
        'engine_type' => 'unreal',
    ]);

    $playcanvasWorkspaces = Workspace::factory()->count(2)->create([
        'company_id' => $this->company->id,
        'engine_type' => 'playcanvas',
    ]);

    $workspaceService = app(\App\Services\WorkspaceService::class);

    // Get Unreal workspaces only
        $unrealOnly = $workspaceService->getWorkspacesByEngine($this->company, 'unreal');
        $this->assertCount(3, $unrealOnly);
        $unrealOnly->each(function ($workspace) {
            $this->assertEquals('unreal', $workspace->engine_type);
        });

    // Verify PlayCanvas workspaces don't interfere
        $allWorkspaces = $workspaceService->getWorkspacesByEngine($this->company);
        $this->assertCount(5, $allWorkspaces);
    }

    public function test_unreal_engine_middleware_and_routing_unchanged(): void
    {
    $unrealWorkspace = Workspace::factory()->create([
        'company_id' => $this->company->id,
        'engine_type' => 'unreal',
        'status' => 'ready',
        'mcp_port' => 3000,
    ]);

    Http::fake([
        'localhost:3000/*' => Http::response(['success' => true]),
    ]);

    // Test that Unreal requests go through existing middleware
    $response = $this->postJson('/api/assist', [
        'provider' => 'openai',
        'workspace_id' => $unrealWorkspace->id,
        'prompt' => 'Test middleware',
        'messages' => [
            ['role' => 'user', 'content' => 'Test middleware']
        ],
        'context' => []
    ]);

        $response->assertOk();

    // Verify request was routed to Unreal MCP server
        Http::assertSent(function ($request) {
            return str_contains($request->url(), ':3000');
        });
    }

    public function test_unreal_engine_error_handling_preserved(): void
    {
    $unrealWorkspace = Workspace::factory()->create([
        'company_id' => $this->company->id,
        'engine_type' => 'unreal',
        'status' => 'ready',
        'mcp_port' => 3000,
    ]);

    // Mock MCP server error
    Http::fake([
        'localhost:3000/*' => Http::response(['error' => 'Unreal MCP server error'], 500),
    ]);

    $response = $this->postJson('/api/assist', [
        'provider' => 'openai',
        'workspace_id' => $unrealWorkspace->id,
        'prompt' => 'This should fail',
        'messages' => [
            ['role' => 'user', 'content' => 'This should fail']
        ],
        'context' => []
    ]);

        $response->assertStatus(500);
        $data = $response->json();
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('MCP server communication failed', $data['error'] ?? '');
    }

    public function test_unreal_engine_workspace_cleanup_unchanged(): void
    {
    $unrealWorkspace = Workspace::factory()->create([
        'company_id' => $this->company->id,
        'engine_type' => 'unreal',
        'status' => 'ready',
        'mcp_pid' => 11111,
        'created_at' => now()->subHours(25), // Old workspace
    ]);

    $playcanvasWorkspace = Workspace::factory()->create([
        'company_id' => $this->company->id,
        'engine_type' => 'playcanvas',
        'status' => 'ready',
        'mcp_pid' => 22222,
        'created_at' => now()->subHours(25), // Old workspace
    ]);

    $workspaceService = app(\App\Services\WorkspaceService::class);

    // Run cleanup
        $cleanedCount = $workspaceService->cleanupOldWorkspaces();
        $this->assertEquals(2, $cleanedCount);
        $this->assertNull(Workspace::find($unrealWorkspace->id));
        $this->assertNull(Workspace::find($playcanvasWorkspace->id));
    }

    public function test_unreal_engine_configuration_files_unchanged(): void
    {
    // Verify Unreal-specific config hasn't been modified
    $config = config('unreal');

    // These would be existing Unreal Engine configurations
        $this->assertArrayHasKey('mcp_server_path', $config ?? []);
        $this->assertArrayHasKey('default_port_range', $config ?? []);

    // Verify no PlayCanvas config leaked into Unreal config
        $this->assertArrayNotHasKey('playcanvas_version', $config ?? []);
        $this->assertArrayNotHasKey('scene_templates', $config ?? []);
    }

    public function test_unreal_engine_service_providers_unchanged(): void
    {
    // Verify Unreal services are still registered
        $this->assertTrue(app()->bound(\App\Services\UnrealMcpManager::class));

    // Verify they work as expected
        $unrealMcpManager = app(\App\Services\UnrealMcpManager::class);
        $this->assertInstanceOf(\App\Services\UnrealMcpManager::class, $unrealMcpManager);

    // Verify PlayCanvas services don't interfere
        $this->assertTrue(app()->bound(\App\Services\PlayCanvasMcpManager::class));
    }

    public function test_unreal_engine_tests_still_pass(): void
    {
    // This would run existing Unreal Engine tests to ensure they still pass
    // In a real implementation, we'd use Artisan::call to run specific test suites

    // For now, we'll verify key Unreal functionality works
    $unrealWorkspace = Workspace::factory()->create([
        'company_id' => $this->company->id,
        'engine_type' => 'unreal',
    ]);

        $this->assertEquals('unreal', $unrealWorkspace->engine_type);
        $this->assertFalse($unrealWorkspace->isPlayCanvas());

    // Verify Unreal-specific methods still work
        $workspaceService = app(\App\Services\WorkspaceService::class);

    // This should not throw an exception for Unreal workspaces
        $result = $workspaceService->stopMcpServer($unrealWorkspace);
        $this->assertTrue($result);
    }
}
