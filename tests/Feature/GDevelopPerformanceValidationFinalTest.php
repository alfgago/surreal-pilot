<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use App\Models\Workspace;
use App\Services\GDevelopPerformanceMonitorService;
use App\Services\GDevelopCacheService;
use App\Services\GDevelopProcessPoolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GDevelopPerformanceValidationFinalTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Company $company;
    protected Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        // Enable GDevelop and performance features
        Config::set('gdevelop.enabled', true);
        Config::set('gdevelop.performance.cache_enabled', true);
        Config::set('gdevelop.performance.process_pool_enabled', true);
        Config::set('gdevelop.performance.async_processing_enabled', true);
        Config::set('gdevelop.performance.monitoring_enabled', true);

        $this->company = Company::factory()->create(['credits' => 1000]);
        $this->user = User::factory()->create(['current_company_id' => $this->company->id]);
        $this->company->users()->attach($this->user->id, ['role' => 'owner']);

        $this->workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'created_by' => $this->user->id,
            'engine_type' => 'gdevelop'
        ]);
    }

    /** @test */
    public function game_creation_performance_meets_requirements()
    {
        $this->actingAs($this->user);

        $performanceMonitor = app(GDevelopPerformanceMonitorService::class);
        
        // Test simple game creation performance
        $startTime = microtime(true);
        
        $response = $this->postJson('/api/gdevelop/chat', [
            'session_id' => 'perf-test-simple',
            'workspace_id' => $this->workspace->id,
            'message' => 'Create a simple platformer game'
        ]);
        
        $simpleGameTime = microtime(true) - $startTime;
        
        $response->assertStatus(200);
        $this->assertLessThan(10, $simpleGameTime, 'Simple game creation should complete within 10 seconds');

        // Test complex game creation performance
        $startTime = microtime(true);
        
        $response = $this->postJson('/api/gdevelop/chat', [
            'session_id' => 'perf-test-complex',
            'workspace_id' => $this->workspace->id,
            'message' => 'Create a complex tower defense game with 5 different tower types, 10 enemy types, multiple levels, upgrade systems, and particle effects'
        ]);
        
        $complexGameTime = microtime(true) - $startTime;
        
        $response->assertStatus(200);
        $this->assertLessThan(30, $complexGameTime, 'Complex game creation should complete within 30 seconds');

        // Verify performance metrics were recorded
        $metrics = $performanceMonitor->getMetrics();
        $this->assertArrayHasKey('game_creation_time', $metrics);
        $this->assertArrayHasKey('total_operations', $metrics);
    }

    /** @test */
    public function preview_generation_performance_meets_requirements()
    {
        $this->actingAs($this->user);

        // Create a game first
        $this->postJson('/api/gdevelop/chat', [
            'session_id' => 'preview-perf-test',
            'workspace_id' => $this->workspace->id,
            'message' => 'Create a platformer game with multiple levels'
        ]);

        // Test initial preview generation
        $startTime = microtime(true);
        
        $response = $this->getJson('/api/gdevelop/preview/preview-perf-test');
        
        $initialPreviewTime = microtime(true) - $startTime;
        
        $response->assertStatus(200);
        $this->assertLessThan(5, $initialPreviewTime, 'Initial preview generation should complete within 5 seconds');

        // Test cached preview access
        $startTime = microtime(true);
        
        $response = $this->getJson('/api/gdevelop/preview/preview-perf-test');
        
        $cachedPreviewTime = microtime(true) - $startTime;
        
        $response->assertStatus(200);
        $this->assertLessThan(1, $cachedPreviewTime, 'Cached preview should load within 1 second');

        // Verify caching is working
        $responseData = $response->json();
        $this->assertTrue($responseData['cached'] ?? false, 'Preview should be served from cache');
    }

    /** @test */
    public function export_performance_meets_requirements()
    {
        $this->actingAs($this->user);

        // Create a game first
        $this->postJson('/api/gdevelop/chat', [
            'session_id' => 'export-perf-test',
            'workspace_id' => $this->workspace->id,
            'message' => 'Create a complete arcade game'
        ]);

        // Test export performance
        $startTime = microtime(true);
        
        $response = $this->postJson('/api/gdevelop/export/export-perf-test', [
            'includeAssets' => true,
            'compressionLevel' => 'standard'
        ]);
        
        $exportTime = microtime(true) - $startTime;
        
        $response->assertStatus(200);
        $this->assertLessThan(30, $exportTime, 'Export should complete within 30 seconds');

        // Test export with maximum compression
        $startTime = microtime(true);
        
        $response = $this->postJson('/api/gdevelop/export/export-perf-test', [
            'includeAssets' => true,
            'compressionLevel' => 'maximum'
        ]);
        
        $maxCompressionTime = microtime(true) - $startTime;
        
        $response->assertStatus(200);
        $this->assertLessThan(60, $maxCompressionTime, 'Maximum compression export should complete within 60 seconds');
    }

    /** @test */
    public function caching_system_improves_performance()
    {
        $cacheService = app(GDevelopCacheService::class);
        
        // Test template caching
        $templateKey = 'template:platformer';
        $templateData = ['name' => 'Platformer', 'objects' => []];
        
        $cacheService->cacheTemplate('platformer', $templateData);
        $this->assertTrue($cacheService->hasTemplate('platformer'));
        
        $startTime = microtime(true);
        $cachedTemplate = $cacheService->getTemplate('platformer');
        $cacheTime = microtime(true) - $startTime;
        
        $this->assertEquals($templateData, $cachedTemplate);
        $this->assertLessThan(0.001, $cacheTime, 'Cache retrieval should be under 1ms');

        // Test game structure caching
        $gameStructure = ['layouts' => [], 'objects' => [], 'events' => []];
        $cacheService->cacheGameStructure('test-structure', $gameStructure);
        
        $startTime = microtime(true);
        $cachedStructure = $cacheService->getGameStructure('test-structure');
        $structureCacheTime = microtime(true) - $startTime;
        
        $this->assertEquals($gameStructure, $cachedStructure);
        $this->assertLessThan(0.001, $structureCacheTime, 'Structure cache retrieval should be under 1ms');

        // Test validation caching
        $validationResult = ['valid' => true, 'errors' => []];
        $cacheService->cacheValidationResult('test-validation', $validationResult);
        
        $startTime = microtime(true);
        $cachedValidation = $cacheService->getValidationResult('test-validation');
        $validationCacheTime = microtime(true) - $startTime;
        
        $this->assertEquals($validationResult, $cachedValidation);
        $this->assertLessThan(0.001, $validationCacheTime, 'Validation cache retrieval should be under 1ms');
    }

    /** @test */
    public function process_pool_improves_concurrent_performance()
    {
        $processPool = app(GDevelopProcessPoolService::class);
        
        // Test process pool initialization
        $this->assertTrue($processPool->isEnabled());
        $this->assertGreaterThan(0, $processPool->getPoolSize());

        // Test concurrent operations
        $this->actingAs($this->user);
        
        $sessionIds = ['concurrent-1', 'concurrent-2', 'concurrent-3'];
        $startTime = microtime(true);
        
        $responses = [];
        foreach ($sessionIds as $sessionId) {
            $responses[] = $this->postJson('/api/gdevelop/chat', [
                'session_id' => $sessionId,
                'workspace_id' => $this->workspace->id,
                'message' => 'Create a simple game'
            ]);
        }
        
        $concurrentTime = microtime(true) - $startTime;
        
        // All requests should succeed
        foreach ($responses as $response) {
            $response->assertStatus(200);
        }
        
        // Concurrent processing should be faster than sequential
        $this->assertLessThan(25, $concurrentTime, 'Concurrent operations should complete within 25 seconds');
        
        // Test process pool metrics
        $poolMetrics = $processPool->getMetrics();
        $this->assertArrayHasKey('active_processes', $poolMetrics);
        $this->assertArrayHasKey('completed_operations', $poolMetrics);
        $this->assertGreaterThan(0, $poolMetrics['completed_operations']);
    }

    /** @test */
    public function async_processing_handles_long_operations()
    {
        Queue::fake();
        
        $this->actingAs($this->user);
        
        // Create a game that should trigger async processing
        $response = $this->postJson('/api/gdevelop/chat', [
            'session_id' => 'async-test',
            'workspace_id' => $this->workspace->id,
            'message' => 'Create a very complex RPG game with multiple systems'
        ]);
        
        $response->assertStatus(200);
        
        // Test export with async processing
        $exportResponse = $this->postJson('/api/gdevelop/export/async-test', [
            'includeAssets' => true,
            'compressionLevel' => 'maximum',
            'async' => true
        ]);
        
        $exportResponse->assertStatus(202); // Accepted for async processing
        
        // Verify jobs were queued
        Queue::assertPushed(\App\Jobs\GDevelopExportJob::class);
        
        // Test status checking
        $statusResponse = $this->getJson('/api/gdevelop/export/async-test/status');
        $statusResponse->assertStatus(200);
        
        $statusData = $statusResponse->json();
        $this->assertArrayHasKey('status', $statusData);
        $this->assertContains($statusData['status'], ['queued', 'processing', 'completed', 'failed']);
    }

    /** @test */
    public function performance_monitoring_tracks_metrics_correctly()
    {
        $monitor = app(GDevelopPerformanceMonitorService::class);
        
        // Test metric recording
        $monitor->recordOperationTime('test_operation', 1.5);
        $monitor->recordMemoryUsage('test_memory', 1024 * 1024); // 1MB
        $monitor->recordCacheHit('test_cache');
        $monitor->recordCacheMiss('test_cache');
        
        // Get metrics
        $metrics = $monitor->getMetrics();
        
        $this->assertArrayHasKey('operations', $metrics);
        $this->assertArrayHasKey('memory_usage', $metrics);
        $this->assertArrayHasKey('cache_stats', $metrics);
        
        // Test performance alerts
        $monitor->recordOperationTime('slow_operation', 35); // Above threshold
        $alerts = $monitor->getPerformanceAlerts();
        
        $this->assertNotEmpty($alerts);
        $this->assertStringContainsString('slow_operation', $alerts[0]['message']);
        
        // Test metrics history
        $history = $monitor->getMetricsHistory(24); // Last 24 hours
        $this->assertIsArray($history);
        $this->assertArrayHasKey('timestamps', $history);
        $this->assertArrayHasKey('values', $history);
    }

    /** @test */
    public function system_handles_resource_limits_gracefully()
    {
        $this->actingAs($this->user);
        
        // Test memory limit handling
        Config::set('gdevelop.performance.memory_limit', '128M');
        
        $response = $this->postJson('/api/gdevelop/chat', [
            'session_id' => 'memory-test',
            'workspace_id' => $this->workspace->id,
            'message' => 'Create a game with thousands of objects and complex physics'
        ]);
        
        // Should either succeed with optimization or fail gracefully
        $this->assertContains($response->getStatusCode(), [200, 422, 503]);
        
        if ($response->getStatusCode() !== 200) {
            $data = $response->json();
            $this->assertArrayHasKey('error', $data);
            $this->assertStringContainsString('resource', strtolower($data['error']));
        }
        
        // Test concurrent operation limits
        Config::set('gdevelop.performance.max_concurrent_operations', 2);
        
        $responses = [];
        for ($i = 0; $i < 5; $i++) {
            $responses[] = $this->postJson('/api/gdevelop/chat', [
                'session_id' => "concurrent-limit-{$i}",
                'workspace_id' => $this->workspace->id,
                'message' => 'Create a simple game'
            ]);
        }
        
        // Some requests should be rate limited
        $rateLimitedCount = 0;
        foreach ($responses as $response) {
            if ($response->getStatusCode() === 429) {
                $rateLimitedCount++;
            }
        }
        
        $this->assertGreaterThan(0, $rateLimitedCount, 'Some requests should be rate limited');
    }

    /** @test */
    public function performance_degrades_gracefully_under_load()
    {
        $this->actingAs($this->user);
        
        $monitor = app(GDevelopPerformanceMonitorService::class);
        
        // Simulate high load
        $sessionCount = 10;
        $responses = [];
        $times = [];
        
        for ($i = 0; $i < $sessionCount; $i++) {
            $startTime = microtime(true);
            
            $response = $this->postJson('/api/gdevelop/chat', [
                'session_id' => "load-test-{$i}",
                'workspace_id' => $this->workspace->id,
                'message' => 'Create a platformer game'
            ]);
            
            $endTime = microtime(true);
            $times[] = $endTime - $startTime;
            $responses[] = $response;
        }
        
        // All requests should eventually succeed
        foreach ($responses as $response) {
            $this->assertContains($response->getStatusCode(), [200, 202, 429]);
        }
        
        // Performance should degrade gracefully (not exponentially)
        $avgTime = array_sum($times) / count($times);
        $maxTime = max($times);
        
        $this->assertLessThan(60, $maxTime, 'Maximum response time should be under 60 seconds');
        $this->assertLessThan(30, $avgTime, 'Average response time should be under 30 seconds');
        
        // Check system health after load
        $healthMetrics = $monitor->getSystemHealth();
        $this->assertArrayHasKey('memory_usage', $healthMetrics);
        $this->assertArrayHasKey('active_sessions', $healthMetrics);
        $this->assertArrayHasKey('error_rate', $healthMetrics);
        
        // Error rate should be reasonable
        $this->assertLessThan(0.2, $healthMetrics['error_rate'], 'Error rate should be under 20%');
    }
}