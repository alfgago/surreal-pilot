<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use App\Models\Workspace;
use App\Services\FeatureFlagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class GDevelopIntegrationValidationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Company $company;
    protected Workspace $workspace;

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
    }

    /** @test */
    public function gdevelop_feature_flags_are_properly_configured()
    {
        $featureFlagService = app(FeatureFlagService::class);

        // Test GDevelop is enabled
        $this->assertTrue($featureFlagService->isGDevelopEnabled());
        $this->assertFalse($featureFlagService->isPlayCanvasEnabled());
        $this->assertEquals('gdevelop', $featureFlagService->getPrimaryEngine());

        // Test enabled engines
        $enabledEngines = $featureFlagService->getEnabledEngines();
        $this->assertContains('gdevelop', $enabledEngines);
        $this->assertNotContains('playcanvas', $enabledEngines);

        // Test configuration summary
        $configSummary = $featureFlagService->getEngineConfigurationSummary();
        $this->assertTrue($configSummary['gdevelop']['enabled']);
        $this->assertFalse($configSummary['playcanvas']['enabled']);
        $this->assertEquals('gdevelop', $configSummary['primary_engine']);

        // Test configuration validation
        $validation = $featureFlagService->validateEngineConfiguration();
        $this->assertTrue($validation['valid']);
        $this->assertEmpty($validation['issues']);
    }

    /** @test */
    public function gdevelop_middleware_enforces_feature_flags()
    {
        $this->actingAs($this->user);

        // Test with GDevelop enabled
        $response = $this->getJson('/api/gdevelop/session/test-session');
        $this->assertNotEquals(503, $response->getStatusCode()); // Should not be service unavailable

        // Test with GDevelop disabled
        Config::set('gdevelop.enabled', false);
        
        $response = $this->getJson('/api/gdevelop/session/test-session');
        $response->assertStatus(503);
        $response->assertJson([
            'error' => 'GDevelop integration is disabled'
        ]);
    }

    /** @test */
    public function workspace_creation_supports_gdevelop_engine()
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

    /** @test */
    public function gdevelop_routes_are_properly_registered()
    {
        $this->actingAs($this->user);

        // Test main GDevelop routes exist and return proper responses
        $routes = [
            '/api/gdevelop/session/test-session' => 'GET',
            '/api/gdevelop/chat' => 'POST',
            '/api/gdevelop/preview/test-session' => 'GET',
            '/api/gdevelop/export/test-session' => 'POST',
        ];

        foreach ($routes as $route => $method) {
            if ($method === 'GET') {
                $response = $this->getJson($route);
            } else {
                $response = $this->postJson($route, [
                    'session_id' => 'test-session',
                    'workspace_id' => $this->workspace->id,
                    'message' => 'Test message'
                ]);
            }

            // Routes should exist (not 404) and be accessible (not 503 when enabled)
            $this->assertNotEquals(404, $response->getStatusCode(), "Route {$route} should exist");
            $this->assertNotEquals(503, $response->getStatusCode(), "Route {$route} should be accessible when GDevelop is enabled");
        }
    }

    /** @test */
    public function gdevelop_configuration_is_valid()
    {
        // Test configuration values are set
        $this->assertTrue(config('gdevelop.enabled'));
        $this->assertTrue(config('gdevelop.engines.gdevelop_enabled'));
        $this->assertFalse(config('gdevelop.engines.playcanvas_enabled'));

        // Test required configuration keys exist
        $requiredKeys = [
            'gdevelop.cli_path',
            'gdevelop.templates_path',
            'gdevelop.sessions_path',
            'gdevelop.exports_path',
            'gdevelop.build_timeout',
            'gdevelop.preview_timeout',
            'gdevelop.max_concurrent_builds'
        ];

        foreach ($requiredKeys as $key) {
            $this->assertNotNull(config($key), "Configuration key {$key} should be set");
        }

        // Test feature flags are properly configured
        $features = config('gdevelop.features');
        $this->assertIsArray($features);
        $this->assertArrayHasKey('preview_generation', $features);
        $this->assertArrayHasKey('export_generation', $features);
        $this->assertArrayHasKey('ai_integration', $features);
    }

    /** @test */
    public function gdevelop_services_are_properly_registered()
    {
        // Test that all required GDevelop services can be resolved
        $services = [
            \App\Services\GDevelopGameService::class,
            \App\Services\GDevelopRuntimeService::class,
            \App\Services\GDevelopPreviewService::class,
            \App\Services\GDevelopExportService::class,
            \App\Services\GDevelopSessionManager::class,
            \App\Services\GDevelopErrorRecoveryService::class,
            \App\Services\FeatureFlagService::class,
        ];

        foreach ($services as $service) {
            $instance = app($service);
            $this->assertInstanceOf($service, $instance, "Service {$service} should be resolvable");
        }
    }

    /** @test */
    public function gdevelop_models_are_properly_configured()
    {
        // Test GDevelopGameSession model
        $gameSession = new \App\Models\GDevelopGameSession();
        
        $this->assertContains('workspace_id', $gameSession->getFillable());
        $this->assertContains('user_id', $gameSession->getFillable());
        $this->assertContains('session_id', $gameSession->getFillable());
        $this->assertContains('game_json', $gameSession->getFillable());

        // Test model relationships
        $this->assertTrue(method_exists($gameSession, 'workspace'));
        $this->assertTrue(method_exists($gameSession, 'user'));

        // Test workspace has GDevelop relationship
        $this->assertTrue(method_exists($this->workspace, 'gdevelopGameSessions'));
        $this->assertTrue(method_exists($this->workspace, 'isGDevelop'));
        $this->assertTrue($this->workspace->isGDevelop());
    }

    /** @test */
    public function authentication_is_required_for_gdevelop_endpoints()
    {
        // Test unauthenticated access is rejected
        $response = $this->postJson('/api/gdevelop/chat', [
            'session_id' => 'unauth-test',
            'workspace_id' => $this->workspace->id,
            'message' => 'Create a game'
        ]);
        
        $response->assertStatus(401);

        // Test authenticated access works
        $this->actingAs($this->user);
        
        $response = $this->postJson('/api/gdevelop/chat', [
            'session_id' => 'auth-test',
            'workspace_id' => $this->workspace->id,
            'message' => 'Create a game'
        ]);
        
        // Should not be unauthorized (may fail for other reasons but not auth)
        $this->assertNotEquals(401, $response->getStatusCode());
    }

    /** @test */
    public function gdevelop_frontend_components_are_integrated()
    {
        // Test that the frontend files exist
        $frontendFiles = [
            'resources/js/components/gdevelop/GDevelopChatInterface.tsx',
            'resources/js/components/gdevelop/GDevelopPreview.tsx',
            'resources/js/components/gdevelop/GDevelopExport.tsx',
        ];

        foreach ($frontendFiles as $file) {
            $this->assertFileExists(base_path($file), "Frontend file {$file} should exist");
        }

        // Test that GDevelop is included in workspace creation
        $createWorkspaceFile = base_path('resources/js/Pages/Workspaces/Create.tsx');
        $this->assertFileExists($createWorkspaceFile);
        
        $content = file_get_contents($createWorkspaceFile);
        $this->assertStringContainsString('gdevelop', $content, 'Workspace creation should include GDevelop option');

        // Test that GDevelop is included in chat interface
        $chatFile = base_path('resources/js/Pages/Chat.tsx');
        $this->assertFileExists($chatFile);
        
        $chatContent = file_get_contents($chatFile);
        $this->assertStringContainsString('GDevelopChatInterface', $chatContent, 'Chat page should include GDevelop interface');
    }

    /** @test */
    public function error_handling_is_properly_configured()
    {
        $this->actingAs($this->user);

        // Test with invalid session ID
        $response = $this->getJson('/api/gdevelop/session/invalid-session-id');
        $response->assertStatus(404);
        
        $data = $response->json();
        $this->assertArrayHasKey('success', $data);
        $this->assertFalse($data['success']);

        // Test with insufficient credits
        $this->company->update(['credits' => 0]);
        
        $response = $this->postJson('/api/gdevelop/chat', [
            'session_id' => 'insufficient-credits-test',
            'workspace_id' => $this->workspace->id,
            'message' => 'Create a complex game'
        ]);

        $response->assertStatus(402);
        $data = $response->json();
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('Insufficient credits', $data['error']);
    }

    /** @test */
    public function performance_optimizations_are_enabled()
    {
        // Test that performance features are configured
        $performanceConfig = config('gdevelop.performance');
        $this->assertIsArray($performanceConfig);
        
        $this->assertTrue($performanceConfig['cache_enabled']);
        $this->assertTrue($performanceConfig['process_pool_enabled']);
        $this->assertTrue($performanceConfig['async_processing_enabled']);
        $this->assertTrue($performanceConfig['monitoring_enabled']);

        // Test that performance services can be resolved
        $performanceServices = [
            \App\Services\GDevelopCacheService::class,
            \App\Services\GDevelopProcessPoolService::class,
            \App\Services\GDevelopAsyncProcessingService::class,
            \App\Services\GDevelopPerformanceMonitorService::class,
        ];

        foreach ($performanceServices as $service) {
            $instance = app($service);
            $this->assertInstanceOf($service, $instance, "Performance service {$service} should be resolvable");
        }
    }

    /** @test */
    public function security_measures_are_in_place()
    {
        // Test that security services can be resolved
        $securityServices = [
            \App\Services\GDevelopJsonValidationService::class,
            \App\Services\GDevelopSandboxService::class,
        ];

        foreach ($securityServices as $service) {
            $instance = app($service);
            $this->assertInstanceOf($service, $instance, "Security service {$service} should be resolvable");
        }

        // Test that security middleware is registered
        $middleware = app('router')->getMiddleware();
        $this->assertArrayHasKey('gdevelop.enabled', $middleware);
        $this->assertEquals(\App\Http\Middleware\EnsureGDevelopEnabled::class, $middleware['gdevelop.enabled']);
    }
}