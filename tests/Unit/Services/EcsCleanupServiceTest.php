<?php

namespace Tests\Unit\Services;

use App\Models\Workspace;
use App\Models\Company;
use App\Models\MultiplayerSession;
use App\Services\EcsCleanupService;
use Aws\Ecs\EcsClient;
use Aws\Exception\AwsException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Mockery;

class EcsCleanupServiceTest extends TestCase
{
    use RefreshDatabase;

    private EcsCleanupService $service;
    private $ecsClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ecsClient = Mockery::mock(EcsClient::class);
        $this->service = new EcsCleanupService($this->ecsClient);

        // Mock configuration
        config(['multiplayer.ecs_cluster' => 'test-cluster']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_cleanup_workspace_tasks_success()
    {
        // Arrange
        $company = Company::factory()->create();
        $workspace = Workspace::factory()->create(['company_id' => $company->id]);

        $session1 = MultiplayerSession::factory()->create([
            'workspace_id' => $workspace->id,
            'fargate_task_arn' => 'arn:aws:ecs:us-east-1:123456789012:task/test-cluster/task1',
        ]);

        $session2 = MultiplayerSession::factory()->create([
            'workspace_id' => $workspace->id,
            'fargate_task_arn' => 'arn:aws:ecs:us-east-1:123456789012:task/test-cluster/task2',
        ]);

        // Mock stopping tasks
        $this->ecsClient
            ->shouldReceive('stopTask')
            ->twice()
            ->andReturn(['task' => ['taskArn' => 'task1']]);

        // Mock finding orphaned tasks
        $this->ecsClient
            ->shouldReceive('listTasks')
            ->once()
            ->with([
                'cluster' => 'test-cluster',
                'desiredStatus' => 'RUNNING',
            ])
            ->andReturn([
                'taskArns' => ['arn:aws:ecs:us-east-1:123456789012:task/test-cluster/orphaned-task'],
            ]);

        $this->ecsClient
            ->shouldReceive('describeTasks')
            ->once()
            ->with([
                'cluster' => 'test-cluster',
                'tasks' => ['arn:aws:ecs:us-east-1:123456789012:task/test-cluster/orphaned-task'],
                'include' => ['TAGS'],
            ])
            ->andReturn([
                'tasks' => [
                    [
                        'taskArn' => 'arn:aws:ecs:us-east-1:123456789012:task/test-cluster/orphaned-task',
                        'tags' => [
                            ['key' => 'WorkspaceId', 'value' => (string) $workspace->id],
                        ],
                    ],
                ],
            ]);

        // Mock stopping orphaned task
        $this->ecsClient
            ->shouldReceive('stopTask')
            ->once()
            ->with([
                'cluster' => 'test-cluster',
                'task' => 'arn:aws:ecs:us-east-1:123456789012:task/test-cluster/orphaned-task',
                'reason' => "Orphaned task cleanup for workspace {$workspace->id}",
            ])
            ->andReturn(['task' => ['taskArn' => 'orphaned-task']]);

        // Act
        $result = $this->service->cleanupWorkspaceTasks($workspace);

        // Assert
        $this->assertEquals(3, $result); // 2 session tasks + 1 orphaned task
    }

    public function test_cleanup_workspace_tasks_no_sessions()
    {
        // Arrange
        $company = Company::factory()->create();
        $workspace = Workspace::factory()->create(['company_id' => $company->id]);

        // Mock finding no orphaned tasks
        $this->ecsClient
            ->shouldReceive('listTasks')
            ->once()
            ->andReturn(['taskArns' => []]);

        // Act
        $result = $this->service->cleanupWorkspaceTasks($workspace);

        // Assert
        $this->assertEquals(0, $result);
    }

    public function test_cleanup_workspace_tasks_stop_task_failure()
    {
        // Arrange
        $company = Company::factory()->create();
        $workspace = Workspace::factory()->create(['company_id' => $company->id]);

        $session = MultiplayerSession::factory()->create([
            'workspace_id' => $workspace->id,
            'fargate_task_arn' => 'arn:aws:ecs:us-east-1:123456789012:task/test-cluster/task1',
        ]);

        // Mock task stop failure
        $this->ecsClient
            ->shouldReceive('stopTask')
            ->once()
            ->andThrow(new AwsException('Task not found', Mockery::mock('Aws\Command\CommandInterface')));

        // Mock finding no orphaned tasks
        $this->ecsClient
            ->shouldReceive('listTasks')
            ->once()
            ->andReturn(['taskArns' => []]);

        // Act
        $result = $this->service->cleanupWorkspaceTasks($workspace);

        // Assert
        $this->assertEquals(0, $result); // Failed to stop task
    }

    public function test_cleanup_orphaned_tasks()
    {
        // Arrange
        $runningTasks = [
            'arn:aws:ecs:us-east-1:123456789012:task/test-cluster/task1',
            'arn:aws:ecs:us-east-1:123456789012:task/test-cluster/task2',
        ];

        $this->ecsClient
            ->shouldReceive('listTasks')
            ->once()
            ->with([
                'cluster' => 'test-cluster',
                'desiredStatus' => 'RUNNING',
            ])
            ->andReturn(['taskArns' => $runningTasks]);

        // Mock that both tasks are orphaned (no active sessions)
        // This would be tested by checking the database, which we'll simulate

        // Mock stopping both tasks
        $this->ecsClient
            ->shouldReceive('stopTask')
            ->twice()
            ->andReturn(['task' => ['taskArn' => 'task']]);

        // Act
        $result = $this->service->cleanupOrphanedTasks();

        // Assert
        $this->assertEquals(2, $result);
    }

    public function test_cleanup_expired_session_tasks()
    {
        // Arrange
        $company = Company::factory()->create();
        $workspace = Workspace::factory()->create(['company_id' => $company->id]);

        $expiredSession = MultiplayerSession::factory()->create([
            'workspace_id' => $workspace->id,
            'fargate_task_arn' => 'arn:aws:ecs:us-east-1:123456789012:task/test-cluster/expired-task',
            'status' => 'active',
            'expires_at' => now()->subMinutes(30), // Expired
        ]);

        // Mock stopping the expired task
        $this->ecsClient
            ->shouldReceive('stopTask')
            ->once()
            ->with([
                'cluster' => 'test-cluster',
                'task' => 'arn:aws:ecs:us-east-1:123456789012:task/test-cluster/expired-task',
                'reason' => 'Expired session cleanup',
            ])
            ->andReturn(['task' => ['taskArn' => 'expired-task']]);

        // Act
        $result = $this->service->cleanupExpiredSessionTasks();

        // Assert
        $this->assertEquals(1, $result);
        
        // Verify session status was updated
        $expiredSession->refresh();
        $this->assertEquals('stopped', $expiredSession->status);
    }

    public function test_cleanup_expired_session_tasks_already_stopped()
    {
        // Arrange
        $company = Company::factory()->create();
        $workspace = Workspace::factory()->create(['company_id' => $company->id]);

        MultiplayerSession::factory()->create([
            'workspace_id' => $workspace->id,
            'fargate_task_arn' => 'arn:aws:ecs:us-east-1:123456789012:task/test-cluster/stopped-task',
            'status' => 'stopped', // Already stopped
            'expires_at' => now()->subMinutes(30),
        ]);

        // Act
        $result = $this->service->cleanupExpiredSessionTasks();

        // Assert
        $this->assertEquals(0, $result); // No tasks to clean up
    }

    public function test_stop_task_with_invalid_parameter_exception()
    {
        // Arrange
        $company = Company::factory()->create();
        $workspace = Workspace::factory()->create(['company_id' => $company->id]);

        $session = MultiplayerSession::factory()->create([
            'workspace_id' => $workspace->id,
            'fargate_task_arn' => 'arn:aws:ecs:us-east-1:123456789012:task/test-cluster/task1',
        ]);

        // Mock InvalidParameterException (task already stopped)
        $exception = Mockery::mock(AwsException::class);
        $exception->shouldReceive('getAwsErrorCode')->andReturn('InvalidParameterException');

        $this->ecsClient
            ->shouldReceive('stopTask')
            ->once()
            ->andThrow($exception);

        // Mock finding no orphaned tasks
        $this->ecsClient
            ->shouldReceive('listTasks')
            ->once()
            ->andReturn(['taskArns' => []]);

        // Act
        $result = $this->service->cleanupWorkspaceTasks($workspace);

        // Assert
        $this->assertEquals(1, $result); // Should count as successful cleanup
    }

    public function test_get_cluster_stats_success()
    {
        // Arrange
        $this->ecsClient
            ->shouldReceive('describeClusters')
            ->once()
            ->with([
                'clusters' => ['test-cluster'],
                'include' => ['STATISTICS'],
            ])
            ->andReturn([
                'clusters' => [
                    [
                        'clusterName' => 'test-cluster',
                        'status' => 'ACTIVE',
                        'registeredContainerInstancesCount' => 2,
                        'statistics' => [
                            ['name' => 'runningTasksCount', 'value' => '5'],
                            ['name' => 'pendingTasksCount', 'value' => '2'],
                            ['name' => 'activeServicesCount', 'value' => '3'],
                        ],
                    ],
                ],
            ]);

        // Act
        $result = $this->service->getClusterStats();

        // Assert
        $this->assertEquals([
            'cluster_exists' => true,
            'cluster_name' => 'test-cluster',
            'status' => 'ACTIVE',
            'running_tasks' => 5,
            'pending_tasks' => 2,
            'active_services' => 3,
            'registered_container_instances' => 2,
        ], $result);
    }

    public function test_get_cluster_stats_cluster_not_found()
    {
        // Arrange
        $this->ecsClient
            ->shouldReceive('describeClusters')
            ->once()
            ->andReturn(['clusters' => []]);

        // Act
        $result = $this->service->getClusterStats();

        // Assert
        $this->assertEquals([
            'cluster_exists' => false,
            'running_tasks' => 0,
            'pending_tasks' => 0,
            'active_services' => 0,
        ], $result);
    }

    public function test_get_cluster_stats_aws_exception()
    {
        // Arrange
        $this->ecsClient
            ->shouldReceive('describeClusters')
            ->once()
            ->andThrow(new AwsException('Access denied', Mockery::mock('Aws\Command\CommandInterface')));

        // Act
        $result = $this->service->getClusterStats();

        // Assert
        $this->assertArrayHasKey('cluster_exists', $result);
        $this->assertFalse($result['cluster_exists']);
        $this->assertArrayHasKey('error', $result);
        $this->assertEquals(0, $result['running_tasks']);
    }

    public function test_is_enabled_with_cluster_name()
    {
        // Act & Assert
        $this->assertTrue($this->service->isEnabled());
    }

    public function test_is_enabled_without_cluster_name()
    {
        // Arrange
        config(['multiplayer.ecs_cluster' => '']);

        // Act & Assert
        $this->assertFalse($this->service->isEnabled());
    }

    public function test_find_orphaned_tasks_for_workspace()
    {
        // Arrange
        $company = Company::factory()->create();
        $workspace = Workspace::factory()->create(['company_id' => $company->id]);

        $runningTasks = [
            'arn:aws:ecs:us-east-1:123456789012:task/test-cluster/task1',
            'arn:aws:ecs:us-east-1:123456789012:task/test-cluster/task2',
        ];

        $this->ecsClient
            ->shouldReceive('listTasks')
            ->once()
            ->andReturn(['taskArns' => $runningTasks]);

        $this->ecsClient
            ->shouldReceive('describeTasks')
            ->once()
            ->with([
                'cluster' => 'test-cluster',
                'tasks' => $runningTasks,
                'include' => ['TAGS'],
            ])
            ->andReturn([
                'tasks' => [
                    [
                        'taskArn' => 'arn:aws:ecs:us-east-1:123456789012:task/test-cluster/task1',
                        'tags' => [
                            ['key' => 'WorkspaceId', 'value' => (string) $workspace->id],
                        ],
                    ],
                    [
                        'taskArn' => 'arn:aws:ecs:us-east-1:123456789012:task/test-cluster/task2',
                        'tags' => [
                            ['key' => 'WorkspaceId', 'value' => '999'], // Different workspace
                        ],
                    ],
                ],
            ]);

        // Mock stopping the orphaned task
        $this->ecsClient
            ->shouldReceive('stopTask')
            ->once()
            ->andReturn(['task' => ['taskArn' => 'task1']]);

        // Act
        $result = $this->service->cleanupWorkspaceTasks($workspace);

        // Assert
        $this->assertEquals(1, $result); // Only task1 should be cleaned up
    }
}