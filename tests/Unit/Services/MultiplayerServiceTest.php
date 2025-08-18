<?php

namespace Tests\Unit\Services;

use App\Models\Company;
use App\Models\MultiplayerSession;
use App\Models\Workspace;
use App\Services\MultiplayerService;
use Aws\Ecs\EcsClient;
use Aws\MockHandler;
use Aws\Result;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiplayerServiceTest extends TestCase
{
    use RefreshDatabase;

    private MultiplayerService $service;
    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::factory()->create();
        $this->workspace = Workspace::factory()->create([
            'company_id' => $company->id,
            'engine_type' => 'playcanvas',
            'status' => 'ready',
        ]);

        $this->service = new MultiplayerService();
    }

    public function test_can_start_session_for_playcanvas_workspace(): void
    {
        $mockClient = $this->createMockEcsClient([
            new Result([
                'tasks' => [
                    ['taskArn' => 'arn:aws:ecs:us-east-1:123456789012:task/test-task']
                ]
            ])
        ]);

        $service = new MultiplayerService($mockClient);
        $result = $service->startSession($this->workspace, 4, 30);

        $this->assertArrayHasKey('session_url', $result);
        $this->assertArrayHasKey('expires_at', $result);
        $this->assertArrayHasKey('session_id', $result);

        // Verify session was created in database
        $this->assertDatabaseHas('multiplayer_sessions', [
            'workspace_id' => $this->workspace->id,
            'max_players' => 4,
            'status' => 'active',
        ]);
    }

    public function test_cannot_start_session_for_non_playcanvas_workspace(): void
    {
        $unrealWorkspace = Workspace::factory()->create([
            'engine_type' => 'unreal',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Multiplayer sessions are only supported for PlayCanvas workspaces');

        $this->service->startSession($unrealWorkspace);
    }

    public function test_returns_existing_active_session(): void
    {
        // Create an existing active session
        $existingSession = MultiplayerSession::factory()->create([
            'workspace_id' => $this->workspace->id,
            'status' => 'active',
            'expires_at' => Carbon::now()->addMinutes(30),
            'session_url' => 'https://existing.ngrok.io',
        ]);

        $result = $this->service->startSession($this->workspace);

        $this->assertEquals($existingSession->id, $result['session_id']);
        $this->assertEquals('https://existing.ngrok.io', $result['session_url']);
    }

    public function test_can_stop_active_session(): void
    {
        $session = MultiplayerSession::factory()->create([
            'workspace_id' => $this->workspace->id,
            'status' => 'active',
            'fargate_task_arn' => 'arn:aws:ecs:us-east-1:123456789012:task/test-task',
        ]);

        $mockClient = $this->createMockEcsClient([
            new Result([]) // Empty result for stopTask
        ]);

        $service = new MultiplayerService($mockClient);
        $result = $service->stopSession($session->id);

        $this->assertTrue($result);

        // Verify session was marked as stopped
        $session->refresh();
        $this->assertEquals('stopped', $session->status);
    }

    public function test_stop_session_returns_false_for_nonexistent_session(): void
    {
        $result = $this->service->stopSession('nonexistent-session-id');

        $this->assertFalse($result);
    }

    public function test_stop_session_returns_true_for_already_stopped_session(): void
    {
        $session = MultiplayerSession::factory()->create([
            'workspace_id' => $this->workspace->id,
            'status' => 'stopped',
        ]);

        $result = $this->service->stopSession($session->id);

        $this->assertTrue($result);
    }

    public function test_can_get_session_status(): void
    {
        // Use a generous future time to avoid timing issues
        $expiresAt = Carbon::now()->addHour();
        
        $session = MultiplayerSession::factory()->create([
            'workspace_id' => $this->workspace->id,
            'status' => 'active',
            'current_players' => 3,
            'max_players' => 8,
            'expires_at' => $expiresAt,
            'session_url' => 'https://test.ngrok.io',
        ]);

        $status = $this->service->getSessionStatus($session->id);

        $this->assertTrue($status['exists']);
        $this->assertEquals('active', $status['status']);
        $this->assertEquals('https://test.ngrok.io', $status['session_url']);
        $this->assertEquals(3, $status['current_players']);
        $this->assertEquals(8, $status['max_players']);
        $this->assertTrue($status['can_accept_players']);
        
        // Check that remaining time is positive (session is not expired)
        $this->assertGreaterThan(0, $status['remaining_time']);
        // Should be close to 1 hour (3600 seconds)
        $this->assertGreaterThan(3500, $status['remaining_time']);
    }

    public function test_get_status_returns_not_found_for_nonexistent_session(): void
    {
        $status = $this->service->getSessionStatus('nonexistent-session-id');

        $this->assertFalse($status['exists']);
        $this->assertEquals('not_found', $status['status']);
    }

    public function test_expired_session_is_automatically_stopped_on_status_check(): void
    {
        $session = MultiplayerSession::factory()->create([
            'workspace_id' => $this->workspace->id,
            'status' => 'active',
            'expires_at' => Carbon::now()->subMinutes(10), // Expired 10 minutes ago
            'fargate_task_arn' => 'arn:aws:ecs:us-east-1:123456789012:task/test-task',
        ]);

        $mockClient = $this->createMockEcsClient([
            new Result([]) // Empty result for stopTask
        ]);

        $service = new MultiplayerService($mockClient);
        $status = $service->getSessionStatus($session->id);

        $this->assertTrue($status['exists']);
        $this->assertEquals('stopped', $status['status']);
        $this->assertEquals(0, $status['remaining_time']);

        // Verify session was marked as stopped in database
        $session->refresh();
        $this->assertEquals('stopped', $session->status);
    }

    public function test_can_cleanup_expired_sessions(): void
    {
        // Create some expired sessions
        $expiredSession1 = MultiplayerSession::factory()->create([
            'workspace_id' => $this->workspace->id,
            'status' => 'active',
            'expires_at' => Carbon::now()->subMinutes(10),
            'fargate_task_arn' => 'arn:aws:ecs:us-east-1:123456789012:task/test-task-1',
        ]);

        $expiredSession2 = MultiplayerSession::factory()->create([
            'workspace_id' => $this->workspace->id,
            'status' => 'active',
            'expires_at' => Carbon::now()->subMinutes(5),
            'fargate_task_arn' => 'arn:aws:ecs:us-east-1:123456789012:task/test-task-2',
        ]);

        // Create an active session (should not be cleaned up)
        $activeSession = MultiplayerSession::factory()->create([
            'workspace_id' => $this->workspace->id,
            'status' => 'active',
            'expires_at' => Carbon::now()->addMinutes(30),
        ]);

        // Create an already stopped session (should not be processed)
        $stoppedSession = MultiplayerSession::factory()->create([
            'workspace_id' => $this->workspace->id,
            'status' => 'stopped',
            'expires_at' => Carbon::now()->subMinutes(15),
        ]);

        $mockClient = $this->createMockEcsClient([
            new Result([]), // For stopping task 1
            new Result([]), // For stopping task 2
        ]);

        $service = new MultiplayerService($mockClient);
        $cleanedUp = $service->cleanupExpiredSessions();

        $this->assertEquals(2, $cleanedUp);

        // Verify expired sessions were stopped
        $expiredSession1->refresh();
        $expiredSession2->refresh();
        $this->assertEquals('stopped', $expiredSession1->status);
        $this->assertEquals('stopped', $expiredSession2->status);

        // Verify active session was not affected
        $activeSession->refresh();
        $this->assertEquals('active', $activeSession->status);

        // Verify stopped session was not processed
        $stoppedSession->refresh();
        $this->assertEquals('stopped', $stoppedSession->status);
    }

    public function test_can_get_active_sessions_for_workspace(): void
    {
        // Create active sessions
        $activeSession1 = MultiplayerSession::factory()->create([
            'workspace_id' => $this->workspace->id,
            'status' => 'active',
            'expires_at' => Carbon::now()->addMinutes(30),
        ]);

        $activeSession2 = MultiplayerSession::factory()->create([
            'workspace_id' => $this->workspace->id,
            'status' => 'active',
            'expires_at' => Carbon::now()->addMinutes(20),
        ]);

        // Create stopped session (should not be included)
        MultiplayerSession::factory()->create([
            'workspace_id' => $this->workspace->id,
            'status' => 'stopped',
            'expires_at' => Carbon::now()->addMinutes(10),
        ]);

        // Create expired session (should not be included)
        MultiplayerSession::factory()->create([
            'workspace_id' => $this->workspace->id,
            'status' => 'active',
            'expires_at' => Carbon::now()->subMinutes(10),
        ]);

        $activeSessions = $this->service->getActiveSessionsForWorkspace($this->workspace);

        $this->assertCount(2, $activeSessions);
        $this->assertTrue($activeSessions->contains('id', $activeSession1->id));
        $this->assertTrue($activeSessions->contains('id', $activeSession2->id));
    }

    public function test_can_get_session_stats(): void
    {
        // Create various sessions
        MultiplayerSession::factory()->create([
            'workspace_id' => $this->workspace->id,
            'status' => 'active',
            'expires_at' => Carbon::now()->addMinutes(30),
            'created_at' => Carbon::today(),
        ]);

        MultiplayerSession::factory()->create([
            'workspace_id' => $this->workspace->id,
            'status' => 'active',
            'expires_at' => Carbon::now()->addMinutes(20),
            'created_at' => Carbon::today(),
        ]);

        MultiplayerSession::factory()->create([
            'workspace_id' => $this->workspace->id,
            'status' => 'stopped',
            'expires_at' => Carbon::now()->subMinutes(10),
            'created_at' => Carbon::today(),
        ]);

        MultiplayerSession::factory()->create([
            'workspace_id' => $this->workspace->id,
            'status' => 'active',
            'expires_at' => Carbon::now()->subMinutes(5), // Expired
            'created_at' => Carbon::today(),
        ]);

        MultiplayerSession::factory()->create([
            'workspace_id' => $this->workspace->id,
            'status' => 'active',
            'expires_at' => Carbon::now()->addMinutes(15),
            'created_at' => Carbon::yesterday(), // Not today
        ]);

        $stats = $this->service->getSessionStats();

        $this->assertEquals(3, $stats['active_sessions']); // 2 active + 1 expired but not stopped
        $this->assertEquals(4, $stats['total_sessions_today']);
        $this->assertEquals(1, $stats['expired_sessions']); // 1 expired but not stopped
    }

    public function test_handles_aws_errors_gracefully(): void
    {
        $mockClient = $this->createMockEcsClient([
            new \Aws\Exception\AwsException('ECS Error', new \Aws\Command('RunTask'))
        ]);

        $service = new MultiplayerService($mockClient);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to start multiplayer session');

        $service->startSession($this->workspace);
    }

    /**
     * Create a mock AWS ECS client with predefined responses.
     */
    private function createMockEcsClient(array $responses): EcsClient
    {
        $mock = new MockHandler($responses);
        
        return new EcsClient([
            'region' => 'us-east-1',
            'version' => 'latest',
            'credentials' => [
                'key' => 'test-key',
                'secret' => 'test-secret',
            ],
            'handler' => $mock,
        ]);
    }
}