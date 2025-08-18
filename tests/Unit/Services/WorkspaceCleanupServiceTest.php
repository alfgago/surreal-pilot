<?php

namespace Tests\Unit\Services;

use App\Models\Workspace;
use App\Models\Company;
use App\Models\MultiplayerSession;
use App\Services\WorkspaceCleanupService;
use App\Services\MultiplayerService;
use App\Services\MultiplayerStorageService;
use App\Services\CloudFrontCleanupService;
use App\Services\EcsCleanupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;
use Mockery;

class WorkspaceCleanupServiceTest extends TestCase
{
    use RefreshDatabase;

    private WorkspaceCleanupService $service;
    private $multiplayerService;
    private $multiplayerStorageService;
    private $cloudFrontCleanupService;
    private $ecsCleanupService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->multiplayerService = Mockery::mock(MultiplayerService::class);
        $this->multiplayerStorageService = Mockery::mock(MultiplayerStorageService::class);
        $this->cloudFrontCleanupService = Mockery::mock(CloudFrontCleanupService::class);
        $this->ecsCleanupService = Mockery::mock(EcsCleanupService::class);

        $this->service = new WorkspaceCleanupService(
            $this->multiplayerService,
            $this->multiplayerStorageService,
            $this->cloudFrontCleanupService,
            $this->ecsCleanupService
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_cleanup_workspace_success()
    {
        // Arrange
        $company = Company::factory()->create();
        $workspace = Workspace::factory()->create([
            'company_id' => $company->id,
            'engine_type' => 'playcanvas',
            'mcp_pid' => 12345,
            'metadata' => [
                'latest_build_path' => 'builds/test/workspace/build1',
                'build_storage_disk' => 'local',
            ],
        ]);

        $session = MultiplayerSession::factory()->create([
            'workspace_id' => $workspace->id,
            'status' => 'active',
        ]);

        // Mock storage
        Storage::fake('local');
        Storage::disk('local')->put('workspaces/' . $company->id . '/' . $workspace->id . '/test.txt', 'content');
        Storage::disk('local')->put('builds/' . $company->id . '/' . $workspace->id . '/build.js', 'build content');

        // Mock services
        $this->multiplayerService
            ->shouldReceive('stopSession')
            ->with($session->id)
            ->once()
            ->andReturn(true);

        $this->multiplayerStorageService
            ->shouldReceive('cleanupSession')
            ->with($workspace, $session->id)
            ->once()
            ->andReturn(true);

        $this->cloudFrontCleanupService
            ->shouldReceive('cleanupWorkspacePaths')
            ->with($workspace)
            ->once()
            ->andReturn(2);

        $this->ecsCleanupService
            ->shouldReceive('cleanupWorkspaceTasks')
            ->with($workspace)
            ->once()
            ->andReturn(1);

        // Mock process for stopping MCP server
        Process::fake([
            'taskkill /PID 12345 /F' => Process::result('', '', 0),
        ]);

        // Act
        $result = $this->service->cleanupWorkspace($workspace);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['sessions_terminated']);
        $this->assertEquals(2, $result['files_cleaned']);
        $this->assertEquals(1, $result['cloudfront_paths_cleaned']);
        $this->assertEquals(1, $result['ecs_tasks_cleaned']);
        $this->assertGreaterThan(0, $result['storage_freed']);

        // Verify workspace is deleted
        $this->assertDatabaseMissing('workspaces', ['id' => $workspace->id]);

        // Verify files are cleaned up
        Storage::disk('local')->assertMissing('workspaces/' . $company->id . '/' . $workspace->id . '/test.txt');
        Storage::disk('local')->assertMissing('builds/' . $company->id . '/' . $workspace->id . '/build.js');
    }

    public function test_cleanup_workspace_with_no_multiplayer_sessions()
    {
        // Arrange
        $company = Company::factory()->create();
        $workspace = Workspace::factory()->create([
            'company_id' => $company->id,
            'engine_type' => 'playcanvas',
            'mcp_pid' => null,
        ]);

        Storage::fake('local');

        // Mock services
        $this->cloudFrontCleanupService
            ->shouldReceive('cleanupWorkspacePaths')
            ->with($workspace)
            ->once()
            ->andReturn(0);

        $this->ecsCleanupService
            ->shouldReceive('cleanupWorkspaceTasks')
            ->with($workspace)
            ->once()
            ->andReturn(0);

        // Act
        $result = $this->service->cleanupWorkspace($workspace);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['sessions_terminated']);
        $this->assertEquals(0, $result['files_cleaned']);
        $this->assertEquals(0, $result['cloudfront_paths_cleaned']);
        $this->assertEquals(0, $result['ecs_tasks_cleaned']);
    }

    public function test_cleanup_workspace_handles_service_failures()
    {
        // Arrange
        $company = Company::factory()->create();
        $workspace = Workspace::factory()->create([
            'company_id' => $company->id,
            'engine_type' => 'playcanvas',
        ]);

        $session = MultiplayerSession::factory()->create([
            'workspace_id' => $workspace->id,
            'status' => 'active',
        ]);

        Storage::fake('local');

        // Mock services to fail
        $this->multiplayerService
            ->shouldReceive('stopSession')
            ->with($session->id)
            ->once()
            ->andReturn(false);

        $this->multiplayerStorageService
            ->shouldReceive('cleanupSession')
            ->with($workspace, $session->id)
            ->once()
            ->andReturn(true);

        $this->cloudFrontCleanupService
            ->shouldReceive('cleanupWorkspacePaths')
            ->with($workspace)
            ->once()
            ->andThrow(new \Exception('CloudFront error'));

        $this->ecsCleanupService
            ->shouldReceive('cleanupWorkspaceTasks')
            ->with($workspace)
            ->once()
            ->andReturn(0);

        // Act
        $result = $this->service->cleanupWorkspace($workspace);

        // Assert
        $this->assertTrue($result['success']); // Should still succeed overall
        $this->assertEquals(0, $result['sessions_terminated']); // Session stop failed
        $this->assertEquals(0, $result['cloudfront_paths_cleaned']); // CloudFront failed
    }

    public function test_cleanup_workspace_with_build_artifacts()
    {
        // Arrange
        $company = Company::factory()->create();
        $workspace = Workspace::factory()->create([
            'company_id' => $company->id,
            'engine_type' => 'playcanvas',
            'metadata' => [
                'latest_build_path' => 'builds/test/workspace/build1',
                'build_storage_disk' => 'local',
            ],
        ]);

        Storage::fake('local');
        Storage::disk('local')->put('builds/test/workspace/build1/index.html', 'build content');
        Storage::disk('local')->put('builds/test/workspace/build1/app.js', 'js content');
        Storage::disk('local')->put('builds/' . $company->id . '/' . $workspace->id . '/extra.js', 'extra content');

        // Mock services
        $this->cloudFrontCleanupService
            ->shouldReceive('cleanupWorkspacePaths')
            ->with($workspace)
            ->once()
            ->andReturn(0);

        $this->ecsCleanupService
            ->shouldReceive('cleanupWorkspaceTasks')
            ->with($workspace)
            ->once()
            ->andReturn(0);

        // Act
        $result = $this->service->cleanupWorkspace($workspace);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(3, $result['files_cleaned']); // 2 from metadata path + 1 from builds path
        $this->assertGreaterThan(0, $result['storage_freed']);

        // Verify build files are cleaned up
        Storage::disk('local')->assertMissing('builds/test/workspace/build1/index.html');
        Storage::disk('local')->assertMissing('builds/test/workspace/build1/app.js');
        Storage::disk('local')->assertMissing('builds/' . $company->id . '/' . $workspace->id . '/extra.js');
    }

    public function test_cleanup_workspace_stops_mcp_server()
    {
        // Arrange
        $company = Company::factory()->create();
        $workspace = Workspace::factory()->create([
            'company_id' => $company->id,
            'engine_type' => 'playcanvas',
            'mcp_pid' => 54321,
        ]);

        Storage::fake('local');

        // Mock services
        $this->cloudFrontCleanupService
            ->shouldReceive('cleanupWorkspacePaths')
            ->with($workspace)
            ->once()
            ->andReturn(0);

        $this->ecsCleanupService
            ->shouldReceive('cleanupWorkspaceTasks')
            ->with($workspace)
            ->once()
            ->andReturn(0);

        // Mock process for stopping MCP server
        Process::fake([
            'taskkill /PID 54321 /F' => Process::result('', '', 0),
        ]);

        // Act
        $result = $this->service->cleanupWorkspace($workspace);

        // Assert
        $this->assertTrue($result['success']);
        
        // Verify the process was called
        Process::assertRan('taskkill /PID 54321 /F');
    }

    public function test_cleanup_workspace_handles_mcp_server_stop_failure()
    {
        // Arrange
        $company = Company::factory()->create();
        $workspace = Workspace::factory()->create([
            'company_id' => $company->id,
            'engine_type' => 'playcanvas',
            'mcp_pid' => 99999,
        ]);

        Storage::fake('local');

        // Mock services
        $this->cloudFrontCleanupService
            ->shouldReceive('cleanupWorkspacePaths')
            ->with($workspace)
            ->once()
            ->andReturn(0);

        $this->ecsCleanupService
            ->shouldReceive('cleanupWorkspaceTasks')
            ->with($workspace)
            ->once()
            ->andReturn(0);

        // Mock process failure for stopping MCP server
        Process::fake([
            'taskkill /PID 99999 /F' => Process::result('', 'Process not found', 1),
        ]);

        // Act
        $result = $this->service->cleanupWorkspace($workspace);

        // Assert
        $this->assertTrue($result['success']); // Should still succeed overall
        
        // Verify the process was called
        Process::assertRan('taskkill /PID 99999 /F');
    }

    public function test_get_cleanup_stats()
    {
        // Arrange
        $company = Company::factory()->create();
        
        // Create workspaces
        Workspace::factory()->count(3)->create(['company_id' => $company->id]);
        Workspace::factory()->count(2)->create([
            'company_id' => $company->id,
            'created_at' => now()->subHours(25), // Old workspaces
        ]);

        // Create multiplayer sessions
        $workspace = Workspace::factory()->create(['company_id' => $company->id]);
        MultiplayerSession::factory()->create([
            'workspace_id' => $workspace->id,
            'status' => 'active',
            'expires_at' => now()->addMinutes(30),
        ]);
        MultiplayerSession::factory()->create([
            'workspace_id' => $workspace->id,
            'status' => 'active',
            'expires_at' => now()->subMinutes(30), // Expired
        ]);

        // Act
        $stats = $this->service->getCleanupStats();

        // Assert
        $this->assertEquals(6, $stats['total_workspaces']);
        $this->assertEquals(2, $stats['old_workspaces']);
        $this->assertEquals(1, $stats['active_multiplayer_sessions']);
        $this->assertEquals(1, $stats['expired_multiplayer_sessions']);
    }

    public function test_cleanup_workspace_exception_handling()
    {
        // Arrange
        $company = Company::factory()->create();
        $workspace = Workspace::factory()->create([
            'company_id' => $company->id,
            'engine_type' => 'playcanvas',
        ]);

        // Mock services to throw exceptions
        $this->multiplayerService
            ->shouldReceive('stopSession')
            ->andThrow(new \Exception('Service unavailable'));

        $this->cloudFrontCleanupService
            ->shouldReceive('cleanupWorkspacePaths')
            ->with($workspace)
            ->once()
            ->andReturn(0);

        $this->ecsCleanupService
            ->shouldReceive('cleanupWorkspaceTasks')
            ->with($workspace)
            ->once()
            ->andReturn(0);

        Storage::fake('local');

        // Act
        $result = $this->service->cleanupWorkspace($workspace);

        // Assert
        $this->assertTrue($result['success']); // Should handle exceptions gracefully
        $this->assertEquals(0, $result['sessions_terminated']);
    }
}