<?php

use App\Models\User;
use App\Models\Company;
use App\Models\Game;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('complete user flow from registration to game creation', function () {
    // Step 1: User Registration
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'testuser@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);
    
    $response->assertRedirect('/engine-selection');
    
    $user = User::where('email', 'testuser@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->name)->toBe('Test User');
    expect($user->currentCompany)->not->toBeNull();
    
    // Step 2: Engine Selection (PlayCanvas)
    $response = $this->actingAs($user)->post('/engine-selection', [
        'engine_type' => 'playcanvas'
    ]);
    
    $response->assertRedirect('/workspace-selection');
    
    // Step 3: Workspace Creation
    $response = $this->actingAs($user)->post('/workspace-selection/create', [
        'name' => 'Test Workspace',
        'template' => 'blank'
    ]);
    
    $response->assertRedirect('/chat');
    
    // Step 4: Chat Interaction (simulate AI response)
    $response = $this->actingAs($user)->post('/api/chat', [
        'message' => 'Create a simple tetris-like game',
        'workspace_id' => 1
    ]);
    
    $response->assertStatus(200);
    
    // Verify conversation was created
    $conversation = ChatConversation::where('user_id', $user->id)->first();
    expect($conversation)->not->toBeNull();
    
    $messages = ChatMessage::where('conversation_id', $conversation->id)->get();
    expect($messages->count())->toBeGreaterThan(0);
    
    // Step 5: Game Creation
    $response = $this->actingAs($user)->post('/api/workspaces/1/games', [
        'name' => 'Tetris Game',
        'description' => 'A simple tetris-like game'
    ]);
    
    $response->assertStatus(201);
    
    $game = Game::where('name', 'Tetris Game')->first();
    expect($game)->not->toBeNull();
    expect($game->user_id)->toBe($user->id);
    
    // Step 6: Game Publishing
    $response = $this->actingAs($user)->post("/api/games/{$game->id}/publish", [
        'is_public' => true
    ]);
    
    $response->assertStatus(200);
    
    $game->refresh();
    expect($game->is_published)->toBeTrue();
    expect($game->share_token)->not->toBeNull();
    
    // Step 7: Test Share Link
    $response = $this->get("/games/shared/{$game->share_token}");
    $response->assertStatus(200);
    
    // Verify the complete flow worked
    expect($user->currentCompany->credits)->toBeLessThan(1000); // Credits were used
    expect($game->is_published)->toBeTrue();
    expect($conversation->messages()->count())->toBeGreaterThan(0);
});

test('billing and subscription flow', function () {
    // Create test user with company
    $user = User::factory()->create();
    $company = Company::factory()->create(['user_id' => $user->id]);
    $user->update(['current_company_id' => $company->id]);
    $company->users()->attach($user->id, ['role' => 'owner']);
    
    // Test credit balance endpoint
    $response = $this->actingAs($user)->get('/api/billing/balance');
    $response->assertStatus(200);
    $response->assertJsonStructure(['credits', 'plan']);
    
    // Test subscription endpoint
    $response = $this->actingAs($user)->get('/api/billing/subscription');
    $response->assertStatus(200);
    
    // Test transaction history
    $response = $this->actingAs($user)->get('/api/billing/transactions');
    $response->assertStatus(200);
    $response->assertJsonStructure(['data']);
    
    // Test billing analytics
    $response = $this->actingAs($user)->get('/api/billing/analytics');
    $response->assertStatus(200);
});

test('team collaboration features', function () {
    // Create owner user and company
    $owner = User::factory()->create();
    $company = Company::factory()->create(['user_id' => $owner->id]);
    $owner->update(['current_company_id' => $company->id]);
    $company->users()->attach($owner->id, ['role' => 'owner']);
    
    // Create team member
    $member = User::factory()->create();
    $company->users()->attach($member->id, ['role' => 'member']);
    $member->update(['current_company_id' => $company->id]);
    
    // Test company settings access
    $response = $this->actingAs($owner)->get('/company/settings');
    $response->assertStatus(200);
    
    // Test member can access workspace
    $response = $this->actingAs($member)->get('/workspace-selection');
    $response->assertStatus(200);
    
    // Test collaboration stats
    $response = $this->actingAs($owner)->get('/api/workspaces/1/collaboration-stats');
    $response->assertStatus(200);
});

test('mobile responsive functionality', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create(['user_id' => $user->id]);
    $user->update(['current_company_id' => $company->id]);
    $company->users()->attach($user->id, ['role' => 'owner']);
    
    // Test mobile API endpoints
    $response = $this->actingAs($user)->get('/api/mobile/demos');
    $response->assertStatus(200);
    
    $response = $this->actingAs($user)->get('/api/mobile/device-info');
    $response->assertStatus(200);
    
    $response = $this->actingAs($user)->get('/api/mobile/playcanvas-suggestions');
    $response->assertStatus(200);
});

test('performance and error handling', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create(['user_id' => $user->id]);
    $user->update(['current_company_id' => $company->id]);
    $company->users()->attach($user->id, ['role' => 'owner']);
    
    // Test API error handling
    $response = $this->actingAs($user)->get('/api/games/999999');
    $response->assertStatus(404);
    
    // Test validation errors
    $response = $this->actingAs($user)->post('/api/workspaces/1/games', []);
    $response->assertStatus(422);
    
    // Test unauthorized access
    $response = $this->get('/api/user');
    $response->assertStatus(401);
    
    // Test performance endpoints
    $response = $this->actingAs($user)->get('/api/credits/analytics');
    $response->assertStatus(200);
    
    $response = $this->actingAs($user)->get('/api/workspaces/stats');
    $response->assertStatus(200);
});