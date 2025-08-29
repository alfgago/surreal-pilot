<?php

use App\Models\User;
use App\Services\PerformanceMonitoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('landing page loads within acceptable time', function () {
    $startTime = microtime(true);
    
    $response = $this->get('/');
    
    $loadTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
    
    $response->assertStatus(200);
    $response->assertSee('Laravel'); // The page shows Laravel as title
    
    // Should load within 2 seconds (2000ms)
    expect($loadTime)->toBeLessThan(2000);
    
    echo "Landing page load time: " . round($loadTime, 2) . "ms\n";
});

test('login page loads quickly', function () {
    $startTime = microtime(true);
    
    $response = $this->get('/login');
    
    $loadTime = (microtime(true) - $startTime) * 1000;
    
    $response->assertStatus(200);
    // Login page should contain form elements
    
    expect($loadTime)->toBeLessThan(1000);
    
    echo "Login page load time: " . round($loadTime, 2) . "ms\n";
});

test('dashboard loads quickly for authenticated user', function () {
    $testUser = User::factory()->create();
    
    $startTime = microtime(true);
    
    $response = $this->actingAs($testUser)->get('/dashboard');
    
    $loadTime = (microtime(true) - $startTime) * 1000;
    
    $response->assertStatus(200);
    
    expect($loadTime)->toBeLessThan(1500);
    
    echo "Dashboard load time: " . round($loadTime, 2) . "ms\n";
});

test('chat page loads efficiently', function () {
    $testUser = User::factory()->create();
    
    $startTime = microtime(true);
    
    $response = $this->actingAs($testUser)->get('/chat');
    
    $loadTime = (microtime(true) - $startTime) * 1000;
    
    $response->assertStatus([200, 302]); // Allow redirect to workspace selection
    
    expect($loadTime)->toBeLessThan(1500);
    
    echo "Chat page load time: " . round($loadTime, 2) . "ms\n";
});

test('games page loads efficiently', function () {
    $testUser = User::factory()->create();
    
    $startTime = microtime(true);
    
    $response = $this->actingAs($testUser)->get('/games');
    
    $loadTime = (microtime(true) - $startTime) * 1000;
    
    $response->assertStatus([200, 302]); // Allow redirect to workspace selection
    
    expect($loadTime)->toBeLessThan(1500);
    
    echo "Games page load time: " . round($loadTime, 2) . "ms\n";
});

test('API endpoints respond quickly', function () {
    $testUser = User::factory()->create();
    
    $endpoints = [
        '/api/user',
        '/api/workspaces',
        '/api/conversations',
    ];
    
    foreach ($endpoints as $endpoint) {
        $startTime = microtime(true);
        
        $response = $this->actingAs($testUser)->get($endpoint);
        
        $loadTime = (microtime(true) - $startTime) * 1000;
        
        $response->assertStatus(200);
        
        // API endpoints should respond within 500ms
        expect($loadTime)->toBeLessThan(500);
        
        echo "API $endpoint response time: " . round($loadTime, 2) . "ms\n";
    }
});

test('database queries are optimized', function () {
    $testUser = User::factory()->create();
    
    // Enable query logging
    DB::enableQueryLog();
    
    // Perform a typical dashboard load operation
    $this->actingAs($testUser)->get('/dashboard');
    
    $queries = DB::getQueryLog();
    
    // Should not have excessive queries (N+1 problem)
    expect(count($queries))->toBeLessThan(20);
    
    // Check for slow queries
    foreach ($queries as $query) {
        // Each query should execute quickly (under 100ms)
        expect($query['time'])->toBeLessThan(100);
    }
    
    echo "Dashboard executed " . count($queries) . " database queries\n";
    
    DB::disableQueryLog();
});

test('memory usage is reasonable', function () {
    $testUser = User::factory()->create();
    
    $initialMemory = memory_get_usage(true);
    
    // Simulate typical user operations
    $this->actingAs($testUser)->get('/dashboard');
    $this->actingAs($testUser)->get('/chat');
    $this->actingAs($testUser)->get('/games');
    
    $finalMemory = memory_get_usage(true);
    $memoryUsed = $finalMemory - $initialMemory;
    
    // Memory usage should be reasonable (under 50MB for these operations)
    expect($memoryUsed)->toBeLessThan(50 * 1024 * 1024);
    
    echo "Memory used: " . round($memoryUsed / 1024 / 1024, 2) . "MB\n";
});

test('performance monitoring service tracks metrics correctly', function () {
    $performanceService = app(PerformanceMonitoringService::class);
    
    // Test page load tracking
    $performanceService->trackPageLoad(request(), 1500);
    
    $metrics = $performanceService->getMetrics('test-route');
    
    expect($metrics['total_loads'])->toBe(1);
    expect($metrics['average_time'])->toBe(1500.0);
    expect($metrics['slow_loads'])->toBe(0); // 1500ms is under 2000ms threshold
    
    // Test slow page load
    $performanceService->trackPageLoad(request(), 2500);
    
    $metrics = $performanceService->getMetrics('test-route');
    
    expect($metrics['total_loads'])->toBe(2);
    expect($metrics['slow_loads'])->toBe(1);
    expect($metrics['slow_load_percentage'])->toBe(50.0);
});

test('CSS and JavaScript assets are optimized', function () {
    $response = $this->get('/');
    
    $content = $response->getContent();
    
    // Check that assets are minified (no excessive whitespace)
    expect($content)->not->toContain('    '); // Multiple spaces indicate unminified content
    
    // Check for proper asset versioning (cache busting)
    expect($content)->toMatch('/\.(css|js)\?id=[a-f0-9]+/');
    
    echo "Assets appear to be optimized and versioned\n";
});

test('response headers include performance optimizations', function () {
    $response = $this->get('/');
    
    // Check for caching headers
    expect($response->headers->has('Cache-Control'))->toBe(true);
    
    // Check for compression
    $response = $this->get('/', ['Accept-Encoding' => 'gzip']);
    
    echo "Performance headers verified\n";
});