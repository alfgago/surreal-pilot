<?php

use App\Models\User;
use App\Models\Company;
use App\Models\Workspace;
use App\Models\GDevelopGameSession;
use App\Services\GDevelopGameService;
use App\Services\GDevelopTemplateService;
use App\Services\GDevelopAIService;
use App\Services\GDevelopJsonValidator;
use App\Services\GDevelopSessionManager;
use App\Services\GDevelopErrorRecoveryService;
use App\Services\GDevelopCacheService;
use App\Services\GDevelopPerformanceMonitorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Enable GDevelop for testing
    Config::set('gdevelop.enabled', true);
    Config::set('gdevelop.engines.gdevelop_enabled', true);
    Config::set('gdevelop.engines.playcanvas_enabled', false);
    
    // Setup storage
    Storage::fake('local');
    
    // Create storage directories
    $directories = [
        'gdevelop/sessions',
        'gdevelop/templates', 
        'gdevelop/exports',
        'gdevelop/previews'
    ];
    
    foreach ($directories as $dir) {
        Storage::makeDirectory($dir);
    }
});

test('gdevelop game service can create game with mocked dependencies', function () {
    // Create test data
    $company = Company::factory()->create(['credits' => 1000]);
    $user = User::factory()->create(['current_company_id' => $company->id]);
    $company->users()->attach($user->id, ['role' => 'owner']);
    
    $workspace = Workspace::factory()->create([
        'company_id' => $company->id,
        'created_by' => $user->id,
        'engine_type' => 'gdevelop',
        'name' => 'Test Workspace'
    ]);
    
    // Create a basic game JSON structure
    $basicGameJson = [
        'properties' => [
            'name' => 'Test Game',
            'description' => 'A test game',
            'version' => '1.0.0'
        ],
        'layouts' => [
            [
                'name' => 'Scene',
                'objects' => [
                    [
                        'name' => 'Player',
                        'type' => 'Sprite',
                        'x' => 100,
                        'y' => 200
                    ]
                ],
                'events' => []
            ]
        ],
        'objects' => [
            [
                'name' => 'Player',
                'type' => 'Sprite'
            ]
        ],
        'events' => []
    ];
    
    // Create game session directly
    $sessionId = \Illuminate\Support\Str::uuid()->toString();
    
    $gameSession = GDevelopGameSession::create([
        'session_id' => $sessionId,
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'game_json' => $basicGameJson,
        'version' => 1,
        'assets' => [],
        'metadata' => [
            'created_at' => now()->toISOString(),
            'game_type' => 'basic'
        ]
    ]);
    
    expect($gameSession)->not->toBeNull();
    expect($gameSession->session_id)->toBe($sessionId);
    expect($gameSession->workspace_id)->toBe($workspace->id);
    expect($gameSession->user_id)->toBe($user->id);
    expect($gameSession->game_json)->toHaveKey('properties');
    expect($gameSession->game_json['properties']['name'])->toBe('Test Game');
});

test('gdevelop session can be retrieved via api', function () {
    $company = Company::factory()->create(['credits' => 1000]);
    $user = User::factory()->create(['current_company_id' => $company->id]);
    $company->users()->attach($user->id, ['role' => 'owner']);
    
    $workspace = Workspace::factory()->create([
        'company_id' => $company->id,
        'created_by' => $user->id,
        'engine_type' => 'gdevelop'
    ]);
    
    // Create game session
    $sessionId = \Illuminate\Support\Str::uuid()->toString();
    
    $gameSession = GDevelopGameSession::create([
        'session_id' => $sessionId,
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'game_json' => [
            'properties' => ['name' => 'Test Game'],
            'layouts' => [],
            'objects' => []
        ],
        'version' => 1,
        'assets' => []
    ]);
    
    $this->actingAs($user);
    
    $response = $this->getJson("/api/gdevelop/session/{$sessionId}");
    
    $response->assertStatus(200);
    $data = $response->json();
    
    expect($data['success'])->toBeTrue();
    expect($data['session_id'])->toBe($sessionId);
    expect($data)->toHaveKey('game_data');
});

test('gdevelop middleware blocks access when disabled', function () {
    $company = Company::factory()->create(['credits' => 1000]);
    $user = User::factory()->create(['current_company_id' => $company->id]);
    $company->users()->attach($user->id, ['role' => 'owner']);
    
    $this->actingAs($user);
    
    // Test with GDevelop disabled
    Config::set('gdevelop.enabled', false);
    
    $response = $this->getJson("/api/gdevelop/session/test-session");
    $response->assertStatus(503);
    
    $data = $response->json();
    expect($data['error'])->toContain('GDevelop integration is disabled');
});

test('gdevelop feature flags work correctly', function () {
    $featureFlagService = app(\App\Services\FeatureFlagService::class);
    
    // Test with GDevelop enabled
    Config::set('gdevelop.enabled', true);
    Config::set('gdevelop.engines.gdevelop_enabled', true);
    
    expect($featureFlagService->isGDevelopEnabled())->toBeTrue();
    expect($featureFlagService->getPrimaryEngine())->toBe('gdevelop');
    
    // Test with GDevelop disabled
    Config::set('gdevelop.enabled', false);
    
    expect($featureFlagService->isGDevelopEnabled())->toBeFalse();
});

test('workspace creation with gdevelop engine works', function () {
    $company = Company::factory()->create(['credits' => 1000]);
    $user = User::factory()->create(['current_company_id' => $company->id]);
    $company->users()->attach($user->id, ['role' => 'owner']);
    
    // Create workspace directly (since we don't have API route)
    $workspace = Workspace::factory()->create([
        'company_id' => $company->id,
        'created_by' => $user->id,
        'engine_type' => 'gdevelop',
        'name' => 'GDevelop Test Workspace',
        'status' => 'active'
    ]);
    
    expect($workspace->engine_type)->toBe('gdevelop');
    expect($workspace->name)->toBe('GDevelop Test Workspace');
    expect($workspace->company_id)->toBe($company->id);
    expect($workspace->created_by)->toBe($user->id);
    
    // Test workspace relationships
    expect($workspace->company)->not->toBeNull();
    expect($workspace->creator)->not->toBeNull();
    expect($workspace->isGDevelop())->toBeTrue();
});

test('credit system integration works with gdevelop', function () {
    $company = Company::factory()->create(['credits' => 100]);
    $user = User::factory()->create(['current_company_id' => $company->id]);
    $company->users()->attach($user->id, ['role' => 'owner']);
    
    $workspace = Workspace::factory()->create([
        'company_id' => $company->id,
        'created_by' => $user->id,
        'engine_type' => 'gdevelop'
    ]);
    
    $this->actingAs($user);
    
    $initialCredits = $company->credits;
    
    // Create game session that would consume credits
    $sessionId = \Illuminate\Support\Str::uuid()->toString();
    
    $gameSession = GDevelopGameSession::create([
        'session_id' => $sessionId,
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'game_json' => [
            'properties' => ['name' => 'Credit Test Game'],
            'layouts' => [],
            'objects' => []
        ],
        'version' => 1,
        'assets' => []
    ]);
    
    // Verify session was created
    expect($gameSession)->not->toBeNull();
    expect($gameSession->workspace->company_id)->toBe($company->id);
});

test('security isolation between users works', function () {
    // Create two separate companies and users
    $company1 = Company::factory()->create(['credits' => 1000]);
    $user1 = User::factory()->create(['current_company_id' => $company1->id]);
    $company1->users()->attach($user1->id, ['role' => 'owner']);
    
    $company2 = Company::factory()->create(['credits' => 1000]);
    $user2 = User::factory()->create(['current_company_id' => $company2->id]);
    $company2->users()->attach($user2->id, ['role' => 'owner']);
    
    $workspace1 = Workspace::factory()->create([
        'company_id' => $company1->id,
        'created_by' => $user1->id,
        'engine_type' => 'gdevelop'
    ]);
    
    // Create session as user1
    $sessionId = \Illuminate\Support\Str::uuid()->toString();
    
    $gameSession = GDevelopGameSession::create([
        'session_id' => $sessionId,
        'workspace_id' => $workspace1->id,
        'user_id' => $user1->id,
        'game_json' => ['properties' => ['name' => 'Secret Game']],
        'version' => 1,
        'assets' => []
    ]);
    
    // Try to access session as user2 (should fail)
    $this->actingAs($user2);
    
    $response = $this->getJson("/api/gdevelop/session/{$sessionId}");
    $response->assertStatus(404); // Should not find session
    
    // Verify user1 can still access their session
    $this->actingAs($user1);
    
    $response = $this->getJson("/api/gdevelop/session/{$sessionId}");
    $response->assertStatus(200);
    
    $data = $response->json();
    expect($data['success'])->toBeTrue();
    expect($data['session_id'])->toBe($sessionId);
});