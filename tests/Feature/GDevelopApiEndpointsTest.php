<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Company;
use App\Models\Workspace;
use App\Models\GDevelopGameSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class GDevelopApiEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Company $company;
    protected Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up test environment
        config(['gdevelop.enabled' => true]);
        config(['gdevelop.cli_path' => '/usr/local/bin/gdevelop-cli']);
        config(['gdevelop.templates_path' => storage_path('gdevelop/templates')]);
        
        // Create test user and company
        $this->user = User::factory()->create();
        $this->company = Company::factory()->create(['credits' => 1000]);
        $this->user->companies()->attach($this->company, ['role' => 'owner']);
        $this->user->update(['current_company_id' => $this->company->id]);
        
        // Create GDevelop workspace
        $this->workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'gdevelop',
            'created_by' => $this->user->id,
            'status' => 'ready'
        ]);
    }

    public function test_post_gdevelop_chat_endpoint_creates_new_game()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/gdevelop/chat", [
                'workspace_id' => $this->workspace->id,
                'message' => 'Create a simple platformer game with a player character',
                'session_id' => null
            ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'session_id',
                        'game_json',
                        'assets',
                        'preview_url',
                        'ai_response',
                        'version'
                    ],
                    'credits_used'
                ]);

        // Verify game session was created
        $this->assertDatabaseHas('g_develop_game_sessions', [
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id
        ]);

        // Verify credits were deducted
        $this->company->refresh();
        $this->assertLessThan(1000, $this->company->credits);
    }

    public function test_post_gdevelop_chat_endpoint_modifies_existing_game()
    {
        // Create initial game session
        $session = GDevelopGameSession::factory()->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'game_json' => [
                'properties' => ['name' => 'Test Game'],
                'layouts' => [],
                'objects' => []
            ],
            'version' => 1
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/gdevelop/chat", [
                'workspace_id' => $this->workspace->id,
                'message' => 'Add a jumping mechanic to the player',
                'session_id' => $session->session_id
            ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'session_id',
                        'game_json',
                        'preview_url',
                        'ai_response',
                        'version'
                    ]
                ]);

        // Verify game was modified
        $session->refresh();
        $this->assertEquals(2, $session->version);
        $this->assertNotEmpty($session->game_json);
    }

    public function test_post_gdevelop_chat_endpoint_validates_request()
    {
        // Test missing message
        $response = $this->actingAs($this->user)
            ->postJson("/api/gdevelop/chat", [
                'workspace_id' => $this->workspace->id
            ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['message']);

        // Test empty message
        $response = $this->actingAs($this->user)
            ->postJson("/api/gdevelop/chat", [
                'workspace_id' => $this->workspace->id,
                'message' => ''
            ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['message']);
    }

    public function test_post_gdevelop_chat_endpoint_handles_insufficient_credits()
    {
        // Set company credits to 0
        $this->company->update(['credits' => 0]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/gdevelop/chat", [
                'workspace_id' => $this->workspace->id,
                'message' => 'Create a game'
            ]);

        $response->assertStatus(402)
                ->assertJson([
                    'success' => false,
                    'error' => 'Insufficient credits'
                ]);
    }

    public function test_get_gdevelop_preview_endpoint_generates_preview()
    {
        // Create game session with valid game data
        $session = GDevelopGameSession::factory()->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'game_json' => [
                'properties' => [
                    'name' => 'Test Game',
                    'orientation' => 'landscape'
                ],
                'layouts' => [
                    [
                        'name' => 'MainScene',
                        'objects' => []
                    ]
                ],
                'objects' => []
            ]
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/gdevelop/preview/{$session->session_id}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'preview_url',
                        'session_id',
                        'game_title',
                        'performance' => [
                            'build_time',
                            'file_size'
                        ]
                    ]
                ]);

        // Verify preview URL was saved
        $session->refresh();
        $this->assertNotEmpty($session->preview_url);
    }

    public function test_get_gdevelop_preview_endpoint_validates_session()
    {
        // Test invalid session ID
        $response = $this->actingAs($this->user)
            ->getJson("/api/gdevelop/preview/invalid-session-id");

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                    'error' => 'Game session not found'
                ]);

        // Test session belonging to different user
        $otherUser = User::factory()->create();
        $otherSession = GDevelopGameSession::factory()->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $otherUser->id
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/gdevelop/preview/{$otherSession->session_id}");

        $response->assertStatus(403)
                ->assertJson([
                    'success' => false,
                    'error' => 'Access denied'
                ]);
    }

    public function test_get_gdevelop_preview_endpoint_handles_build_errors()
    {
        // Create session with invalid game data
        $session = GDevelopGameSession::factory()->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'game_json' => [
                'properties' => [], // Missing required properties
                'layouts' => [],
                'objects' => []
            ]
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/gdevelop/preview/{$session->session_id}");

        $response->assertStatus(422)
                ->assertJsonStructure([
                    'success',
                    'error',
                    'details' => [
                        'validation_errors',
                        'suggestions'
                    ]
                ]);
    }

    public function test_post_gdevelop_export_endpoint_creates_html5_export()
    {
        // Create game session ready for export
        $session = GDevelopGameSession::factory()->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'game_json' => [
                'properties' => [
                    'name' => 'Exportable Game',
                    'version' => '1.0.0'
                ],
                'layouts' => [],
                'objects' => []
            ]
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/gdevelop/export/{$session->session_id}", [
                'export_format' => 'html5',
                'include_assets' => true,
                'compression_level' => 'standard',
                'mobile_optimization' => false
            ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'export_id',
                        'download_url',
                        'file_size',
                        'export_format',
                        'build_time'
                    ]
                ]);

        // Verify export URL was saved
        $session->refresh();
        $this->assertNotEmpty($session->export_url);
    }

    public function test_post_gdevelop_export_endpoint_creates_mobile_export()
    {
        $session = GDevelopGameSession::factory()->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'game_json' => [
                'properties' => [
                    'name' => 'Mobile Game',
                    'orientation' => 'portrait'
                ],
                'layouts' => [],
                'objects' => []
            ]
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/gdevelop/export/{$session->session_id}", [
                'export_format' => 'cordova',
                'mobile_optimization' => true,
                'target_platform' => 'android',
                'include_assets' => true
            ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'export_id',
                        'download_url',
                        'mobile_config' => [
                            'target_platform',
                            'app_id',
                            'version'
                        ]
                    ]
                ]);
    }

    public function test_post_gdevelop_export_endpoint_validates_options()
    {
        $session = GDevelopGameSession::factory()->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id
        ]);

        // Test invalid export format
        $response = $this->actingAs($this->user)
            ->postJson("/api/gdevelop/export/{$session->session_id}", [
                'export_format' => 'invalid_format'
            ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['export_format']);

        // Test invalid compression level
        $response = $this->actingAs($this->user)
            ->postJson("/api/gdevelop/export/{$session->session_id}", [
                'export_format' => 'html5',
                'compression_level' => 'invalid_level'
            ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['compression_level']);
    }

    public function test_post_gdevelop_export_endpoint_handles_export_errors()
    {
        // Create session with problematic game data
        $session = GDevelopGameSession::factory()->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'game_json' => [
                'properties' => [
                    'name' => '', // Empty name should cause export error
                ],
                'layouts' => [],
                'objects' => []
            ]
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/gdevelop/export/{$session->session_id}", [
                'export_format' => 'html5'
            ]);

        $response->assertStatus(422)
                ->assertJsonStructure([
                    'success',
                    'error',
                    'details' => [
                        'export_errors',
                        'suggestions'
                    ]
                ]);
    }

    public function test_gdevelop_endpoints_require_authentication()
    {
        // Test chat endpoint without authentication
        $response = $this->postJson("/api/gdevelop/chat", [
            'workspace_id' => $this->workspace->id,
            'message' => 'Create a game'
        ]);

        $response->assertStatus(401);

        // Test preview endpoint without authentication
        $response = $this->getJson("/api/gdevelop/preview/test-session");

        $response->assertStatus(401);

        // Test export endpoint without authentication
        $response = $this->postJson("/api/gdevelop/export/test-session", [
            'export_format' => 'html5'
        ]);

        $response->assertStatus(401);
    }

    public function test_gdevelop_endpoints_respect_feature_flag()
    {
        // Disable GDevelop feature
        config(['gdevelop.enabled' => false]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/gdevelop/chat", [
                'workspace_id' => $this->workspace->id,
                'message' => 'Create a game'
            ]);

        $response->assertStatus(503)
                ->assertJson([
                    'error' => 'GDevelop integration is disabled',
                    'message' => 'GDevelop features are not available. Please enable GDEVELOP_ENABLED in your environment configuration.',
                    'code' => 'GDEVELOP_DISABLED'
                ]);
    }

    public function test_gdevelop_endpoints_handle_rate_limiting()
    {
        // Make multiple rapid requests to test rate limiting
        for ($i = 0; $i < 10; $i++) {
            $response = $this->actingAs($this->user)
                ->postJson("/api/gdevelop/chat", [
                    'workspace_id' => $this->workspace->id,
                    'message' => "Create game number {$i}"
                ]);

            if ($response->getStatusCode() === 429) {
                // Rate limit hit
                $response->assertStatus(429)
                        ->assertJsonStructure([
                            'success',
                            'error',
                            'retry_after'
                        ]);
                break;
            }
        }
    }

    public function test_gdevelop_chat_endpoint_handles_concurrent_requests()
    {
        $session = GDevelopGameSession::factory()->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'game_json' => [
                'properties' => ['name' => 'Concurrent Test Game'],
                'layouts' => [],
                'objects' => []
            ]
        ]);

        // Simulate concurrent modification requests
        $responses = [];
        for ($i = 0; $i < 3; $i++) {
            $responses[] = $this->actingAs($this->user)
                ->postJson("/api/gdevelop/chat", [
                    'workspace_id' => $this->workspace->id,
                    'message' => "Concurrent modification {$i}",
                    'session_id' => $session->session_id
                ]);
        }

        // At least one should succeed
        $successCount = 0;
        foreach ($responses as $response) {
            if ($response->getStatusCode() === 200) {
                $successCount++;
            }
        }

        $this->assertGreaterThan(0, $successCount);
    }

    public function test_gdevelop_preview_endpoint_caches_results()
    {
        $session = GDevelopGameSession::factory()->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'game_json' => [
                'properties' => ['name' => 'Cache Test Game'],
                'layouts' => [],
                'objects' => []
            ]
        ]);

        // First request
        $startTime = microtime(true);
        $response1 = $this->actingAs($this->user)
            ->getJson("/api/gdevelop/preview/{$session->session_id}");
        $firstRequestTime = microtime(true) - $startTime;

        $response1->assertStatus(200);

        // Second request (should be faster due to caching)
        $startTime = microtime(true);
        $response2 = $this->actingAs($this->user)
            ->getJson("/api/gdevelop/preview/{$session->session_id}");
        $secondRequestTime = microtime(true) - $startTime;

        $response2->assertStatus(200);

        // Verify same preview URL is returned
        $this->assertEquals(
            $response1->json('data.preview_url'),
            $response2->json('data.preview_url')
        );

        // Second request should be significantly faster (cached)
        $this->assertLessThan($firstRequestTime * 0.5, $secondRequestTime);
    }

    public function test_gdevelop_export_endpoint_supports_batch_operations()
    {
        $sessions = [];
        for ($i = 0; $i < 3; $i++) {
            $sessions[] = GDevelopGameSession::factory()->create([
                'workspace_id' => $this->workspace->id,
                'user_id' => $this->user->id,
                'game_json' => [
                    'properties' => ['name' => "Batch Game {$i}"],
                    'layouts' => [],
                    'objects' => []
                ]
            ]);
        }

        // Test batch export request
        $sessionIds = array_map(fn($s) => $s->session_id, $sessions);
        
        $response = $this->actingAs($this->user)
            ->postJson("/api/gdevelop/export/batch", [
                'session_ids' => $sessionIds,
                'export_format' => 'html5',
                'compression_level' => 'standard'
            ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'batch_id',
                        'exports' => [
                            '*' => [
                                'session_id',
                                'status',
                                'download_url'
                            ]
                        ]
                    ]
                ]);
    }

    public function test_gdevelop_endpoints_log_performance_metrics()
    {
        $session = GDevelopGameSession::factory()->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'game_json' => [
                'properties' => ['name' => 'Performance Test Game'],
                'layouts' => [],
                'objects' => []
            ]
        ]);

        // Enable performance logging
        config(['gdevelop.log_performance' => true]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/gdevelop/preview/{$session->session_id}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'preview_url',
                        'performance' => [
                            'build_time',
                            'file_size',
                            'memory_usage'
                        ]
                    ]
                ]);

        // Verify performance metrics are reasonable
        $performance = $response->json('data.performance');
        $this->assertIsNumeric($performance['build_time']);
        $this->assertGreaterThan(0, $performance['build_time']);
        $this->assertIsNumeric($performance['file_size']);
        $this->assertGreaterThan(0, $performance['file_size']);
    }

    public function test_gdevelop_endpoints_handle_workspace_permissions()
    {
        // Create workspace owned by different company
        $otherCompany = Company::factory()->create();
        $otherWorkspace = Workspace::factory()->create([
            'company_id' => $otherCompany->id,
            'engine_type' => 'gdevelop'
        ]);

        // Try to access other company's workspace
        $response = $this->actingAs($this->user)
            ->postJson("/api/gdevelop/chat", [
                'workspace_id' => $otherWorkspace->id,
                'message' => 'Create a game'
            ]);

        $response->assertStatus(403)
                ->assertJson([
                    'success' => false,
                    'error' => 'Access denied to workspace'
                ]);
    }

    public function test_gdevelop_endpoints_validate_engine_type()
    {
        // Create non-GDevelop workspace
        $unrealWorkspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'unreal'
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/gdevelop/chat", [
                'workspace_id' => $unrealWorkspace->id,
                'message' => 'Create a game'
            ]);

        $response->assertStatus(422)
                ->assertJson([
                    'success' => false,
                    'error' => 'Workspace must use GDevelop engine'
                ]);
    }
}