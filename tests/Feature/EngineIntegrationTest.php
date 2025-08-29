<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Models\Workspace;
use App\Services\EngineSelectionService;
use App\Services\PlayCanvasMcpManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EngineIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Company $company;
    private Workspace $playcanvasWorkspace;
    private Workspace $unrealWorkspace;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user and company
        $this->company = Company::factory()->create([
            'name' => 'Test Company',
            'plan' => 'pro',
        ]);

        $this->user = User::factory()->create([
            'current_company_id' => $this->company->id,
            'selected_engine_type' => 'playcanvas',
        ]);

        $this->user->companies()->attach($this->company);

        // Create test workspaces
        $this->playcanvasWorkspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'PlayCanvas Test Workspace',
            'engine_type' => 'playcanvas',
            'status' => 'ready',
            'mcp_port' => 3001,
            'mcp_pid' => 12345,
            'preview_url' => 'http://localhost:3001/preview/1',
        ]);

        $this->unrealWorkspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Unreal Test Workspace',
            'engine_type' => 'unreal',
            'status' => 'ready',
        ]);
    }

    public function test_can_get_engine_status_for_playcanvas_workspace(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/workspaces/{$this->playcanvasWorkspace->id}/engine/status");

        $response->assertOk()
            ->assertJsonStructure([
                'workspace_id',
                'engine_type',
                'status',
                'message',
                'details',
            ])
            ->assertJson([
                'workspace_id' => $this->playcanvasWorkspace->id,
                'engine_type' => 'playcanvas',
            ]);
    }

    public function test_can_get_engine_status_for_unreal_workspace(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/workspaces/{$this->unrealWorkspace->id}/engine/status");

        $response->assertOk()
            ->assertJsonStructure([
                'workspace_id',
                'engine_type',
                'status',
                'message',
                'details',
            ])
            ->assertJson([
                'workspace_id' => $this->unrealWorkspace->id,
                'engine_type' => 'unreal',
            ]);
    }

    public function test_can_get_workspace_context(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/workspaces/{$this->playcanvasWorkspace->id}/context");

        $response->assertOk()
            ->assertJsonStructure([
                'workspace' => [
                    'id',
                    'name',
                    'engine_type',
                    'status',
                ],
                'engine' => [
                    'type',
                    'display_name',
                    'available',
                ],
                'games',
                'recent_conversations',
                'timestamp',
            ])
            ->assertJson([
                'workspace' => [
                    'id' => $this->playcanvasWorkspace->id,
                    'name' => 'PlayCanvas Test Workspace',
                    'engine_type' => 'playcanvas',
                ],
                'engine' => [
                    'type' => 'playcanvas',
                    'display_name' => 'PlayCanvas',
                ],
            ]);
    }

    public function test_can_get_playcanvas_status(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/workspaces/{$this->playcanvasWorkspace->id}/playcanvas/status");

        $response->assertOk()
            ->assertJsonStructure([
                'mcp_running',
                'port',
                'preview_available',
                'preview_url',
                'last_update',
                'health_check',
            ]);
    }

    public function test_can_get_unreal_status(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/workspaces/{$this->unrealWorkspace->id}/unreal/status");

        $response->assertOk()
            ->assertJsonStructure([
                'connected',
                'version',
                'plugin_version',
                'project_name',
                'last_ping',
                'error',
            ])
            ->assertJson([
                'connected' => false,
            ]);
    }

    public function test_can_test_unreal_connection(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/workspaces/{$this->unrealWorkspace->id}/unreal/test", [
                'host' => 'localhost',
                'port' => 8080,
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'connected',
                'error',
            ]);
    }

    public function test_can_refresh_playcanvas_preview(): void
    {
        // Mock the MCP manager to avoid actual server operations
        $this->mock(PlayCanvasMcpManager::class, function ($mock) {
            $mock->shouldReceive('getServerStatus')
                ->andReturn('running');
            $mock->shouldReceive('sendCommand')
                ->andReturn(['preview_url' => 'http://localhost:3001/preview/1']);
        });

        $response = $this->actingAs($this->user)
            ->postJson("/api/workspaces/{$this->playcanvasWorkspace->id}/playcanvas/refresh");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'preview_url',
                'timestamp',
            ])
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_can_get_engine_ai_config(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/engine/playcanvas/ai-config');

        $response->assertOk()
            ->assertJsonStructure([
                'engine_type',
                'config',
            ])
            ->assertJson([
                'engine_type' => 'playcanvas',
            ]);
    }

    public function test_can_update_engine_ai_config(): void
    {
        $response = $this->actingAs($this->user)
            ->putJson('/api/engine/playcanvas/ai-config', [
                'model' => 'claude-3-5-sonnet-20241022',
                'temperature' => 0.3,
                'max_tokens' => 1500,
                'provider' => 'anthropic',
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'engine_type',
                'config',
            ])
            ->assertJson([
                'success' => true,
                'engine_type' => 'playcanvas',
            ]);
    }

    public function test_engine_context_includes_workspace_specific_data(): void
    {
        // Create some test data
        $game = \App\Models\Game::factory()->create([
            'workspace_id' => $this->playcanvasWorkspace->id,
            'title' => 'Test Game',
        ]);

        $conversation = \App\Models\ChatConversation::factory()->create([
            'workspace_id' => $this->playcanvasWorkspace->id,
            'title' => 'Test Conversation',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/workspaces/{$this->playcanvasWorkspace->id}/context");

        $response->assertOk()
            ->assertJsonPath('games.0.title', 'Test Game')
            ->assertJsonPath('recent_conversations.0.title', 'Test Conversation');
    }

    public function test_cannot_access_other_company_workspace_engine_status(): void
    {
        $otherCompany = Company::factory()->create();
        $otherWorkspace = Workspace::factory()->create([
            'company_id' => $otherCompany->id,
            'engine_type' => 'playcanvas',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/workspaces/{$otherWorkspace->id}/engine/status");

        $response->assertNotFound();
    }

    public function test_engine_selection_service_integration(): void
    {
        $engineService = app(EngineSelectionService::class);

        // Test getting available engines
        $engines = $engineService->getAvailableEngines();
        $this->assertArrayHasKey('playcanvas', $engines);
        $this->assertArrayHasKey('unreal', $engines);

        // Test engine validation
        $this->assertTrue($engineService->validateEngineType('playcanvas'));
        $this->assertTrue($engineService->validateEngineType('unreal'));
        $this->assertFalse($engineService->validateEngineType('invalid'));

        // Test user engine preference
        $this->assertEquals('playcanvas', $engineService->getUserEnginePreference($this->user));

        // Test setting new preference
        $engineService->setUserEnginePreference($this->user, 'unreal');
        $this->assertEquals('unreal', $engineService->getUserEnginePreference($this->user));
    }

    public function test_chat_page_loads_with_engine_context(): void
    {
        // Set up session with selected workspace
        session(['selected_workspace_id' => $this->playcanvasWorkspace->id]);

        $response = $this->actingAs($this->user)
            ->get('/chat');

        $response->assertOk()
            ->assertInertia(fn ($page) => 
                $page->component('Chat')
                    ->has('workspace')
                    ->where('workspace.id', $this->playcanvasWorkspace->id)
                    ->where('workspace.engine_type', 'playcanvas')
                    ->has('conversations')
                    ->has('providers')
            );
    }

    public function test_different_engine_configurations_affect_ai_responses(): void
    {
        // This test would verify that different engine types result in different AI configurations
        // For now, we'll test that the engine type is properly passed to the context

        $playcanvasContext = $this->actingAs($this->user)
            ->getJson("/api/workspaces/{$this->playcanvasWorkspace->id}/context")
            ->json();

        $unrealContext = $this->actingAs($this->user)
            ->getJson("/api/workspaces/{$this->unrealWorkspace->id}/context")
            ->json();

        $this->assertEquals('playcanvas', $playcanvasContext['engine']['type']);
        $this->assertEquals('unreal', $unrealContext['engine']['type']);
        $this->assertEquals('PlayCanvas', $playcanvasContext['engine']['display_name']);
        $this->assertEquals('Unreal Engine', $unrealContext['engine']['display_name']);

        // Verify engine-specific context is included
        $this->assertArrayHasKey('playcanvas', $playcanvasContext);
        $this->assertArrayHasKey('unreal', $unrealContext);
    }
}