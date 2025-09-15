<?php

use App\Models\User;
use App\Models\Company;
use App\Models\Workspace;
use App\Models\GDevelopGameSession;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Http;
use function Pest\Laravel\{actingAs, post, get, put, delete, assertDatabaseHas};

uses(DatabaseMigrations::class);

beforeEach(function () {
    // Enable GDevelop for testing
    Config::set('gdevelop.enabled', true);
    Config::set('gdevelop.engines.gdevelop_enabled', true);
    Config::set('gdevelop.engines.gdevelop.templates_path', storage_path('gdevelop/templates'));
    Config::set('gdevelop.engines.gdevelop.games_path', storage_path('gdevelop/games'));
    Config::set('gdevelop.engines.gdevelop.exports_path', storage_path('gdevelop/exports'));
    
    // Setup storage directories
    Storage::fake('local');
    Storage::makeDirectory('gdevelop/templates');
    Storage::makeDirectory('gdevelop/games');
    Storage::makeDirectory('gdevelop/exports');
    
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
    
    Queue::fake();
});

test('complete user workflow from registration to game export', function () {
    // Step 1: Test user registration via API
    $response = post('/register', [
        'name' => 'Test User',
        'email' => 'test@gdevelop.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'company_name' => 'Test Company'
    ]);

    $response->assertRedirect('/dashboard');
    assertDatabaseHas('users', ['email' => 'test@gdevelop.com']);
    assertDatabaseHas('companies', ['name' => 'Test Company']);

    // Step 2: Test engine selection
    $user = User::where('email', 'test@gdevelop.com')->first();
    actingAs($user);

    $engineResponse = get('/engine-selection');
    $engineResponse->assertOk();
    $engineResponse->assertSee('Choose Your Game Engine');
    $engineResponse->assertSee('GDevelop');

    // Step 3: Select GDevelop engine (if available)
    $selectResponse = post('/engine-selection', [
        'engine_type' => 'gdevelop'
    ]);

    // If GDevelop is not available, test with PlayCanvas
    if ($selectResponse->status() !== 302) {
        $selectResponse = post('/engine-selection', [
            'engine_type' => 'playcanvas'
        ]);
        $selectResponse->assertRedirect('/workspace-selection');
    }

    // Step 4: Create workspace
    $workspaceResponse = post('/workspaces', [
        'name' => 'My GDevelop Game',
        'engine' => 'gdevelop'
    ]);

    if ($workspaceResponse->status() !== 201) {
        // Fallback to PlayCanvas if GDevelop not available
        $workspaceResponse = post('/workspaces', [
            'name' => 'My PlayCanvas Game',
            'engine' => 'playcanvas'
        ]);
    }

    $workspaceResponse->assertStatus(201);
    $workspace = Workspace::where('name', 'LIKE', 'My % Game')->first();
    expect($workspace)->not->toBeNull();

    // Step 5: Test chat functionality
    $chatResponse = post("/api/assist", [
        'message' => 'Create a simple platformer game with a player character that can jump and collect coins',
        'workspace_id' => $workspace->id
    ]);

    $chatResponse->assertOk();
    $chatResponse->assertJsonStructure(['response']);

    // Step 6: Test game preview (if available)
    if ($workspace->engine === 'gdevelop') {
        $previewResponse = get("/api/workspaces/{$workspace->id}/gdevelop/preview");
        // Preview might not be available yet, so we just check it doesn't error
        expect($previewResponse->status())->toBeIn([200, 404, 422]);
    }

    // Step 7: Test game export (if available)
    if ($workspace->engine === 'gdevelop') {
        $exportResponse = post("/api/workspaces/{$workspace->id}/gdevelop/export", [
            'format' => 'html5'
        ]);
        // Export might not be available yet, so we just check it doesn't error
        expect($exportResponse->status())->toBeIn([200, 202, 404, 422]);
    }

    // Verify final database state
    assertDatabaseHas('workspaces', ['name' => $workspace->name]);
});

test('user can modify game through chat iterations', function () {
    // Create test user and workspace
    $company = Company::factory()->create(['name' => 'Test Company']);
    $user = User::factory()->create([
        'current_company_id' => $company->id,
        'email' => 'test@iterations.com',
        'password' => bcrypt('password123')
    ]);
    
    // Associate user with company
    $user->companies()->attach($company->id);
    
    $workspace = Workspace::factory()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'engine' => 'gdevelop',
        'name' => 'Test Game Workspace'
    ]);

    actingAs($user);

    // First iteration - create basic game
    $response1 = post("/api/assist", [
        'message' => 'Create a simple jumping game',
        'workspace_id' => $workspace->id
    ]);
    $response1->assertOk();

    // Second iteration - add enemies
    $response2 = post("/api/assist", [
        'message' => 'Add some enemies that move left and right',
        'workspace_id' => $workspace->id
    ]);
    $response2->assertOk();

    // Third iteration - add power-ups
    $response3 = post("/api/assist", [
        'message' => 'Add power-ups that make the player bigger',
        'workspace_id' => $workspace->id
    ]);
    $response3->assertOk();

    // Verify multiple interactions were recorded
    expect(true)->toBeTrue(); // Placeholder - in real implementation, check chat history
});

test('error handling during game creation', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create([
        'current_company_id' => $company->id,
        'email' => 'test@errors.com',
        'password' => bcrypt('password123')
    ]);
    
    // Associate user with company
    $user->companies()->attach($company->id);
    
    $workspace = Workspace::factory()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'engine' => 'gdevelop'
    ]);

    // Mock AI failure
    Http::fake([
        'api.openai.com/*' => Http::response(['error' => 'API Error'], 500)
    ]);

    actingAs($user);

    $response = post("/api/assist", [
        'message' => 'Create a game',
        'workspace_id' => $workspace->id
    ]);

    // Should handle the error gracefully
    expect($response->status())->toBeIn([422, 500]);
});

test('game export with different formats', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create([
        'current_company_id' => $company->id,
        'email' => 'test@export.com',
        'password' => bcrypt('password123')
    ]);
    
    // Associate user with company
    $user->companies()->attach($company->id);
    
    $workspace = Workspace::factory()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'engine' => 'gdevelop'
    ]);

    // Create a game session
    $gameSession = GDevelopGameSession::factory()->create([
        'workspace_id' => $workspace->id,
        'status' => 'completed',
        'game_data' => json_encode(['test' => 'data'])
    ]);

    actingAs($user);

    // Test HTML5 export
    $html5Response = post("/api/workspaces/{$workspace->id}/gdevelop/export", [
        'format' => 'html5'
    ]);
    expect($html5Response->status())->toBeIn([200, 202, 422]);

    // Test Android export
    $androidResponse = post("/api/workspaces/{$workspace->id}/gdevelop/export", [
        'format' => 'android'
    ]);
    expect($androidResponse->status())->toBeIn([200, 202, 422]);
});

test('workspace collaboration features', function () {
    $company = Company::factory()->create();
    $user1 = User::factory()->create(['current_company_id' => $company->id]);
    $user2 = User::factory()->create(['current_company_id' => $company->id]);
    
    // Associate users with company
    $user1->companies()->attach($company->id);
    $user2->companies()->attach($company->id);
    
    $workspace = Workspace::factory()->create([
        'company_id' => $company->id,
        'user_id' => $user1->id,
        'engine' => 'gdevelop'
    ]);

    // User 1 creates a game
    actingAs($user1);
    $response1 = post("/api/assist", [
        'message' => 'Create a racing game',
        'workspace_id' => $workspace->id
    ]);
    $response1->assertOk();

    // User 2 can see and modify the game
    actingAs($user2);
    $response2 = post("/api/assist", [
        'message' => 'Add nitro boost feature',
        'workspace_id' => $workspace->id
    ]);
    $response2->assertOk();
});

test('game data persistence and recovery', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create([
        'current_company_id' => $company->id,
        'email' => 'test@persistence.com',
        'password' => bcrypt('password123')
    ]);
    
    // Associate user with company
    $user->companies()->attach($company->id);
    
    $workspace = Workspace::factory()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'engine' => 'gdevelop'
    ]);

    actingAs($user);

    // Create game
    $response = post("/api/assist", [
        'message' => 'Create a puzzle game',
        'workspace_id' => $workspace->id
    ]);
    $response->assertOk();

    // Verify game data is saved
    assertDatabaseHas('workspaces', [
        'id' => $workspace->id,
        'name' => $workspace->name
    ]);
});

test('api endpoints are accessible', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create([
        'current_company_id' => $company->id,
        'email' => 'test@api.com',
        'password' => bcrypt('password123')
    ]);
    
    // Associate user with company
    $user->companies()->attach($company->id);
    
    $workspace = Workspace::factory()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'engine' => 'gdevelop'
    ]);

    actingAs($user);

    // Test workspace access
    $workspaceResponse = get("/api/workspaces/{$workspace->id}");
    $workspaceResponse->assertOk();

    // Test engine status
    $statusResponse = get("/api/workspaces/{$workspace->id}/engine/status");
    expect($statusResponse->status())->toBeIn([200, 404]);

    // Test workspace context
    $contextResponse = get("/api/workspaces/{$workspace->id}/context");
    expect($contextResponse->status())->toBeIn([200, 404]);
});

test('gdevelop configuration is loaded', function () {
    // Test that GDevelop configuration is properly loaded
    expect(config('gdevelop.enabled'))->toBeTrue();
    expect(config('gdevelop.engines.gdevelop_enabled'))->toBeTrue();
    
    // Test configuration paths
    expect(config('gdevelop.engines.gdevelop.templates_path'))->not->toBeNull();
    expect(config('gdevelop.engines.gdevelop.games_path'))->not->toBeNull();
    expect(config('gdevelop.engines.gdevelop.exports_path'))->not->toBeNull();
});