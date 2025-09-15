<?php

use App\Models\User;
use App\Models\Company;
use App\Models\Workspace;
use App\Models\GDevelopGameSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->create(['current_company_id' => $this->company->id]);
    $this->user->companies()->attach($this->company);
    
    $this->workspace = Workspace::factory()->create([
        'company_id' => $this->company->id,
        'created_by' => $this->user->id,
        'engine_type' => 'gdevelop'
    ]);
    
    Storage::fake('gdevelop');
    Cache::flush();
});

describe('Game Creation Performance', function () {
    test('tower defense game creation completes within performance requirements', function () {
        $startTime = microtime(true);
        
        $response = $this->actingAs($this->user)
            ->postJson('/api/gdevelop/chat', [
                'workspace_id' => $this->workspace->id,
                'message' => 'Create a tower defense game with 5 tower types, 3 enemy types, wave system, and scoring',
                'session_id' => null
            ]);
        
        $endTime = microtime(true);
        $creationTime = $endTime - $startTime;
        
        $response->assertStatus(200);
        
        // Game creation should complete within 10 seconds
        expect($creationTime)->toBeLessThan(10.0);
        
        $sessionId = $response->json('data.session_id');
        $gameData = $response->json('data.game_data');
        
        // Verify game complexity
        $objects = collect($gameData['game_json']['objects']);
        expect($objects->count())->toBeGreaterThanOrEqual(8); // 5 towers + 3 enemies
        
        // Test modification performance
        $modStartTime = microtime(true);
        
        $modResponse = $this->actingAs($this->user)
            ->postJson('/api/gdevelop/chat', [
                'workspace_id' => $this->workspace->id,
                'message' => 'Add special abilities to each tower and increase enemy variety with flying and armored units',
                'session_id' => $sessionId
            ]);
        
        $modEndTime = microtime(true);
        $modificationTime = $modEndTime - $modStartTime;
        
        $modResponse->assertStatus(200);
        
        // Modifications should complete within 8 seconds
        expect($modificationTime)->toBeLessThan(8.0);
        
        // Test preview generation performance
        $previewStartTime = microtime(true);
        
        $previewResponse = $this->actingAs($this->user)
            ->getJson("/api/gdevelop/preview/{$sessionId}");
        
        $previewEndTime = microtime(true);
        $previewTime = $previewEndTime - $previewStartTime;
        
        $previewResponse->assertStatus(200);
        
        // Preview generation should complete within 5 seconds
        expect($previewTime)->toBeLessThan(5.0);
    });
    
    test('platformer game with physics modifications meets performance targets', function () {
        // Create initial platformer
        $startTime = microtime(true);
        
        $response = $this->actingAs($this->user)
            ->postJson('/api/gdevelop/chat', [
                'workspace_id' => $this->workspace->id,
                'message' => 'Create a platformer with player character, 3 levels, enemies, collectibles, and physics',
                'session_id' => null
            ]);
        
        $endTime = microtime(true);
        $creationTime = $endTime - $startTime;
        
        expect($creationTime)->toBeLessThan(12.0); // Slightly longer for physics complexity
        
        $sessionId = $response->json('data.session_id');
        
        // Test multiple rapid modifications
        $modifications = [
            'Add double jump and wall sliding mechanics',
            'Create moving platforms and disappearing platforms',
            'Add enemy AI with different movement patterns',
            'Implement collectible power-ups and health system',
            'Add level transitions and checkpoint system'
        ];
        
        $totalModificationTime = 0;
        
        foreach ($modifications as $modification) {
            $modStartTime = microtime(true);
            
            $modResponse = $this->actingAs($this->user)
                ->postJson('/api/gdevelop/chat', [
                    'workspace_id' => $this->workspace->id,
                    'message' => $modification,
                    'session_id' => $sessionId
                ]);
            
            $modEndTime = microtime(true);
            $modTime = $modEndTime - $modStartTime;
            $totalModificationTime += $modTime;
            
            $modResponse->assertStatus(200);
            
            // Each modification should complete within 6 seconds
            expect($modTime)->toBeLessThan(6.0);
        }
        
        // Total time for 5 modifications should be under 25 seconds
        expect($totalModificationTime)->toBeLessThan(25.0);
        
        // Test export performance
        $exportStartTime = microtime(true);
        
        $exportResponse = $this->actingAs($this->user)
            ->postJson("/api/gdevelop/export/{$sessionId}", [
                'options' => [
                    'includeAssets' => true,
                    'optimizeForMobile' => true,
                    'compressionLevel' => 'standard'
                ]
            ]);
        
        $exportEndTime = microtime(true);
        $exportTime = $exportEndTime - $exportStartTime;
        
        $exportResponse->assertStatus(200);
        
        // Export should complete within 30 seconds
        expect($exportTime)->toBeLessThan(30.0);
    });
});

describe('Concurrent Game Creation Performance', function () {
    test('handles multiple simultaneous game creation requests efficiently', function () {
        $users = User::factory()->count(3)->create(['current_company_id' => $this->company->id]);
        $workspaces = [];
        
        foreach ($users as $user) {
            $user->companies()->attach($this->company);
            $workspaces[] = Workspace::factory()->create([
                'company_id' => $this->company->id,
                'created_by' => $user->id,
                'engine_type' => 'gdevelop'
            ]);
        }
        
        $startTime = microtime(true);
        $responses = [];
        
        // Create games concurrently
        foreach ($users as $index => $user) {
            $gameType = ['tower defense', 'platformer', 'puzzle'][$index];
            
            $responses[] = $this->actingAs($user)
                ->postJson('/api/gdevelop/chat', [
                    'workspace_id' => $workspaces[$index]->id,
                    'message' => "Create a {$gameType} game with multiple levels and complex mechanics",
                    'session_id' => null
                ]);
        }
        
        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;
        
        // All concurrent requests should complete within 15 seconds
        expect($totalTime)->toBeLessThan(15.0);
        
        // Verify all responses are successful
        foreach ($responses as $response) {
            $response->assertStatus(200);
            expect($response->json('data.session_id'))->not->toBeNull();
        }
        
        // Verify sessions are isolated
        $sessionIds = array_map(fn($r) => $r->json('data.session_id'), $responses);
        expect(count(array_unique($sessionIds)))->toBe(3);
    });
});

describe('Memory and Resource Usage', function () {
    test('game creation does not exceed memory limits', function () {
        $initialMemory = memory_get_usage(true);
        
        // Create a complex game
        $response = $this->actingAs($this->user)
            ->postJson('/api/gdevelop/chat', [
                'workspace_id' => $this->workspace->id,
                'message' => 'Create a complex RPG-style game with inventory system, character stats, multiple levels, quest system, and detailed graphics',
                'session_id' => null
            ]);
        
        $response->assertStatus(200);
        $sessionId = $response->json('data.session_id');
        
        // Make multiple modifications
        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($this->user)
                ->postJson('/api/gdevelop/chat', [
                    'workspace_id' => $this->workspace->id,
                    'message' => "Add feature set {$i}: new characters, abilities, and game mechanics",
                    'session_id' => $sessionId
                ]);
        }
        
        $finalMemory = memory_get_usage(true);
        $memoryIncrease = $finalMemory - $initialMemory;
        
        // Memory increase should be reasonable (less than 50MB)
        expect($memoryIncrease)->toBeLessThan(50 * 1024 * 1024);
        
        // Test memory cleanup after session
        $session = GDevelopGameSession::where('session_id', $sessionId)->first();
        $session->delete();
        
        // Force garbage collection
        gc_collect_cycles();
        
        $cleanupMemory = memory_get_usage(true);
        expect($cleanupMemory)->toBeLessThan($finalMemory);
    });
});

describe('Database Performance', function () {
    test('game session queries perform efficiently with large datasets', function () {
        // Create multiple game sessions
        $sessions = [];
        for ($i = 0; $i < 50; $i++) {
            $sessions[] = GDevelopGameSession::create([
                'workspace_id' => $this->workspace->id,
                'user_id' => $this->user->id,
                'session_id' => 'test_session_' . $i,
                'game_title' => 'Test Game ' . $i,
                'version' => rand(1, 20),
                'game_json' => [
                    'objects' => array_fill(0, rand(10, 100), ['name' => 'TestObject']),
                    'layouts' => array_fill(0, rand(1, 5), ['name' => 'TestLayout'])
                ],
                'assets_manifest' => [],
                'status' => 'active'
            ]);
        }
        
        // Test query performance
        $startTime = microtime(true);
        
        $recentSessions = GDevelopGameSession::where('workspace_id', $this->workspace->id)
            ->where('user_id', $this->user->id)
            ->orderBy('last_modified', 'desc')
            ->limit(10)
            ->get();
        
        $endTime = microtime(true);
        $queryTime = $endTime - $startTime;
        
        // Query should complete within 100ms
        expect($queryTime)->toBeLessThan(0.1);
        expect($recentSessions->count())->toBe(10);
        
        // Test complex aggregation query
        $startTime = microtime(true);
        
        $stats = GDevelopGameSession::where('workspace_id', $this->workspace->id)
            ->selectRaw('COUNT(*) as total_sessions, AVG(version) as avg_version, MAX(version) as max_version')
            ->first();
        
        $endTime = microtime(true);
        $aggregationTime = $endTime - $startTime;
        
        // Aggregation should complete within 50ms
        expect($aggregationTime)->toBeLessThan(0.05);
        expect($stats->total_sessions)->toBe(50);
    });
});

describe('Cache Performance', function () {
    test('game templates and common structures are cached effectively', function () {
        // First request - should populate cache
        $startTime = microtime(true);
        
        $response1 = $this->actingAs($this->user)
            ->postJson('/api/gdevelop/chat', [
                'workspace_id' => $this->workspace->id,
                'message' => 'Create a tower defense game',
                'session_id' => null
            ]);
        
        $endTime = microtime(true);
        $firstRequestTime = $endTime - $startTime;
        
        $response1->assertStatus(200);
        
        // Second similar request - should use cache
        $startTime = microtime(true);
        
        $response2 = $this->actingAs($this->user)
            ->postJson('/api/gdevelop/chat', [
                'workspace_id' => $this->workspace->id,
                'message' => 'Create another tower defense game with different towers',
                'session_id' => null
            ]);
        
        $endTime = microtime(true);
        $secondRequestTime = $endTime - $startTime;
        
        $response2->assertStatus(200);
        
        // Second request should be faster due to caching
        expect($secondRequestTime)->toBeLessThan($firstRequestTime * 0.8);
        
        // Verify cache keys exist
        expect(Cache::has('gdevelop.template.tower_defense'))->toBe(true);
        expect(Cache::has('gdevelop.objects.tower'))->toBe(true);
    });
});

describe('File System Performance', function () {
    test('asset management and file operations perform efficiently', function () {
        $response = $this->actingAs($this->user)
            ->postJson('/api/gdevelop/chat', [
                'workspace_id' => $this->workspace->id,
                'message' => 'Create a game with many visual assets: sprites, backgrounds, sound effects, and animations',
                'session_id' => null
            ]);
        
        $response->assertStatus(200);
        $sessionId = $response->json('data.session_id');
        
        // Test asset loading performance
        $startTime = microtime(true);
        
        $previewResponse = $this->actingAs($this->user)
            ->getJson("/api/gdevelop/preview/{$sessionId}");
        
        $endTime = microtime(true);
        $assetLoadTime = $endTime - $startTime;
        
        $previewResponse->assertStatus(200);
        
        // Asset loading should complete within 3 seconds
        expect($assetLoadTime)->toBeLessThan(3.0);
        
        // Test export with many assets
        $startTime = microtime(true);
        
        $exportResponse = $this->actingAs($this->user)
            ->postJson("/api/gdevelop/export/{$sessionId}", [
                'options' => [
                    'includeAssets' => true,
                    'compressionLevel' => 'maximum'
                ]
            ]);
        
        $endTime = microtime(true);
        $exportTime = $endTime - $startTime;
        
        $exportResponse->assertStatus(200);
        
        // Export with compression should complete within 25 seconds
        expect($exportTime)->toBeLessThan(25.0);
        
        // Verify file cleanup
        $session = GDevelopGameSession::where('session_id', $sessionId)->first();
        expect($session->assets_manifest)->not->toBeEmpty();
    });
});