<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('landing page performance is acceptable', function () {
    $startTime = microtime(true);
    
    $response = $this->get('/');
    
    $loadTime = (microtime(true) - $startTime) * 1000;
    
    $response->assertStatus(200);
    $response->assertSee('Laravel');
    
    expect($loadTime)->toBeLessThan(2000);
    
    echo "✓ Landing page load time: " . round($loadTime, 2) . "ms\n";
});

test('login page performance is acceptable', function () {
    $startTime = microtime(true);
    
    $response = $this->get('/login');
    
    $loadTime = (microtime(true) - $startTime) * 1000;
    
    $response->assertStatus(200);
    
    expect($loadTime)->toBeLessThan(1000);
    
    echo "✓ Login page load time: " . round($loadTime, 2) . "ms\n";
});

test('dashboard performance for authenticated user', function () {
    $testUser = User::factory()->create();
    
    $startTime = microtime(true);
    
    $response = $this->actingAs($testUser)->get('/dashboard');
    
    $loadTime = (microtime(true) - $startTime) * 1000;
    
    $response->assertStatus(200);
    
    expect($loadTime)->toBeLessThan(1500);
    
    echo "✓ Dashboard load time: " . round($loadTime, 2) . "ms\n";
});

test('database query performance is optimized', function () {
    $testUser = User::factory()->create();
    
    DB::enableQueryLog();
    
    $this->actingAs($testUser)->get('/dashboard');
    
    $queries = DB::getQueryLog();
    
    expect(count($queries))->toBeLessThan(20);
    
    foreach ($queries as $query) {
        expect($query['time'])->toBeLessThan(100);
    }
    
    echo "✓ Dashboard executed " . count($queries) . " database queries\n";
    
    DB::disableQueryLog();
});

test('memory usage is reasonable during operations', function () {
    $testUser = User::factory()->create();
    
    $initialMemory = memory_get_usage(true);
    
    $this->actingAs($testUser)->get('/dashboard');
    $this->get('/login');
    $this->get('/');
    
    $finalMemory = memory_get_usage(true);
    $memoryUsed = $finalMemory - $initialMemory;
    
    expect($memoryUsed)->toBeLessThan(50 * 1024 * 1024);
    
    echo "✓ Memory used: " . round($memoryUsed / 1024 / 1024, 2) . "MB\n";
});

test('API user endpoint responds quickly', function () {
    $testUser = User::factory()->create();
    
    $startTime = microtime(true);
    
    $response = $this->actingAs($testUser)->get('/api/user');
    
    $loadTime = (microtime(true) - $startTime) * 1000;
    
    $response->assertStatus(200);
    
    expect($loadTime)->toBeLessThan(500);
    
    echo "✓ API /api/user response time: " . round($loadTime, 2) . "ms\n";
});

test('assets are properly versioned for caching', function () {
    $response = $this->get('/');
    
    $content = $response->getContent();
    
    // Check for proper asset versioning (cache busting)
    expect($content)->toMatch('/build\/assets\/.*\.(css|js)/');
    
    echo "✓ Assets are properly versioned for caching\n";
});

test('response includes performance headers', function () {
    $response = $this->get('/');
    
    expect($response->headers->has('Cache-Control'))->toBe(true);
    
    echo "✓ Performance headers are present\n";
});

test('mobile viewport meta tag is present', function () {
    $response = $this->get('/');
    
    $content = $response->getContent();
    
    expect($content)->toContain('name="viewport"');
    expect($content)->toContain('width=device-width');
    
    echo "✓ Mobile viewport meta tag is present\n";
});

test('CSS and JavaScript are loaded efficiently', function () {
    $response = $this->get('/');
    
    $content = $response->getContent();
    
    // Check for preload directives
    expect($content)->toContain('rel="preload"');
    expect($content)->toContain('rel="modulepreload"');
    
    echo "✓ CSS and JavaScript use efficient loading strategies\n";
});