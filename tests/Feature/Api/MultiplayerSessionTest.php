<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\MultiplayerSession;
use App\Models\User;
use App\Models\Workspace;
use App\Services\MultiplayerService;
use App\Services\MultiplayerStorageService;
use Aws\Ecs\EcsClient;
use Aws\MockHandler;
use Aws\Result;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MultiplayerSessionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Company $company;
    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->user = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'developer',
        ]);

        $this->workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
            'status' => 'ready',
        ]);

        Storage::fake('public');
    }

    public function test_can_start_multiplayer_session(): void
    {
        // Mock AWS ECS client
        $this->mockEcsClient([
            new Result([
                'tasks' => [
                    ['taskArn' => 'arn:aws:ecs:us-east-1:123456789012:task/test-task']
                ]
            ])
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/multiplayer/start', [
                'workspace_id' => $this->workspace->id,
                'max_players' => 4,
                'ttl_minutes' => 30,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Multiplayer session started successfully',
            ])
            ->assertJsonStructure([
                'data' => [
                    'session_id',
                    'session_url',
                    'expires_at',
                    'max_players',
                ]
            ]);

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
            'company_id' => $this->company->id,
            'engine_type' => 'unreal',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/multiplayer/start', [
                'workspace_id' => $unrealWorkspace->id,
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Multiplayer sessions are only supported for PlayCanvas workspaces',
            ]);
    }

    public function test_cannot_start_session_for_other_company_workspace(): void
    {
        $otherCompany = Company::factory()->create();
        $otherWorkspace = Workspace::factory()->create([
            'company_id' => $otherCompany->id,
            'engine_type' => 'playcanvas',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/multiplayer/start', [
                'workspace_id' => $otherWorkspace->id,
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Access denied to this workspace',
            ]);
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

        $this->mockEcsClient([]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/multiplayer/start', [
                'workspace_id' => $this->workspace->id,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'session_id' => $existingSession->id,
                    'session_url' => 'https://existing.ngrok.io',
                ],
            ]);
    }

    public function test_can_stop_multiplayer_session(): void
    {
        $session = MultiplayerSession::factory()->create([
            'workspace_id' => $this->workspace->id,
            'status' => 'active',
            'fargate_task_arn' => 'arn:aws:ecs:us-east-1:123456789012:task/test-task',
        ]);

        // Mock AWS ECS client for stopping task
        $this->mockEcsClient([
            new Result([]) // Empty result for stopTask
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/multiplayer/{$session->id}/stop");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Multiplayer session stopped successfully',
            ]);

        // Verify session was marked as stopped
        $this->assertDatabaseHas('multiplayer_sessions', [
            'id' => $session->id,
            'status' => 'stopped',
        ]);
    }

    public function test_can_get_session_status(): void
    {
        $session = MultiplayerSession::factory()->create([
            'workspace_id' => $this->workspace->id,
            'status' => 'active',
            'current_players' => 2,
            'max_players' => 8,
            'expires_at' => Carbon::now()->addMinutes(30),
            'session_url' => 'https://test.ngrok.io',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/multiplayer/{$session->id}/status");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'exists' => true,
                    'status' => 'active',
                    'session_url' => 'https://test.ngrok.io',
                    'current_players' => 2,
                    'max_players' => 8,
                    'can_accept_players' => true,
                ],
            ]);
    }

    public function test_can_upload_progress_file(): void
    {
        $session = MultiplayerSession::factory()->create([
            'workspace_id' => $this->workspace->id,
            'status' => 'active',
        ]);

        $file = UploadedFile::fake()->create('progress.json', 100, 'application/json');

        $response = $this->actingAs($this->user)
            ->postJson("/api/multiplayer/{$session->id}/upload", [
                'file' => $file,
                'filename' => 'game_progress.json',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Progress file uploaded successfully',
            ])
            ->assertJsonStructure([
                'data' => [
                    'path',
                    'url',
                    'filename',
                    'size',
                ]
            ]);

        // Verify file was stored
        $expectedPath = "multiplayer/company_{$this->company->id}/workspace_{$this->workspace->id}/session_{$session->id}/game_progress.json";
        Storage::disk('public')->assertExists($expectedPath);
    }

    public function test_can_list_progress_files(): void
    {
        $session = MultiplayerSession::factory()->create([
            'workspace_id' => $this->workspace->id,
            'status' => 'active',
        ]);

        // Create some test files
        $basePath = "multiplayer/company_{$this->company->id}/workspace_{$this->workspace->id}/session_{$session->id}";
        Storage::disk('public')->put("{$basePath}/progress1.json", '{"level": 1}');
        Storage::disk('public')->put("{$basePath}/progress2.json", '{"level": 2}');

        $response = $this->actingAs($this->user)
            ->getJson("/api/multiplayer/{$session->id}/files");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'count' => 2,
                ],
            ])
            ->assertJsonStructure([
                'data' => [
                    'files' => [
                        '*' => [
                            'filename',
                            'path',
                            'url',
                            'size',
                            'last_modified',
                        ]
                    ],
                    'count',
                ]
            ]);
    }

    public function test_can_download_progress_file(): void
    {
        $session = MultiplayerSession::factory()->create([
            'workspace_id' => $this->workspace->id,
            'status' => 'active',
        ]);

        // Create a test file
        $basePath = "multiplayer/company_{$this->company->id}/workspace_{$this->workspace->id}/session_{$session->id}";
        $fileContent = '{"level": 5, "score": 1000}';
        Storage::disk('public')->put("{$basePath}/game_save.json", $fileContent);

        $response = $this->actingAs($this->user)
            ->get("/api/multiplayer/{$session->id}/download/game_save.json");

        $response->assertStatus(200)
            ->assertHeader('content-disposition', 'attachment; filename=game_save.json');

        $this->assertEquals($fileContent, $response->getContent());
    }

    public function test_can_get_multiplayer_stats(): void
    {
        // Create some test sessions
        MultiplayerSession::factory()->create([
            'workspace_id' => $this->workspace->id,
            'status' => 'active',
            'expires_at' => Carbon::now()->addMinutes(30),
        ]);

        MultiplayerSession::factory()->create([
            'workspace_id' => $this->workspace->id,
            'status' => 'active',
            'expires_at' => Carbon::now()->addMinutes(15),
        ]);

        MultiplayerSession::factory()->create([
            'workspace_id' => $this->workspace->id,
            'status' => 'stopped',
            'created_at' => Carbon::today(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/multiplayer/stats');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'active_sessions' => 2,
                    'total_sessions_today' => 3,
                    'expired_sessions' => 0,
                ],
            ]);
    }

    public function test_can_get_active_sessions_for_company(): void
    {
        // Create sessions for this company
        $session1 = MultiplayerSession::factory()->create([
            'workspace_id' => $this->workspace->id,
            'status' => 'active',
            'expires_at' => Carbon::now()->addMinutes(30),
        ]);

        $workspace2 = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
        ]);

        $session2 = MultiplayerSession::factory()->create([
            'workspace_id' => $workspace2->id,
            'status' => 'active',
            'expires_at' => Carbon::now()->addMinutes(20),
        ]);

        // Create session for another company (should not be included)
        $otherCompany = Company::factory()->create();
        $otherWorkspace = Workspace::factory()->create([
            'company_id' => $otherCompany->id,
            'engine_type' => 'playcanvas',
        ]);

        MultiplayerSession::factory()->create([
            'workspace_id' => $otherWorkspace->id,
            'status' => 'active',
            'expires_at' => Carbon::now()->addMinutes(25),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/multiplayer/active');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'count' => 2,
                ],
            ])
            ->assertJsonStructure([
                'data' => [
                    'sessions' => [
                        '*' => [
                            'id',
                            'status',
                            'workspace' => [
                                'id',
                                'name',
                                'engine_type',
                            ],
                        ]
                    ],
                    'count',
                ]
            ]);
    }

    public function test_expired_session_is_automatically_stopped_on_status_check(): void
    {
        $session = MultiplayerSession::factory()->create([
            'workspace_id' => $this->workspace->id,
            'status' => 'active',
            'expires_at' => Carbon::now()->subMinutes(10), // Expired 10 minutes ago
            'fargate_task_arn' => 'arn:aws:ecs:us-east-1:123456789012:task/test-task',
        ]);

        // Mock AWS ECS client for stopping task
        $this->mockEcsClient([
            new Result([]) // Empty result for stopTask
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/multiplayer/{$session->id}/status");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'exists' => true,
                    'status' => 'stopped',
                ],
            ]);

        // Verify session was marked as stopped
        $this->assertDatabaseHas('multiplayer_sessions', [
            'id' => $session->id,
            'status' => 'stopped',
        ]);
    }

    public function test_validation_errors_for_invalid_requests(): void
    {
        // Test missing workspace_id
        $response = $this->actingAs($this->user)
            ->postJson('/api/multiplayer/start', []);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Validation failed',
            ]);

        // Test invalid max_players
        $response = $this->actingAs($this->user)
            ->postJson('/api/multiplayer/start', [
                'workspace_id' => $this->workspace->id,
                'max_players' => 1, // Too low
            ]);

        $response->assertStatus(422);

        // Test invalid ttl_minutes
        $response = $this->actingAs($this->user)
            ->postJson('/api/multiplayer/start', [
                'workspace_id' => $this->workspace->id,
                'ttl_minutes' => 5, // Too low
            ]);

        $response->assertStatus(422);
    }

    /**
     * Mock the AWS ECS client with predefined responses.
     */
    private function mockEcsClient(array $responses): void
    {
        $mock = new MockHandler($responses);
        
        $this->app->bind(EcsClient::class, function () use ($mock) {
            return new EcsClient([
                'region' => 'us-east-1',
                'version' => 'latest',
                'credentials' => [
                    'key' => 'test-key',
                    'secret' => 'test-secret',
                ],
                'handler' => $mock,
            ]);
        });
    }
}