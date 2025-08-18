<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Company;
use App\Models\Workspace;
use App\Services\PlayCanvasMcpManager;
use App\Services\UnrealMcpManager;
use App\Services\CreditManager;
use App\Services\RolePermissionService;
use App\Services\PrismProviderManager;
use App\Services\ApiErrorHandler;
use App\Services\ErrorMonitoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Mockery;

class McpCommandTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $user;
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user and company
        $this->company = Company::factory()->create(['credits' => 100]);
        $this->user = User::factory()->create();
        $this->user->companies()->attach($this->company->id, ['role' => 'developer']);
        $this->user->update(['current_company_id' => $this->company->id]);

        // Mock all required services
        $this->mockServices();
    }

    private function mockServices(): void
    {
        // Mock MCP managers
        $playCanvasMcpManager = Mockery::mock(PlayCanvasMcpManager::class);
        $unrealMcpManager = Mockery::mock(UnrealMcpManager::class);
        $creditManager = Mockery::mock(CreditManager::class);
        $roleService = Mockery::mock(RolePermissionService::class);
        $providerManager = Mockery::mock(PrismProviderManager::class);
        $errorHandler = Mockery::mock(ApiErrorHandler::class);
        $errorMonitoring = Mockery::mock(ErrorMonitoringService::class);

        $this->app->instance(PlayCanvasMcpManager::class, $playCanvasMcpManager);
        $this->app->instance(UnrealMcpManager::class, $unrealMcpManager);
        $this->app->instance(CreditManager::class, $creditManager);
        $this->app->instance(RolePermissionService::class, $roleService);
        $this->app->instance(PrismProviderManager::class, $providerManager);
        $this->app->instance(ApiErrorHandler::class, $errorHandler);
        $this->app->instance(ErrorMonitoringService::class, $errorMonitoring);

        // Set up default mock behaviors
        $roleService->shouldReceive('canAccessAI')->andReturn(true);
        $providerManager->shouldReceive('resolveProvider')->andReturn('openai');
        $creditManager->shouldReceive('canAffordRequest')->andReturn(true);
        $creditManager->shouldReceive('deductCredits')->andReturn(true);
        $errorMonitoring->shouldReceive('trackError')->andReturn(true);
        $errorHandler->shouldReceive('handleGeneralError')->andReturn(response()->json(['error' => 'General error'], 500));
        
        // Store references for specific test mocking
        $this->app['test.playcanvas_mcp'] = $playCanvasMcpManager;
        $this->app['test.unreal_mcp'] = $unrealMcpManager;
        $this->app['test.credit_manager'] = $creditManager;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeMcpCommandRequest(int $workspaceId, string $command): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/mcp-command', [
            'workspace_id' => $workspaceId,
            'command' => $command,
            'provider' => 'openai'
        ]);
    }

    /** @test */
    public function it_executes_playcanvas_mcp_command_successfully()
    {
        Sanctum::actingAs($this->user);

        $workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
            'status' => 'ready'
        ]);

        $command = 'Create a new player entity';
        $mcpResponse = [
            'success' => true,
            'entity_id' => 123,
            'message' => 'Player entity created successfully'
        ];

        // Mock MCP command execution
        $this->app['test.playcanvas_mcp']
            ->shouldReceive('sendCommand')
            ->once()
            ->with($workspace, $command)
            ->andReturn($mcpResponse);

        $response = $this->makeMcpCommandRequest($workspace->id, $command);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => $mcpResponse,
                'metadata' => [
                    'workspace_id' => $workspace->id,
                    'engine_type' => 'playcanvas'
                ]
            ]);
    }

    /** @test */
    public function it_executes_unreal_mcp_command_successfully()
    {
        Sanctum::actingAs($this->user);

        $workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'unreal',
            'status' => 'ready'
        ]);

        $command = 'Create a new blueprint';
        $mcpResponse = [
            'success' => true,
            'blueprint_id' => 456,
            'message' => 'Blueprint created successfully'
        ];

        // Mock MCP command execution
        $this->app['test.unreal_mcp']
            ->shouldReceive('sendCommand')
            ->once()
            ->with($workspace, $command)
            ->andReturn($mcpResponse);

        $response = $this->makeMcpCommandRequest($workspace->id, $command);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => $mcpResponse,
                'metadata' => [
                    'workspace_id' => $workspace->id,
                    'engine_type' => 'unreal'
                ]
            ]);
    }

    /** @test */
    public function it_returns_404_for_workspace_not_owned_by_company()
    {
        Sanctum::actingAs($this->user);

        $otherCompany = Company::factory()->create();
        $workspace = Workspace::factory()->create([
            'company_id' => $otherCompany->id,
            'engine_type' => 'playcanvas',
            'status' => 'ready'
        ]);

        $response = $this->makeMcpCommandRequest($workspace->id, 'Some command');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error' => 'Workspace not found or access denied'
            ]);
    }

    /** @test */
    public function it_returns_400_for_workspace_not_ready()
    {
        Sanctum::actingAs($this->user);

        $workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
            'status' => 'initializing'
        ]);

        $response = $this->makeMcpCommandRequest($workspace->id, 'Some command');

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => 'Workspace is not ready for commands',
                'workspace_status' => 'initializing'
            ]);
    }

    /** @test */
    public function it_validates_required_fields()
    {
        Sanctum::actingAs($this->user);

        // Test missing workspace_id
        $response = $this->postJson('/api/mcp-command', [
            'command' => 'Some command',
            'provider' => 'openai'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['workspace_id']);

        // Test missing command
        $workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
            'status' => 'ready'
        ]);

        $response = $this->postJson('/api/mcp-command', [
            'workspace_id' => $workspace->id,
            'provider' => 'openai'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['command']);
    }

    /** @test */
    public function it_validates_command_length()
    {
        Sanctum::actingAs($this->user);

        $workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
            'status' => 'ready'
        ]);

        $longCommand = str_repeat('a', 10001); // Exceeds max length

        $response = $this->postJson('/api/mcp-command', [
            'workspace_id' => $workspace->id,
            'command' => $longCommand,
            'provider' => 'openai'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['command']);
    }

    /** @test */
    public function it_requires_authentication()
    {
        $workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
            'status' => 'ready'
        ]);

        $response = $this->makeMcpCommandRequest($workspace->id, 'Some command');

        $response->assertStatus(401);
    }
}