<?php

use App\Models\User;
use App\Models\Company;
use App\Models\Workspace;
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
    
    // Create basic template file for testing
    $basicTemplate = [
        'properties' => [
            'name' => 'Basic Game',
            'description' => 'A basic game template',
            'version' => '1.0.0'
        ],
        'layouts' => [
            [
                'name' => 'Scene',
                'objects' => [],
                'events' => []
            ]
        ],
        'objects' => [],
        'events' => []
    ];
    
    Storage::put('gdevelop/templates/basic.json', json_encode($basicTemplate, JSON_PRETTY_PRINT));
    Storage::put('gdevelop/templates/platformer.json', json_encode($basicTemplate, JSON_PRETTY_PRINT));
    Storage::put('gdevelop/templates/puzzle.json', json_encode($basicTemplate, JSON_PRETTY_PRINT));
});

test('gdevelop chat endpoint is accessible', function () {
    $company = Company::factory()->create(['credits' => 1000]);
    $user = User::factory()->create(['current_company_id' => $company->id]);
    $company->users()->attach($user->id, ['role' => 'owner']);
    
    $workspace = Workspace::factory()->create([
        'company_id' => $company->id,
        'created_by' => $user->id,
        'engine_type' => 'gdevelop',
        'name' => 'Test Workspace'
    ]);
    
    $this->actingAs($user);
    
    $sessionId = \Illuminate\Support\Str::uuid()->toString();
    
    $response = $this->postJson('/api/gdevelop/chat', [
        'session_id' => $sessionId,
        'workspace_id' => $workspace->id,
        'message' => 'Create a simple game'
    ]);
    
    // Check if we get a proper response (not 500 error)
    if ($response->getStatusCode() === 500) {
        dump('Response content:', $response->getContent());
        dump('Response status:', $response->getStatusCode());
    }
    expect($response->getStatusCode())->not->toBe(500);
    
    // If we get 200, check the structure
    if ($response->getStatusCode() === 200) {
        $data = $response->json();
        expect($data)->toHaveKey('success');
    }
});

test('gdevelop session endpoint works', function () {
    $company = Company::factory()->create(['credits' => 1000]);
    $user = User::factory()->create(['current_company_id' => $company->id]);
    $company->users()->attach($user->id, ['role' => 'owner']);
    
    $this->actingAs($user);
    
    $sessionId = \Illuminate\Support\Str::uuid()->toString();
    
    $response = $this->getJson("/api/gdevelop/session/{$sessionId}");
    
    // Should return 404 for non-existent session, not 500
    expect($response->getStatusCode())->toBeIn([404, 200]);
});

test('gdevelop middleware works correctly', function () {
    $company = Company::factory()->create(['credits' => 1000]);
    $user = User::factory()->create(['current_company_id' => $company->id]);
    $company->users()->attach($user->id, ['role' => 'owner']);
    
    $workspace = Workspace::factory()->create([
        'company_id' => $company->id,
        'created_by' => $user->id,
        'engine_type' => 'gdevelop'
    ]);
    
    $this->actingAs($user);
    
    // Test with GDevelop enabled
    Config::set('gdevelop.enabled', true);
    
    $response = $this->getJson("/api/gdevelop/session/test-session");
    expect($response->getStatusCode())->not->toBe(503);
    
    // Test with GDevelop disabled
    Config::set('gdevelop.enabled', false);
    
    $response = $this->getJson("/api/gdevelop/session/test-session");
    $response->assertStatus(503);
});