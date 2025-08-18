<?php

namespace Tests\Feature\PlayCanvas;

use App\Http\Controllers\Api\AssistController;
use App\Models\Company;
use App\Models\Workspace;
use App\Services\CreditManager;
use App\Services\PlayCanvasMcpManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AssistanceOperationsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->company = Company::factory()->create(['credits' => 100.0]);
        $this->workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
            'status' => 'ready',
            'mcp_port' => 3001,
            'mcp_pid' => 12345,
            'preview_url' => 'http://localhost:3001/preview',
        ]);
        
        $this->user = $this->company->users()->first();
        $this->actingAs($this->user);
    }

    public function test_playcanvas_assistance_request_routes_to_correct_mcp_server()
    {
        // Mock MCP server response
        Http::fake([
            'localhost:3001/v1/context' => Http::response([
                'scene' => 'Main Scene',
                'entities' => ['Player', 'Ground', 'Camera'],
                'components' => ['script', 'render', 'collision']
            ]),
            'localhost:3001/v1/assist' => Http::response([
                'success' => true,
                'changes' => ['Modified jump height in PlayerController'],
                'preview_url' => 'http://localhost:3001/preview'
            ])
        ]);
        
        $response = $this->postJson('/api/assist', [
            'workspace_id' => $this->workspace->id,
            'prompt' => 'Increase the player jump height by 50%',
            'context' => []
        ]);
        
        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'response',
            'preview_url',
            'credits_remaining'
        ]);
        
        Http::assertSent(function ($request) {
            return $request->url() === 'http://localhost:3001/v1/assist' &&
                   str_contains($request->body(), 'jump height');
        });
    }

    public function test_playcanvas_context_includes_scene_and_entity_information()
    {
        Http::fake([
            'localhost:3001/v1/context' => Http::response([
                'scene' => 'Game Scene',
                'entities' => [
                    ['name' => 'Player', 'components' => ['script', 'render']],
                    ['name' => 'Enemy', 'components' => ['ai', 'render']],
                ],
                'scripts' => ['PlayerController.js', 'EnemyAI.js']
            ])
        ]);
        
        $controller = app(AssistController::class);
        $request = Request::create('/api/assist', 'POST', [
            'workspace_id' => $this->workspace->id,
            'prompt' => 'Show me the current scene setup',
            'context' => []
        ]);
        
        $response = $controller->assist($request);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'context');
        });
    }

    public function test_assistance_operations_deduct_credits_with_mcp_surcharge()
    {
        Http::fake([
            'localhost:3001/v1/assist' => Http::response([
                'success' => true,
                'token_usage' => ['prompt_tokens' => 50, 'completion_tokens' => 100],
                'mcp_actions' => 3
            ])
        ]);
        
        $initialCredits = $this->company->fresh()->credits;
        
        $this->postJson('/api/assist', [
            'workspace_id' => $this->workspace->id,
            'prompt' => 'Add a new enemy spawn point',
            'context' => []
        ]);
        
        $this->company->refresh();
        $finalCredits = $this->company->credits;
        
        // Should deduct token costs + MCP surcharge (3 actions * 0.1 = 0.3)
        $this->assertLessThan($initialCredits, $finalCredits);
        
        // Check that MCP surcharge was applied
        $expectedSurcharge = 3 * 0.1; // 3 MCP actions * 0.1 credit each
        $actualDeduction = $initialCredits - $finalCredits;
        $this->assertGreaterThan($expectedSurcharge, $actualDeduction);
    }

    public function test_assistance_handles_playcanvas_specific_commands()
    {
        Http::fake([
            'localhost:3001/v1/assist' => Http::response([
                'success' => true,
                'changes' => [
                    'Created new entity: PowerUp',
                    'Added script component: PowerUpController',
                    'Set position: (10, 0, 5)'
                ],
                'entities_modified' => ['PowerUp'],
                'scripts_created' => ['PowerUpController.js']
            ])
        ]);
        
        $response = $this->postJson('/api/assist', [
            'workspace_id' => $this->workspace->id,
            'prompt' => 'Create a power-up that increases player speed',
            'context' => []
        ]);
        
        $response->assertOk();
        $data = $response->json();
        
        $this->assertTrue($data['success']);
        $this->assertStringContainsString('PowerUp', $data['response']);
    }

    public function test_assistance_fails_gracefully_when_mcp_server_is_down()
    {
        Http::fake([
            'localhost:3001/*' => Http::response([], 500)
        ]);
        
        $response = $this->postJson('/api/assist', [
            'workspace_id' => $this->workspace->id,
            'prompt' => 'Add a new weapon',
            'context' => []
        ]);
        
        $response->assertStatus(500);
        $response->assertJson([
            'success' => false,
            'error' => 'MCP server communication failed'
        ]);
    }

    public function test_assistance_validates_workspace_engine_type()
    {
        $unrealWorkspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'unreal',
            'status' => 'ready',
        ]);
        
        $response = $this->postJson('/api/assist', [
            'workspace_id' => $unrealWorkspace->id,
            'prompt' => 'This should not work for Unreal workspace',
            'context' => []
        ]);
        
        // Should route to Unreal MCP, not PlayCanvas
        $response->assertOk(); // But won't hit PlayCanvas endpoints
        
        Http::assertNothingSent();
    }

    public function test_assistance_tracks_operation_metrics()
    {
        Http::fake([
            'localhost:3001/v1/assist' => Http::response([
                'success' => true,
                'execution_time_ms' => 1500,
                'mcp_actions' => 2,
                'entities_modified' => 1
            ])
        ]);
        
        $response = $this->postJson('/api/assist', [
            'workspace_id' => $this->workspace->id,
            'prompt' => 'Optimize the lighting in the scene',
            'context' => []
        ]);
        
        $response->assertOk();
        
        // Verify metrics are captured (would be stored in logs/metrics)
        $this->assertArrayHasKey('execution_time_ms', $response->json());
    }

    public function test_assistance_handles_complex_scene_modifications()
    {
        Http::fake([
            'localhost:3001/v1/context' => Http::response([
                'scene' => 'Complex Scene',
                'entities' => array_fill(0, 50, ['name' => 'Entity', 'components' => ['render']])
            ]),
            'localhost:3001/v1/assist' => Http::response([
                'success' => true,
                'changes' => [
                    'Modified 15 entities',
                    'Updated lighting system',
                    'Optimized render pipeline'
                ],
                'performance_impact' => 'minimal'
            ])
        ]);
        
        $response = $this->postJson('/api/assist', [
            'workspace_id' => $this->workspace->id,
            'prompt' => 'Optimize performance for mobile devices',
            'context' => []
        ]);
        
        $response->assertOk();
        $this->assertStringContainsString('Optimized', $response->json()['response']);
    }
}