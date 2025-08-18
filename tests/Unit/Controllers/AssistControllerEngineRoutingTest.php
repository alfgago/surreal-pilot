<?php

namespace Tests\Unit\Controllers;

use Tests\TestCase;
use App\Http\Controllers\Api\AssistController;
use App\Services\PlayCanvasMcpManager;
use App\Services\UnrealMcpManager;
use App\Services\CreditManager;
use App\Services\PrismProviderManager;
use App\Services\RolePermissionService;
use App\Services\ApiErrorHandler;
use App\Services\ErrorMonitoringService;
use App\Models\Workspace;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Mockery;

class AssistControllerEngineRoutingTest extends TestCase
{
    use RefreshDatabase;

    private AssistController $controller;
    private PlayCanvasMcpManager $playCanvasMcpManager;
    private UnrealMcpManager $unrealMcpManager;
    private CreditManager $creditManager;
    private PrismProviderManager $providerManager;
    private RolePermissionService $roleService;
    private ApiErrorHandler $errorHandler;
    private ErrorMonitoringService $errorMonitoring;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mock services
        $this->playCanvasMcpManager = Mockery::mock(PlayCanvasMcpManager::class);
        $this->unrealMcpManager = Mockery::mock(UnrealMcpManager::class);
        $this->creditManager = Mockery::mock(CreditManager::class);
        $this->providerManager = Mockery::mock(PrismProviderManager::class);
        $this->roleService = Mockery::mock(RolePermissionService::class);
        $this->errorHandler = Mockery::mock(ApiErrorHandler::class);
        $this->errorMonitoring = Mockery::mock(ErrorMonitoringService::class);

        // Create controller with mocked dependencies
        $this->controller = new AssistController(
            $this->providerManager,
            $this->creditManager,
            $this->roleService,
            $this->errorHandler,
            $this->errorMonitoring,
            $this->playCanvasMcpManager,
            $this->unrealMcpManager
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_detects_playcanvas_engine_type_from_context()
    {
        $context = ['engine_type' => 'playcanvas'];
        
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('detectEngineType');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->controller, $context);
        
        $this->assertEquals('playcanvas', $result);
    }

    /** @test */
    public function it_detects_unreal_engine_type_from_context()
    {
        $context = ['engine_type' => 'unreal'];
        
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('detectEngineType');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->controller, $context);
        
        $this->assertEquals('unreal', $result);
    }

    /** @test */
    public function it_detects_playcanvas_from_workspace_id()
    {
        $company = Company::factory()->create();
        $workspace = Workspace::factory()->create([
            'company_id' => $company->id,
            'engine_type' => 'playcanvas'
        ]);

        $context = ['workspace_id' => $workspace->id];
        
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('detectEngineType');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->controller, $context);
        
        $this->assertEquals('playcanvas', $result);
    }

    /** @test */
    public function it_detects_playcanvas_from_scene_context()
    {
        $context = ['scene' => 'Main Scene with entities'];
        
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('detectEngineType');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->controller, $context);
        
        $this->assertEquals('playcanvas', $result);
    }

    /** @test */
    public function it_detects_unreal_from_blueprint_context()
    {
        $context = ['blueprint' => 'PlayerController blueprint'];
        
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('detectEngineType');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->controller, $context);
        
        $this->assertEquals('unreal', $result);
    }

    /** @test */
    public function it_defaults_to_unreal_engine_for_backward_compatibility()
    {
        $context = [];
        
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('detectEngineType');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->controller, $context);
        
        $this->assertEquals('unreal', $result);
    }

    /** @test */
    public function it_builds_playcanvas_system_message_correctly()
    {
        $context = [
            'engine_type' => 'playcanvas',
            'scene' => 'Main Scene',
            'entities' => ['Player', 'Enemy', 'Platform'],
            'components' => ['Transform', 'RigidBody', 'Script'],
            'scripts' => 'player.js with movement logic',
            'assets' => ['player.png', 'enemy.png']
        ];
        
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('buildSystemMessage');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->controller, $context);
        
        $this->assertStringContainsString('PlayCanvas game developers', $result);
        $this->assertStringContainsString('Current Scene Context:', $result);
        $this->assertStringContainsString('Main Scene', $result);
        $this->assertStringContainsString('Scene Entities:', $result);
        $this->assertStringContainsString('Player', $result);
        $this->assertStringContainsString('Component Data:', $result);
        $this->assertStringContainsString('Transform', $result);
        $this->assertStringContainsString('Script Context:', $result);
        $this->assertStringContainsString('player.js', $result);
        $this->assertStringContainsString('Asset Information:', $result);
        $this->assertStringContainsString('player.png', $result);
        $this->assertStringContainsString('mobile-first game development', $result);
    }

    /** @test */
    public function it_builds_unreal_system_message_correctly()
    {
        $context = [
            'engine_type' => 'unreal',
            'blueprint' => 'PlayerController blueprint',
            'errors' => ['Compilation error in BP_Player'],
            'selection' => 'Selected node: BeginPlay'
        ];
        
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('buildSystemMessage');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->controller, $context);
        
        $this->assertStringContainsString('Unreal Engine developers', $result);
        $this->assertStringContainsString('Current Blueprint Context:', $result);
        $this->assertStringContainsString('PlayerController blueprint', $result);
        $this->assertStringContainsString('Build Errors:', $result);
        $this->assertStringContainsString('Compilation error', $result);
        $this->assertStringContainsString('Selected Context:', $result);
        $this->assertStringContainsString('BeginPlay', $result);
    }

    /** @test */
    public function it_routes_commands_to_playcanvas_mcp_server()
    {
        $company = Company::factory()->create();
        $workspace = Workspace::factory()->create([
            'company_id' => $company->id,
            'engine_type' => 'playcanvas'
        ]);

        $command = 'Create a new entity';
        $expectedResponse = ['success' => true, 'entity_id' => 123];

        $this->playCanvasMcpManager
            ->shouldReceive('sendCommand')
            ->once()
            ->with($workspace, $command)
            ->andReturn($expectedResponse);

        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('routeToMcpServer');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->controller, $workspace, $command);
        
        $this->assertEquals($expectedResponse, $result);
    }

    /** @test */
    public function it_routes_commands_to_unreal_mcp_server()
    {
        $company = Company::factory()->create();
        $workspace = Workspace::factory()->create([
            'company_id' => $company->id,
            'engine_type' => 'unreal'
        ]);

        $command = 'Create a new blueprint';
        $expectedResponse = ['success' => true, 'blueprint_id' => 456];

        $this->unrealMcpManager
            ->shouldReceive('sendCommand')
            ->once()
            ->with($workspace, $command)
            ->andReturn($expectedResponse);

        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('routeToMcpServer');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->controller, $workspace, $command);
        
        $this->assertEquals($expectedResponse, $result);
    }

    /** @test */
    public function it_throws_exception_for_unsupported_engine_type()
    {
        $company = Company::factory()->create();
        
        // Create a workspace with a valid engine type first
        $workspace = Workspace::factory()->create([
            'company_id' => $company->id,
            'engine_type' => 'playcanvas'
        ]);
        
        // Then manually set an unsupported engine type to bypass the constraint
        $workspace->engine_type = 'unsupported';

        $command = 'Some command';

        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('routeToMcpServer');
        $method->setAccessible(true);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unsupported engine type: unsupported');
        
        $method->invoke($this->controller, $workspace, $command);
    }

    /** @test */
    public function it_estimates_tokens_with_playcanvas_context()
    {
        $messages = [
            ['role' => 'user', 'content' => 'Create a player entity']
        ];
        
        $context = [
            'scene' => 'Main Scene with multiple entities',
            'entities' => ['Player', 'Enemy'],
            'components' => ['Transform', 'RigidBody'],
            'scripts' => 'player.js with movement logic',
            'assets' => ['player.png', 'enemy.png']
        ];

        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('estimateTokenUsage');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->controller, $messages, $context);
        
        $this->assertIsInt($result);
        $this->assertGreaterThanOrEqual(10, $result);
        $this->assertLessThanOrEqual(4000, $result);
    }

    /** @test */
    public function it_estimates_tokens_with_unreal_context()
    {
        $messages = [
            ['role' => 'user', 'content' => 'Fix this blueprint error']
        ];
        
        $context = [
            'blueprint' => 'PlayerController with movement logic',
            'errors' => ['Compilation error in BeginPlay'],
            'selection' => 'Selected node: InputAction'
        ];

        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('estimateTokenUsage');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->controller, $messages, $context);
        
        $this->assertIsInt($result);
        $this->assertGreaterThanOrEqual(10, $result);
        $this->assertLessThanOrEqual(4000, $result);
    }

    /** @test */
    public function it_handles_array_and_string_context_values()
    {
        $messages = [
            ['role' => 'user', 'content' => 'Test message']
        ];
        
        // Test with array values
        $contextWithArrays = [
            'entities' => ['Player', 'Enemy', 'Platform'],
            'components' => ['Transform', 'RigidBody', 'Script'],
            'assets' => ['texture1.png', 'texture2.png']
        ];

        // Test with string values
        $contextWithStrings = [
            'entities' => 'Player, Enemy, Platform',
            'components' => 'Transform, RigidBody, Script',
            'assets' => 'texture1.png, texture2.png'
        ];

        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('estimateTokenUsage');
        $method->setAccessible(true);
        
        $resultArrays = $method->invoke($this->controller, $messages, $contextWithArrays);
        $resultStrings = $method->invoke($this->controller, $messages, $contextWithStrings);
        
        $this->assertIsInt($resultArrays);
        $this->assertIsInt($resultStrings);
        $this->assertGreaterThan(10, $resultArrays);
        $this->assertGreaterThan(10, $resultStrings);
    }
}