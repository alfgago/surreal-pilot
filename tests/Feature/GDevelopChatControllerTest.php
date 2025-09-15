<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use App\Models\Workspace;
use App\Models\GDevelopGameSession;
use App\Services\GDevelopGameService;
use App\Services\GDevelopRuntimeService;
use App\Services\GDevelopSessionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Mockery;

class GDevelopChatControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Company $company;
    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user and company
        $this->company = Company::factory()->create();
        $this->user = User::factory()->create(['current_company_id' => $this->company->id]);
        
        // Attach user to company via pivot table
        $this->user->companies()->attach($this->company);
        
        $this->workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'created_by' => $this->user->id,
            'engine_type' => 'gdevelop'
        ]);

        // Ensure storage directories exist
        Storage::makeDirectory('gdevelop/sessions');
        Storage::makeDirectory('gdevelop/exports');
    }

    public function test_chat_endpoint_creates_new_game(): void
    {
        // Mock the game service to return a successful game creation
        $mockGameService = Mockery::mock(GDevelopGameService::class);
        $mockGameService->shouldReceive('getGameData')
            ->with(Mockery::type('string'))
            ->andReturn(null);
        
        $mockGameService->shouldReceive('createGame')
            ->with(
                Mockery::type('string'),
                'Make a simple platformer game',
                null,
                Mockery::type('array')
            )
            ->andReturn([
                'session_id' => 'test-session-id',
                'game_json' => [
                    'properties' => ['name' => 'Test Platformer Game'],
                    'objects' => [],
                    'layouts' => []
                ],
                'assets_manifest' => [],
                'version' => 1,
                'last_modified' => now(),
                'storage_path' => 'gdevelop/sessions/test-session-id'
            ]);

        $this->app->instance(GDevelopGameService::class, $mockGameService);

        $response = $this->actingAs($this->user)
            ->postJson('/api/gdevelop/chat', [
                'message' => 'Make a simple platformer game',
                'workspace_id' => $this->workspace->id
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Game created successfully'
            ])
            ->assertJsonStructure([
                'success',
                'session_id',
                'game_data',
                'preview_url',
                'message',
                'actions' => [
                    'preview' => ['available', 'url'],
                    'export' => ['available', 'url']
                ]
            ]);
    }

    public function test_chat_endpoint_modifies_existing_game(): void
    {
        $sessionId = '550e8400-e29b-41d4-a716-446655440000'; // Valid UUID
        
        // Mock the game service to return existing game data
        $mockGameService = Mockery::mock(GDevelopGameService::class);
        $mockGameService->shouldReceive('getGameData')
            ->with($sessionId)
            ->andReturn([
                'session_id' => $sessionId,
                'game_json' => [
                    'properties' => ['name' => 'Existing Game'],
                    'objects' => [],
                    'layouts' => []
                ],
                'assets_manifest' => [],
                'version' => 1
            ]);
        
        $mockGameService->shouldReceive('modifyGame')
            ->with(
                $sessionId,
                'Add a new enemy type',
                Mockery::type('array')
            )
            ->andReturn([
                'session_id' => $sessionId,
                'game_json' => [
                    'properties' => ['name' => 'Existing Game'],
                    'objects' => [['name' => 'Enemy']],
                    'layouts' => []
                ],
                'assets_manifest' => [],
                'version' => 2,
                'last_modified' => now(),
                'storage_path' => "gdevelop/sessions/{$sessionId}"
            ]);

        $this->app->instance(GDevelopGameService::class, $mockGameService);

        $response = $this->actingAs($this->user)
            ->postJson('/api/gdevelop/chat', [
                'message' => 'Add a new enemy type',
                'session_id' => $sessionId,
                'workspace_id' => $this->workspace->id
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'session_id' => $sessionId,
                'message' => 'Game modified successfully'
            ]);
    }

    public function test_preview_endpoint_generates_preview(): void
    {
        // Mock the services
        $mockGameService = Mockery::mock(GDevelopGameService::class);
        $mockGameService->shouldReceive('getGameData')
            ->with('test-session-id')
            ->andReturn([
                'session_id' => 'test-session-id',
                'game_json' => [
                    'properties' => ['name' => 'Test Game'],
                    'objects' => [],
                    'layouts' => []
                ],
                'assets_manifest' => []
            ]);

        $mockPreviewService = Mockery::mock(\App\Services\GDevelopPreviewService::class);
        $mockPreviewService->shouldReceive('generatePreview')
            ->with('test-session-id', Mockery::type('array'))
            ->andReturn(new \App\Services\PreviewGenerationResult(
                success: true,
                previewUrl: '/gdevelop/preview/test-session-id/serve',
                previewPath: '/path/to/preview',
                indexPath: '/path/to/preview/index.html',
                error: null,
                buildTime: time(),
                cached: false
            ));

        $this->app->instance(GDevelopGameService::class, $mockGameService);
        $this->app->instance(\App\Services\GDevelopPreviewService::class, $mockPreviewService);

        $response = $this->actingAs($this->user)
            ->getJson('/api/gdevelop/preview/test-session-id');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'session_id' => 'test-session-id',
                'message' => 'Preview generated successfully'
            ])
            ->assertJsonStructure([
                'success',
                'session_id',
                'preview_url',
                'preview_path',
                'index_path',
                'build_time',
                'cached',
                'message'
            ]);
    }

    public function test_export_endpoint_generates_export(): void
    {
        // Mock the services
        $mockGameService = Mockery::mock(GDevelopGameService::class);
        $mockGameService->shouldReceive('getGameData')
            ->with('test-session-id')
            ->andReturn([
                'session_id' => 'test-session-id',
                'game_json' => [
                    'properties' => ['name' => 'Test Game'],
                    'objects' => [],
                    'layouts' => []
                ],
                'assets_manifest' => []
            ]);

        $mockRuntimeService = Mockery::mock(GDevelopRuntimeService::class);
        $mockRuntimeService->shouldReceive('buildExport')
            ->with('test-session-id', Mockery::type('string'), Mockery::type('array'))
            ->andReturn(new \App\Services\ExportResult(
                success: true,
                exportPath: '/path/to/export',
                zipPath: '/path/to/export.zip',
                downloadUrl: '/download/test-session-id',
                error: null,
                buildTime: time()
            ));

        $this->app->instance(GDevelopGameService::class, $mockGameService);
        $this->app->instance(GDevelopRuntimeService::class, $mockRuntimeService);

        $response = $this->actingAs($this->user)
            ->postJson('/api/gdevelop/export/test-session-id', [
                'minify' => true,
                'mobile_optimized' => false,
                'compression_level' => 'standard'
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'session_id' => 'test-session-id',
                'message' => 'Export generated successfully'
            ])
            ->assertJsonStructure([
                'success',
                'session_id',
                'download_url',
                'export_path',
                'zip_path',
                'build_time',
                'options',
                'message'
            ]);
    }

    public function test_chat_request_validation(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/gdevelop/chat', [
                // Missing required 'message' field
                'workspace_id' => $this->workspace->id
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['message']);
    }

    public function test_export_request_validation(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/gdevelop/export/test-session-id', [
                'compression_level' => 'invalid_level'
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['compression_level']);
    }

    public function test_preview_endpoint_handles_missing_session(): void
    {
        $mockGameService = Mockery::mock(GDevelopGameService::class);
        $mockGameService->shouldReceive('getGameData')
            ->with('nonexistent-session-id')
            ->andReturn(null);

        $this->app->instance(GDevelopGameService::class, $mockGameService);

        $response = $this->actingAs($this->user)
            ->getJson('/api/gdevelop/preview/nonexistent-session-id');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error' => 'Game session not found'
            ]);
    }

    public function test_export_endpoint_handles_missing_session(): void
    {
        $mockGameService = Mockery::mock(GDevelopGameService::class);
        $mockGameService->shouldReceive('getGameData')
            ->with('nonexistent-session-id')
            ->andReturn(null);

        $this->app->instance(GDevelopGameService::class, $mockGameService);

        $response = $this->actingAs($this->user)
            ->postJson('/api/gdevelop/export/nonexistent-session-id');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error' => 'Game session not found'
            ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}