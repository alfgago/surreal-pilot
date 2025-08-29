<?php

use App\Models\User;
use App\Models\Company;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('assist controller includes thinking process in non-streaming response', function () {
    // Create test data
    $user = User::factory()->create();
    $company = Company::factory()->create(['user_id' => $user->id, 'credits' => 1000]);
    $user->companies()->attach($company, ['role' => 'owner']);
    $user->update(['current_company_id' => $company->id]);
    
    $workspace = Workspace::factory()->create([
        'company_id' => $company->id,
        'engine_type' => 'playcanvas'
    ]);

    // Mock HTTP requests for MCP
    Http::fake([
        '*' => Http::response(['success' => true, 'response' => 'Test response'], 200)
    ]);

    // Make request to assist endpoint
    $response = $this->actingAs($user)->postJson('/api/chat', [
        'messages' => [
            ['role' => 'user', 'content' => 'Create a tower defense game']
        ],
        'context' => [
            'workspace_id' => $workspace->id,
            'engine_type' => 'playcanvas'
        ],
        'stream' => false,
        'resolved_provider' => 'openai',
        'temperature' => 0.7,
        'max_tokens' => 1024
    ]);

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'success',
        'response',
        'thinking' => [
            'timestamp',
            'steps' => [
                '*' => [
                    'type',
                    'title',
                    'content',
                    'duration'
                ]
            ]
        ],
        'metadata'
    ]);

    // Verify thinking process contains expected steps
    $thinking = $response->json('thinking');
    expect($thinking['steps'])->toHaveCount(4);
    
    $stepTypes = collect($thinking['steps'])->pluck('type')->toArray();
    expect($stepTypes)->toEqual(['analysis', 'decision', 'implementation', 'validation']);
});

test('assist controller includes thinking process in streaming response', function () {
    // Create test data
    $user = User::factory()->create();
    $company = Company::factory()->create(['user_id' => $user->id, 'credits' => 1000]);
    $user->companies()->attach($company, ['role' => 'owner']);
    $user->update(['current_company_id' => $company->id]);
    
    $workspace = Workspace::factory()->create([
        'company_id' => $company->id,
        'engine_type' => 'playcanvas'
    ]);

    // Mock HTTP requests for MCP
    Http::fake([
        '*' => Http::response(['success' => true, 'response' => 'Test response'], 200)
    ]);

    // Make streaming request to assist endpoint
    $response = $this->actingAs($user)->postJson('/api/chat', [
        'messages' => [
            ['role' => 'user', 'content' => 'Create a platformer game']
        ],
        'context' => [
            'workspace_id' => $workspace->id,
            'engine_type' => 'playcanvas'
        ],
        'stream' => true,
        'resolved_provider' => 'openai',
        'temperature' => 0.7,
        'max_tokens' => 1024
    ]);

    $response->assertStatus(200);
    $response->assertHeader('content-type', 'text/event-stream; charset=UTF-8');
});

test('thinking process analyzes tower defense requests correctly', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create(['user_id' => $user->id, 'credits' => 1000]);
    $user->companies()->attach($company, ['role' => 'owner']);
    $user->update(['current_company_id' => $company->id]);
    
    $workspace = Workspace::factory()->create([
        'company_id' => $company->id,
        'engine_type' => 'playcanvas'
    ]);

    Http::fake([
        '*' => Http::response(['success' => true, 'response' => 'Test response'], 200)
    ]);

    $response = $this->actingAs($user)->postJson('/api/chat', [
        'messages' => [
            ['role' => 'user', 'content' => 'Create a tower defense game with multiple tower types']
        ],
        'context' => [
            'workspace_id' => $workspace->id,
            'engine_type' => 'playcanvas'
        ],
        'stream' => false,
        'resolved_provider' => 'openai',
        'temperature' => 0.7,
        'max_tokens' => 1024
    ]);

    $response->assertStatus(200);
    
    $thinking = $response->json('thinking');
    $analysisStep = collect($thinking['steps'])->firstWhere('type', 'analysis');
    
    expect(strtolower($analysisStep['content']))->toContain('tower defense');
    expect(strtolower($analysisStep['content']))->toContain('towers');
    expect(strtolower($analysisStep['content']))->toContain('enemies');
});