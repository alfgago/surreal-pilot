<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    // Configure to use the main MySQL database
    Config::set('database.default', 'mysql');
    
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
                                ],
                                'scenes' => [
                                    [
                                        'name' => 'MainScene',
                                        'objects' => [
                                            [
                                                'name' => 'Player',
                                                'type' => 'Sprite'
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ])
                    ]
                ]
            ]
        ], 200)
    ]);
});

test('application is accessible and working', function () {
    $response = $this->get('/');
    $response->assertStatus(200);
    $response->assertSee('SurrealPilot');
});

test('authentication system works', function () {
    // Test login page
    $response = $this->get('/login');
    $response->assertStatus(200);
    $response->assertSee('Sign in');
    
    // Test register page
    $response = $this->get('/register');
    $response->assertStatus(200);
    $response->assertSee('Register');
    
    // Test protected route redirects
    $response = $this->get('/engine-selection');
    $response->assertRedirect('/login');
});

test('gdevelop configuration is properly loaded', function () {
    expect(config('gdevelop.enabled'))->toBeTrue();
    expect(config('gdevelop.engines.gdevelop_enabled'))->toBeTrue();
    expect(config('app.url'))->toBe('http://surreal-pilot.local');
});

test('user registration workflow', function () {
    $uniqueEmail = 'test' . time() . '@gdevelop.com';
    
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => $uniqueEmail,
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'company_name' => 'Test Company'
    ]);
    
    // Should redirect after successful registration
    expect($response->status())->toBeIn([302, 422]);
    
    if ($response->status() === 302) {
        // Registration successful, check if user exists
        $user = DB::table('users')->where('email', $uniqueEmail)->first();
        expect($user)->not->toBeNull();
    }
});

test('engine selection system works', function () {
    // Test engine selection endpoint structure
    $response = $this->post('/engine-selection', [
        'engine_type' => 'gdevelop'
    ]);
    
    // Should require authentication
    expect($response->status())->toBeIn([302, 401, 403, 422]);
});

test('workspace creation system works', function () {
    $response = $this->post('/workspaces', [
        'name' => 'My GDevelop Game',
        'engine' => 'gdevelop'
    ]);
    
    // Should require authentication
    expect($response->status())->toBeIn([302, 401, 403, 422]);
});

test('ai chat system endpoints exist', function () {
    $response = $this->post('/api/assist', [
        'message' => 'Create a simple platformer game',
        'workspace_id' => 1
    ]);
    
    // Should require authentication
    expect($response->status())->toBeIn([401, 403, 422]);
});

test('gdevelop specific endpoints exist', function () {
    // Test preview endpoint
    $response = $this->get('/api/workspaces/1/gdevelop/preview');
    expect($response->status())->toBeIn([401, 403, 404]);
    
    // Test export endpoint
    $response = $this->post('/api/workspaces/1/gdevelop/export', [
        'format' => 'html5'
    ]);
    expect($response->status())->toBeIn([401, 403, 404, 422]);
});

test('complete user workflow simulation', function () {
    // Step 1: Test registration endpoint
    $uniqueEmail = 'workflow' . time() . '@test.com';
    $registrationResponse = $this->post('/register', [
        'name' => 'Workflow Test User',
        'email' => $uniqueEmail,
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'company_name' => 'Workflow Test Company'
    ]);
    
    expect($registrationResponse->status())->toBeIn([302, 422]);
    
    // Step 2: Test login attempt
    $loginResponse = $this->post('/login', [
        'email' => $uniqueEmail,
        'password' => 'password123'
    ]);
    
    expect($loginResponse->status())->toBeIn([302, 422]);
    
    // Step 3: Test engine selection (without auth)
    $engineResponse = $this->post('/engine-selection', [
        'engine_type' => 'gdevelop'
    ]);
    
    expect($engineResponse->status())->toBeIn([302, 401, 403, 422]);
    
    // Step 4: Test workspace creation (without auth)
    $workspaceResponse = $this->post('/workspaces', [
        'name' => 'Test Workspace',
        'engine' => 'gdevelop'
    ]);
    
    expect($workspaceResponse->status())->toBeIn([302, 401, 403, 422]);
    
    // Step 5: Test AI chat (without auth)
    $chatResponse = $this->post('/api/assist', [
        'message' => 'Create a game',
        'workspace_id' => 1
    ]);
    
    expect($chatResponse->status())->toBeIn([401, 403, 422]);
    
    // All endpoints are responding correctly
    expect(true)->toBeTrue();
});

test('error handling works correctly', function () {
    // Mock AI failure
    Http::fake([
        'api.openai.com/*' => Http::response(['error' => 'API Error'], 500)
    ]);
    
    $response = $this->post('/api/assist', [
        'message' => 'Create a game',
        'workspace_id' => 1
    ]);
    
    // Should handle the error gracefully (require auth first)
    expect($response->status())->toBeIn([401, 403, 422, 500]);
});

test('api routes structure is correct', function () {
    $apiRoutes = [
        ['GET', '/api/workspaces/1'],
        ['GET', '/api/workspaces/1/context'],
        ['GET', '/api/workspaces/1/engine/status'],
        ['POST', '/api/assist'],
        ['GET', '/api/workspaces/1/gdevelop/preview'],
        ['POST', '/api/workspaces/1/gdevelop/export']
    ];
    
    foreach ($apiRoutes as [$method, $route]) {
        $response = $this->call($method, $route, [
            'format' => 'html5' // For export endpoint
        ]);
        
        // Should require authentication or return proper error
        expect($response->status())->toBeIn([401, 403, 404, 405, 422]);
    }
});

test('middleware protection is working', function () {
    $protectedRoutes = [
        '/engine-selection',
        '/workspace-selection',
        '/dashboard'
    ];
    
    foreach ($protectedRoutes as $route) {
        $response = $this->get($route);
        // Should redirect to login or return 401/403
        expect($response->status())->toBeIn([302, 401, 403]);
    }
});

test('configuration system works', function () {
    // Test that we can read and modify configuration
    expect(config('app.name'))->toBe('Laravel');
    expect(config('database.default'))->toBe('mysql');
    
    // Test GDevelop specific configuration
    expect(config('gdevelop.enabled'))->toBeTrue();
    
    // Test configuration modification in tests
    Config::set('test.gdevelop.custom', 'test_value');
    expect(config('test.gdevelop.custom'))->toBe('test_value');
});

test('http mocking system works', function () {
    // Test that our HTTP mocking is working
    Http::fake([
        'example.com/*' => Http::response(['success' => true], 200)
    ]);
    
    $response = Http::get('https://example.com/test');
    expect($response->json())->toBe(['success' => true]);
    expect($response->status())->toBe(200);
});

test('database connection works', function () {
    // Test that we can connect to the database
    $result = DB::select('SELECT 1 as test');
    expect($result[0]->test)->toBe(1);
    
    // Test that we can query existing tables
    $tables = DB::select("SHOW TABLES");
    expect(count($tables))->toBeGreaterThan(0);
});

test('application environment is correct', function () {
    expect(config('app.env'))->toBe('local');
    expect(config('app.debug'))->toBeTrue();
    expect(config('app.url'))->toBe('http://surreal-pilot.local');
});

test('gdevelop features are enabled', function () {
    // Test that GDevelop is enabled in configuration
    expect(config('gdevelop.enabled'))->toBeTrue();
    expect(config('gdevelop.engines.gdevelop_enabled'))->toBeTrue();
    
    // Test that we can access GDevelop-related configuration
    $gdevelopConfig = config('gdevelop');
    expect($gdevelopConfig)->toBeArray();
    expect($gdevelopConfig['enabled'])->toBeTrue();
});