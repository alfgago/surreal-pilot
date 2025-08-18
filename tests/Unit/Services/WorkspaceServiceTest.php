<?php

namespace Tests\Unit\Services;

use App\Models\Company;
use App\Models\DemoTemplate;
use App\Models\MultiplayerSession;
use App\Models\Workspace;
use App\Services\PlayCanvasMcpManager;
use App\Services\TemplateRegistry;
use App\Services\WorkspaceService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class WorkspaceServiceTest extends TestCase
{
    use RefreshDatabase;

    private WorkspaceService $workspaceService;
    private PlayCanvasMcpManager $mcpManager;
    private TemplateRegistry $templateRegistry;
    private Company $company;
    private DemoTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mock dependencies
        $this->mcpManager = Mockery::mock(PlayCanvasMcpManager::class);
        $this->templateRegistry = Mockery::mock(TemplateRegistry::class);

        // Create service instance with mocked dependencies
        $this->workspaceService = new WorkspaceService(
            $this->mcpManager,
            $this->templateRegistry
        );

        // Create test data
        $this->company = Company::factory()->create();
        $this->template = DemoTemplate::factory()->create([
            'id' => 'test-template',
            'name' => 'Test Template',
            'engine_type' => 'playcanvas',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_create_from_template_success()
    {
        // Arrange
        $templateId = 'test-template';
        $engineType = 'playcanvas';
        $workspaceName = 'Test Workspace';
        $workspacePath = storage_path("workspaces/1");

        $this->templateRegistry
            ->shouldReceive('cloneTemplate')
            ->with($templateId, $workspacePath)
            ->once()
            ->andReturn(true);

        $this->mcpManager
            ->shouldReceive('startServer')
            ->once()
            ->andReturnUsing(function ($workspace) {
                // Simulate what the real MCP manager does - update the workspace
                $workspace->update([
                    'mcp_port' => 3001,
                    'mcp_pid' => 12345,
                    'preview_url' => 'http://localhost:3001/preview/' . $workspace->id,
                    'status' => 'ready'
                ]);
                
                return [
                    'port' => 3001,
                    'pid' => 12345,
                    'preview_url' => 'http://localhost:3001/preview/' . $workspace->id
                ];
            });

        // Act
        $workspace = $this->workspaceService->createFromTemplate(
            $this->company,
            $templateId,
            $engineType,
            $workspaceName
        );

        // Assert
        $this->assertInstanceOf(Workspace::class, $workspace);
        $this->assertEquals($this->company->id, $workspace->company_id);
        $this->assertEquals($workspaceName, $workspace->name);
        $this->assertEquals($engineType, $workspace->engine_type);
        $this->assertEquals($templateId, $workspace->template_id);
        $this->assertEquals('ready', $workspace->status);
        $this->assertEquals(3001, $workspace->mcp_port);
        $this->assertEquals(12345, $workspace->mcp_pid);
        $this->assertEquals('http://localhost:3001/preview/1', $workspace->preview_url);
        $this->assertArrayHasKey('template_name', $workspace->metadata);
        $this->assertArrayHasKey('created_from_template', $workspace->metadata);
    }

    public function test_create_from_template_with_invalid_template()
    {
        // Arrange
        $templateId = 'nonexistent-template';
        $engineType = 'playcanvas';

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Template 'nonexistent-template' not found or inactive for engine type 'playcanvas'");

        $this->workspaceService->createFromTemplate(
            $this->company,
            $templateId,
            $engineType
        );
    }

    public function test_create_from_template_with_clone_failure()
    {
        // Arrange
        $templateId = 'test-template';
        $engineType = 'playcanvas';
        $workspacePath = storage_path("workspaces/1");

        $this->templateRegistry
            ->shouldReceive('cloneTemplate')
            ->with($templateId, $workspacePath)
            ->once()
            ->andReturn(false);

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Failed to clone template 'test-template' to workspace");

        $this->workspaceService->createFromTemplate(
            $this->company,
            $templateId,
            $engineType
        );
    }

    public function test_create_from_template_generates_unique_name()
    {
        // Arrange
        $templateId = 'test-template';
        $engineType = 'playcanvas';
        
        // Create existing workspace with same name as template
        Workspace::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Test Template'
        ]);

        $workspacePath = storage_path("workspaces/2");

        $this->templateRegistry
            ->shouldReceive('cloneTemplate')
            ->with($templateId, $workspacePath)
            ->once()
            ->andReturn(true);

        $this->mcpManager
            ->shouldReceive('startServer')
            ->once()
            ->andReturn([
                'port' => 3001,
                'pid' => 12345,
                'preview_url' => 'http://localhost:3001/preview/2'
            ]);

        // Act
        $workspace = $this->workspaceService->createFromTemplate(
            $this->company,
            $templateId,
            $engineType
        );

        // Assert
        $this->assertEquals('Test Template (1)', $workspace->name);
    }

    public function test_start_mcp_server_success()
    {
        // Arrange
        $workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
            'status' => 'initializing'
        ]);

        $expectedResult = [
            'port' => 3001,
            'pid' => 12345,
            'preview_url' => 'http://localhost:3001/preview/' . $workspace->id
        ];

        $this->mcpManager
            ->shouldReceive('startServer')
            ->with($workspace)
            ->once()
            ->andReturnUsing(function ($workspace) use ($expectedResult) {
                // Simulate what the real MCP manager does - update the workspace
                $workspace->update([
                    'mcp_port' => $expectedResult['port'],
                    'mcp_pid' => $expectedResult['pid'],
                    'preview_url' => $expectedResult['preview_url'],
                    'status' => 'ready'
                ]);
                
                return $expectedResult;
            });

        // Act
        $result = $this->workspaceService->startMcpServer($workspace);

        // Assert
        $this->assertEquals($expectedResult, $result);
        $workspace->refresh();
        $this->assertEquals('ready', $workspace->status);
    }

    public function test_start_mcp_server_for_non_playcanvas_workspace()
    {
        // Arrange
        $workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'unreal'
        ]);

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('MCP server can only be started for PlayCanvas workspaces');

        $this->workspaceService->startMcpServer($workspace);
    }

    public function test_start_mcp_server_already_running()
    {
        // Arrange
        $workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
            'mcp_port' => 3001,
            'mcp_pid' => 12345,
            'preview_url' => 'http://localhost:3001/preview/1'
        ]);

        $this->mcpManager
            ->shouldReceive('getServerStatus')
            ->with($workspace)
            ->once()
            ->andReturn('running');

        // Act
        $result = $this->workspaceService->startMcpServer($workspace);

        // Assert
        $this->assertEquals([
            'port' => 3001,
            'pid' => 12345,
            'preview_url' => 'http://localhost:3001/preview/1'
        ], $result);
    }

    public function test_stop_mcp_server_success()
    {
        // Arrange
        $workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
            'mcp_pid' => 12345
        ]);

        $this->mcpManager
            ->shouldReceive('stopServer')
            ->with($workspace)
            ->once()
            ->andReturn(true);

        // Act
        $result = $this->workspaceService->stopMcpServer($workspace);

        // Assert
        $this->assertTrue($result);
    }

    public function test_stop_mcp_server_for_non_playcanvas_workspace()
    {
        // Arrange
        $workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'unreal'
        ]);

        // Act
        $result = $this->workspaceService->stopMcpServer($workspace);

        // Assert
        $this->assertTrue($result); // Should return true for non-PlayCanvas workspaces
    }

    public function test_cleanup_workspace_success()
    {
        // Arrange
        $workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
            'mcp_pid' => 12345,
            'published_url' => 'https://example.com/published'
        ]);

        // Create multiplayer session
        MultiplayerSession::factory()->create([
            'workspace_id' => $workspace->id
        ]);

        $this->mcpManager
            ->shouldReceive('stopServer')
            ->with($workspace)
            ->once()
            ->andReturn(true);

        // Act
        $result = $this->workspaceService->cleanup($workspace);

        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseMissing('workspaces', ['id' => $workspace->id]);
        $this->assertDatabaseMissing('multiplayer_sessions', ['workspace_id' => $workspace->id]);
    }

    public function test_get_workspaces_by_engine()
    {
        // Arrange
        $playcanvasWorkspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas'
        ]);

        $unrealWorkspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'unreal'
        ]);

        // Act
        $playcanvasWorkspaces = $this->workspaceService->getWorkspacesByEngine($this->company, 'playcanvas');
        $unrealWorkspaces = $this->workspaceService->getWorkspacesByEngine($this->company, 'unreal');
        $allWorkspaces = $this->workspaceService->getWorkspacesByEngine($this->company);

        // Assert
        $this->assertInstanceOf(Collection::class, $playcanvasWorkspaces);
        $this->assertCount(1, $playcanvasWorkspaces);
        $this->assertEquals($playcanvasWorkspace->id, $playcanvasWorkspaces->first()->id);

        $this->assertCount(1, $unrealWorkspaces);
        $this->assertEquals($unrealWorkspace->id, $unrealWorkspaces->first()->id);

        $this->assertCount(2, $allWorkspaces);
    }

    public function test_get_workspaces_by_status()
    {
        // Arrange
        $readyWorkspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'ready'
        ]);

        $initializingWorkspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'initializing'
        ]);

        // Act
        $readyWorkspaces = $this->workspaceService->getWorkspacesByEngine($this->company, null, 'ready');
        $initializingWorkspaces = $this->workspaceService->getWorkspacesByEngine($this->company, null, 'initializing');

        // Assert
        $this->assertCount(1, $readyWorkspaces);
        $this->assertEquals($readyWorkspace->id, $readyWorkspaces->first()->id);

        $this->assertCount(1, $initializingWorkspaces);
        $this->assertEquals($initializingWorkspace->id, $initializingWorkspaces->first()->id);
    }

    public function test_get_workspace_stats()
    {
        // Arrange
        Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
            'status' => 'ready',
            'mcp_pid' => 12345
        ]);

        Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'unreal',
            'status' => 'published'
        ]);

        Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
            'status' => 'error'
        ]);

        // Act
        $stats = $this->workspaceService->getWorkspaceStats($this->company);

        // Assert
        $this->assertEquals(3, $stats['total']);
        $this->assertEquals(2, $stats['by_engine']['playcanvas']);
        $this->assertEquals(1, $stats['by_engine']['unreal']);
        $this->assertEquals(1, $stats['by_status']['ready']);
        $this->assertEquals(1, $stats['by_status']['published']);
        $this->assertEquals(1, $stats['by_status']['error']);
        $this->assertEquals(1, $stats['active_mcp_servers']);
    }

    public function test_update_status()
    {
        // Arrange
        $workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'initializing',
            'metadata' => ['initial' => 'data']
        ]);

        $newMetadata = ['updated' => 'value'];

        // Act
        $result = $this->workspaceService->updateStatus($workspace, 'ready', $newMetadata);

        // Assert
        $this->assertTrue($result);
        $workspace->refresh();
        $this->assertEquals('ready', $workspace->status);
        $this->assertEquals(['initial' => 'data', 'updated' => 'value'], $workspace->metadata);
    }

    public function test_cleanup_old_workspaces()
    {
        // Arrange
        $oldWorkspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
            'created_at' => now()->subHours(25) // Older than 24 hours
        ]);

        $newWorkspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
            'created_at' => now()->subHours(12) // Newer than 24 hours
        ]);

        $this->mcpManager
            ->shouldReceive('stopServer')
            ->atMost()
            ->once()
            ->andReturn(true);

        // Act
        $cleanedCount = $this->workspaceService->cleanupOldWorkspaces(24);

        // Assert
        $this->assertEquals(1, $cleanedCount);
        $this->assertDatabaseMissing('workspaces', ['id' => $oldWorkspace->id]);
        $this->assertDatabaseHas('workspaces', ['id' => $newWorkspace->id]);
    }

    public function test_restart_mcp_server()
    {
        // Arrange
        $workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
            'mcp_pid' => 12345
        ]);

        $this->mcpManager
            ->shouldReceive('stopServer')
            ->with($workspace)
            ->once()
            ->andReturn(true);

        $this->mcpManager
            ->shouldReceive('getServerStatus')
            ->with($workspace)
            ->once()
            ->andReturn('stopped');

        $this->mcpManager
            ->shouldReceive('startServer')
            ->with($workspace)
            ->once()
            ->andReturnUsing(function ($workspace) {
                $workspace->update([
                    'mcp_port' => 3002,
                    'mcp_pid' => 54321,
                    'preview_url' => 'http://localhost:3002/preview/' . $workspace->id,
                    'status' => 'ready'
                ]);
                
                return [
                    'port' => 3002,
                    'pid' => 54321,
                    'preview_url' => 'http://localhost:3002/preview/' . $workspace->id
                ];
            });

        // Act
        $result = $this->workspaceService->restartMcpServer($workspace);

        // Assert
        $this->assertEquals([
            'port' => 3002,
            'pid' => 54321,
            'preview_url' => 'http://localhost:3002/preview/' . $workspace->id
        ], $result);
    }

    public function test_restart_mcp_server_for_non_playcanvas_workspace()
    {
        // Arrange
        $workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'unreal'
        ]);

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('MCP server can only be restarted for PlayCanvas workspaces');

        $this->workspaceService->restartMcpServer($workspace);
    }

    public function test_transaction_rollback_on_failure()
    {
        // Arrange
        $templateId = 'test-template';
        $engineType = 'playcanvas';

        $this->templateRegistry
            ->shouldReceive('cloneTemplate')
            ->once()
            ->andThrow(new \Exception('Clone failed'));

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Clone failed');

        $this->workspaceService->createFromTemplate(
            $this->company,
            $templateId,
            $engineType
        );
    }

    public function test_mcp_server_failure_sets_error_status()
    {
        // Arrange
        $workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
            'status' => 'initializing'
        ]);

        $this->mcpManager
            ->shouldReceive('startServer')
            ->with($workspace)
            ->once()
            ->andThrow(new \Exception('MCP server failed to start'));

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('MCP server failed to start');

        try {
            $this->workspaceService->startMcpServer($workspace);
        } catch (\Exception $e) {
            $workspace->refresh();
            $this->assertEquals('error', $workspace->status);
            throw $e;
        }
    }
}