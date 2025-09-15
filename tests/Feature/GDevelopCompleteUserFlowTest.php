<?php

use App\Models\User;
use App\Models\Company;
use App\Models\Workspace;
use App\Models\GDevelopGameSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;

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

test('complete user flow from registration to game export works', function () {
    // Step 1: Create user and company (simulating registration)
    $company = Company::factory()->create([
        'name' => 'Test Game Studio',
        'credits' => 1000
    ]);
    
    $user = User::factory()->create([
        'name' => 'Test Developer',
        'email' => 'developer@testgamestudio.com',
        'password' => Hash::make('password123'),
        'current_company_id' => $company->id
    ]);
    
    $company->users()->attach($user->id, ['role' => 'owner']);
    
    // Step 2: User authentication
    $this->actingAs($user);
    
    // Step 3: Create GDevelop workspace directly (since API route doesn't exist)
    $workspace = Workspace::factory()->create([
        'company_id' => $company->id,
        'created_by' => $user->id,
        'engine_type' => 'gdevelop',
        'name' => 'My First Game Project',
        'status' => 'active'
    ]);
    
    expect($workspace->engine_type)->toBe('gdevelop');
    expect($workspace->name)->toBe('My First Game Project');
    
    // Step 4: Create game through chat
    $sessionId = \Illuminate\Support\Str::uuid()->toString();
    
    $chatResponse = $this->postJson('/api/gdevelop/chat', [
        'session_id' => $sessionId,
        'workspace_id' => $workspace->id,
        'message' => 'Create a simple platformer game with a blue player character that can jump and move left and right. Add some platforms to jump on and collect coins.'
    ]);
    
    $chatResponse->assertStatus(200);
    $chatData = $chatResponse->json();
    
    expect($chatData['success'])->toBeTrue();
    expect($chatData['session_id'])->toBe($sessionId);
    expect($chatData)->toHaveKey('game_data');
    expect($chatData)->toHaveKey('preview_url');
    expect($chatData)->toHaveKey('actions');
    
    // Verify game session was created
    $gameSession = GDevelopGameSession::where('session_id', $sessionId)->first();
    expect($gameSession)->not->toBeNull();
    expect($gameSession->workspace_id)->toBe($workspace->id);
    expect($gameSession->user_id)->toBe($user->id);
    expect($gameSession->game_json)->not->toBeNull();
    
    // Step 5: Modify game through additional chat
    $modifyResponse = $this->postJson('/api/gdevelop/chat', [
        'session_id' => $sessionId,
        'workspace_id' => $workspace->id,
        'message' => 'Add enemies that move back and forth on the platforms. Make them red and make the player restart when touching them.'
    ]);
    
    $modifyResponse->assertStatus(200);
    $modifyData = $modifyResponse->json();
    
    expect($modifyData['success'])->toBeTrue();
    expect($modifyData['session_id'])->toBe($sessionId);
    
    // Verify game was modified (version should increment)
    $updatedSession = GDevelopGameSession::where('session_id', $sessionId)->first();
    expect($updatedSession->version)->toBeGreaterThan($gameSession->version);
    
    // Step 6: Generate preview
    $previewResponse = $this->getJson("/api/gdevelop/preview/{$sessionId}");
    
    $previewResponse->assertStatus(200);
    $previewData = $previewResponse->json();
    
    expect($previewData['success'])->toBeTrue();
    expect($previewData)->toHaveKey('preview_url');
    expect($previewData)->toHaveKey('build_time');
    
    // Step 7: Export game
    $exportResponse = $this->postJson("/api/gdevelop/export/{$sessionId}", [
        'includeAssets' => true,
        'optimizeForMobile' => false,
        'compressionLevel' => 'standard'
    ]);
    
    $exportResponse->assertStatus(200);
    $exportData = $exportResponse->json();
    
    expect($exportData['success'])->toBeTrue();
    expect($exportData)->toHaveKey('export_id');
    
    // Step 8: Check export status
    $statusResponse = $this->getJson("/api/gdevelop/export/{$sessionId}/status");
    $statusResponse->assertStatus(200);
    
    $statusData = $statusResponse->json();
    expect($statusData['success'])->toBeTrue();
    expect($statusData)->toHaveKey('status');
    expect($statusData['status'])->toBeIn(['queued', 'processing', 'completed', 'failed']);
    
    // Step 9: Verify credits were deducted
    $company->refresh();
    expect($company->credits)->toBeLessThan(1000);
    
    // Step 10: Verify session can be retrieved
    $sessionResponse = $this->getJson("/api/gdevelop/session/{$sessionId}");
    $sessionResponse->assertStatus(200);
    
    $sessionData = $sessionResponse->json();
    expect($sessionData['success'])->toBeTrue();
    expect($sessionData['session_id'])->toBe($sessionId);
    expect($sessionData)->toHaveKey('game_data');
});

test('mobile optimization workflow works correctly', function () {
    // Setup user and workspace
    $company = Company::factory()->create(['credits' => 1000]);
    $user = User::factory()->create(['current_company_id' => $company->id]);
    $company->users()->attach($user->id, ['role' => 'owner']);
    
    $workspace = Workspace::factory()->create([
        'company_id' => $company->id,
        'created_by' => $user->id,
        'engine_type' => 'gdevelop',
        'name' => 'Mobile Game Project'
    ]);
    
    $this->actingAs($user);
    
    $sessionId = \Illuminate\Support\Str::uuid()->toString();
    
    // Create mobile-optimized game
    $response = $this->postJson('/api/gdevelop/chat', [
        'session_id' => $sessionId,
        'workspace_id' => $workspace->id,
        'message' => 'Create a mobile-friendly puzzle game with large touch-friendly buttons and simple tap controls',
        'options' => [
            'mobile_optimized' => true,
            'target_device' => 'mobile',
            'control_scheme' => 'touch_direct',
            'orientation' => 'portrait'
        ]
    ]);
    
    $response->assertStatus(200);
    $data = $response->json();
    
    expect($data['success'])->toBeTrue();
    
    // Verify mobile optimization was applied
    $gameSession = GDevelopGameSession::where('session_id', $sessionId)->first();
    expect($gameSession)->not->toBeNull();
    
    $gameJson = $gameSession->game_json;
    expect($gameJson)->toHaveKey('properties');
    
    // Test mobile export
    $exportResponse = $this->postJson("/api/gdevelop/export/{$sessionId}", [
        'includeAssets' => true,
        'optimizeForMobile' => true,
        'compressionLevel' => 'standard'
    ]);
    
    $exportResponse->assertStatus(200);
    $exportData = $exportResponse->json();
    expect($exportData['success'])->toBeTrue();
});

test('error handling works correctly throughout the flow', function () {
    $company = Company::factory()->create(['credits' => 1000]);
    $user = User::factory()->create(['current_company_id' => $company->id]);
    $company->users()->attach($user->id, ['role' => 'owner']);
    
    $workspace = Workspace::factory()->create([
        'company_id' => $company->id,
        'created_by' => $user->id,
        'engine_type' => 'gdevelop'
    ]);
    
    $this->actingAs($user);
    
    // Test with insufficient credits
    $company->update(['credits' => 1]);
    
    $response = $this->postJson('/api/gdevelop/chat', [
        'session_id' => \Illuminate\Support\Str::uuid()->toString(),
        'workspace_id' => $workspace->id,
        'message' => 'Create a very complex RPG game with multiple systems'
    ]);
    
    $response->assertStatus(402);
    $data = $response->json();
    expect($data['success'])->toBeFalse();
    expect($data['error'])->toContain('Insufficient credits');
    
    // Restore credits and test invalid session access
    $company->update(['credits' => 1000]);
    
    $invalidResponse = $this->getJson('/api/gdevelop/preview/invalid-session-id');
    $invalidResponse->assertStatus(404);
    
    // Test with disabled GDevelop
    Config::set('gdevelop.enabled', false);
    
    $disabledResponse = $this->postJson('/api/gdevelop/chat', [
        'session_id' => \Illuminate\Support\Str::uuid()->toString(),
        'workspace_id' => $workspace->id,
        'message' => 'Create a game'
    ]);
    
    $disabledResponse->assertStatus(503);
    $disabledData = $disabledResponse->json();
    expect($disabledData['error'])->toContain('GDevelop integration is disabled');
});

test('security measures prevent unauthorized access', function () {
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
    $this->actingAs($user1);
    
    $sessionId = \Illuminate\Support\Str::uuid()->toString();
    
    $response = $this->postJson('/api/gdevelop/chat', [
        'session_id' => $sessionId,
        'workspace_id' => $workspace1->id,
        'message' => 'Create a secret game'
    ]);
    
    $response->assertStatus(200);
    
    // Try to access session as user2 (should fail)
    $this->actingAs($user2);
    
    $accessResponse = $this->getJson("/api/gdevelop/session/{$sessionId}");
    $accessResponse->assertStatus(404); // Should not find session
    
    // Try to access user1's workspace as user2 (should fail)
    $unauthorizedResponse = $this->postJson('/api/gdevelop/chat', [
        'session_id' => \Illuminate\Support\Str::uuid()->toString(),
        'workspace_id' => $workspace1->id,
        'message' => 'Try to hack the game'
    ]);
    
    // Should either be forbidden or not found
    expect($unauthorizedResponse->getStatusCode())->toBeIn([403, 404]);
});

test('input sanitization prevents malicious content', function () {
    $company = Company::factory()->create(['credits' => 1000]);
    $user = User::factory()->create(['current_company_id' => $company->id]);
    $company->users()->attach($user->id, ['role' => 'owner']);
    
    $workspace = Workspace::factory()->create([
        'company_id' => $company->id,
        'created_by' => $user->id,
        'engine_type' => 'gdevelop'
    ]);
    
    $this->actingAs($user);
    
    $maliciousInputs = [
        '<script>alert("xss")</script>',
        'javascript:alert(1)',
        '"; DROP TABLE users; --',
        '../../../etc/passwd'
    ];
    
    foreach ($maliciousInputs as $input) {
        $response = $this->postJson('/api/gdevelop/chat', [
            'session_id' => \Illuminate\Support\Str::uuid()->toString(),
            'workspace_id' => $workspace->id,
            'message' => "Create a game with title: {$input}"
        ]);
        
        // Should either succeed with sanitized input or fail gracefully
        expect($response->getStatusCode())->toBeIn([200, 422]);
        
        if ($response->getStatusCode() === 200) {
            $data = $response->json();
            $gameData = $data['game_data'];
            
            // Verify malicious content was sanitized
            $gameJson = json_encode($gameData);
            expect($gameJson)->not->toContain('<script>');
            expect($gameJson)->not->toContain('javascript:');
            expect($gameJson)->not->toContain('DROP TABLE');
            expect($gameJson)->not->toContain('/etc/passwd');
        }
    }
});

test('performance requirements are met', function () {
    $company = Company::factory()->create(['credits' => 1000]);
    $user = User::factory()->create(['current_company_id' => $company->id]);
    $company->users()->attach($user->id, ['role' => 'owner']);
    
    $workspace = Workspace::factory()->create([
        'company_id' => $company->id,
        'created_by' => $user->id,
        'engine_type' => 'gdevelop'
    ]);
    
    $this->actingAs($user);
    
    $sessionId = \Illuminate\Support\Str::uuid()->toString();
    
    // Test game creation performance
    $startTime = microtime(true);
    
    $response = $this->postJson('/api/gdevelop/chat', [
        'session_id' => $sessionId,
        'workspace_id' => $workspace->id,
        'message' => 'Create a platformer game with multiple levels and power-ups'
    ]);
    
    $creationTime = microtime(true) - $startTime;
    
    $response->assertStatus(200);
    expect($creationTime)->toBeLessThan(30); // Should complete within 30 seconds
    
    // Test preview generation performance
    $startTime = microtime(true);
    
    $previewResponse = $this->getJson("/api/gdevelop/preview/{$sessionId}");
    
    $previewTime = microtime(true) - $startTime;
    
    $previewResponse->assertStatus(200);
    expect($previewTime)->toBeLessThan(10); // Should complete within 10 seconds
    
    // Check build time from response
    $previewData = $previewResponse->json();
    if (isset($previewData['build_time'])) {
        expect($previewData['build_time'])->toBeLessThan(5000); // Build time should be under 5 seconds
    }
});

test('feature flags control access correctly', function () {
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
    
    $response = $this->postJson('/api/gdevelop/chat', [
        'session_id' => \Illuminate\Support\Str::uuid()->toString(),
        'workspace_id' => $workspace->id,
        'message' => 'Create a simple game'
    ]);
    
    expect($response->getStatusCode())->not->toBe(503);
    
    // Test with GDevelop disabled
    Config::set('gdevelop.enabled', false);
    
    $disabledResponse = $this->postJson('/api/gdevelop/chat', [
        'session_id' => \Illuminate\Support\Str::uuid()->toString(),
        'workspace_id' => $workspace->id,
        'message' => 'Create a simple game'
    ]);
    
    $disabledResponse->assertStatus(503);
    $data = $disabledResponse->json();
    expect($data['error'])->toContain('GDevelop integration is disabled');
});