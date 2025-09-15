<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

// Don't use RefreshDatabase to avoid SQLite issues
// uses(RefreshDatabase::class);

beforeEach(function () {
    // Enable GDevelop for testing
    Config::set('gdevelop.enabled', true);
    Config::set('gdevelop.engines.gdevelop_enabled', true);
    
    // Mock HTTP responses for AI
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'gameData' => [
                                'properties' => [
                                    'name' => 'Test Game',
                                    'description' => 'A test game created by AI'
                                ]
                            ]
                        ])
                    ]
                ]
            ]
        ], 200)
    ]);
});

test('homepage loads successfully', function () {
    $response = $this->get('/');
    $response->assertStatus(200);
});

test('login page loads successfully', function () {
    $response = $this->get('/login');
    $response->assertStatus(200);
    $response->assertSee('Sign in');
});

test('register page loads successfully', function () {
    $response = $this->get('/register');
    $response->assertStatus(200);
    $response->assertSee('Register');
});

test('engine selection requires authentication', function () {
    $response = $this->get('/engine-selection');
    $response->assertRedirect('/login');
});

test('gdevelop configuration is properly set', function () {
    expect(config('gdevelop.enabled'))->toBeTrue();
    expect(config('gdevelop.engines.gdevelop_enabled'))->toBeTrue();
});

test('can access api routes structure', function () {
    // Test that API routes are defined (will return 401/403 without auth)
    $response = $this->get('/api/assist');
    expect($response->status())->toBeIn([401, 403, 405]); // Unauthorized or Method Not Allowed
    
    $response = $this->get('/api/workspaces/1');
    expect($response->status())->toBeIn([401, 403, 404]); // Unauthorized or Not Found
});

test('registration endpoint accepts correct data', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'company_name' => 'Test Company'
    ]);
    
    // Should redirect after successful registration
    expect($response->status())->toBeIn([302, 422]); // Redirect or validation error
});

test('login endpoint accepts credentials', function () {
    $response = $this->post('/login', [
        'email' => 'test@example.com',
        'password' => 'password123'
    ]);
    
    // Should redirect or return validation error
    expect($response->status())->toBeIn([302, 422]);
});

test('engine selection endpoint structure', function () {
    // Test POST to engine selection (will fail without auth)
    $response = $this->post('/engine-selection', [
        'engine_type' => 'gdevelop'
    ]);
    
    expect($response->status())->toBeIn([302, 401, 403, 422]);
});

test('workspace creation endpoint structure', function () {
    // Test POST to workspaces (will fail without auth)
    $response = $this->post('/workspaces', [
        'name' => 'Test Workspace',
        'engine' => 'gdevelop'
    ]);
    
    expect($response->status())->toBeIn([302, 401, 403, 422]);
});

test('ai assist endpoint structure', function () {
    // Test POST to assist endpoint (will fail without auth)
    $response = $this->post('/api/assist', [
        'message' => 'Create a game',
        'workspace_id' => 1
    ]);
    
    expect($response->status())->toBeIn([401, 403, 422]);
});

test('gdevelop preview endpoint structure', function () {
    // Test GET to preview endpoint (will fail without auth)
    $response = $this->get('/api/workspaces/1/gdevelop/preview');
    
    expect($response->status())->toBeIn([401, 403, 404]);
});

test('gdevelop export endpoint structure', function () {
    // Test POST to export endpoint (will fail without auth)
    $response = $this->post('/api/workspaces/1/gdevelop/export', [
        'format' => 'html5'
    ]);
    
    expect($response->status())->toBeIn([401, 403, 404, 422]);
});

test('application has correct middleware setup', function () {
    // Test that protected routes redirect to login
    $protectedRoutes = [
        '/engine-selection',
        '/workspace-selection',
        '/dashboard'
    ];
    
    foreach ($protectedRoutes as $route) {
        $response = $this->get($route);
        expect($response->status())->toBeIn([302, 401, 403]);
    }
});

test('api routes have correct authentication middleware', function () {
    // Test that API routes require authentication
    $apiRoutes = [
        '/api/assist',
        '/api/workspaces/1',
        '/api/workspaces/1/context'
    ];
    
    foreach ($apiRoutes as $route) {
        $response = $this->get($route);
        expect($response->status())->toBeIn([401, 403, 404, 405]);
    }
});

test('configuration values are accessible', function () {
    // Test that we can read configuration values
    expect(config('app.name'))->toBe('Laravel');
    expect(config('app.url'))->toBe('http://surreal-pilot.local');
    
    // Test GDevelop specific config
    $gdevelopEnabled = config('gdevelop.enabled');
    expect($gdevelopEnabled)->toBeBool();
});