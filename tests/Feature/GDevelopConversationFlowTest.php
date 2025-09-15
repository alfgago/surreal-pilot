<?php

use App\Models\User;
use App\Models\Company;
use App\Models\Workspace;
use App\Models\GDevelopGameSession;
use App\Models\ChatMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

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
});

describe('Complete Conversation Flow Validation', function () {
    test('validates complete tower defense conversation with stored messages and AI thinking', function () {
        // Track conversation messages
        $conversationMessages = [];
        
        // Initial game creation
        $initialMessage = "Create a tower defense game with 3 different tower types: basic shooter that fires bullets, splash tower that damages multiple enemies, and freeze tower that slows enemies down. Add enemies that spawn from the left side and follow a path to the right.";
        
        $response = $this->actingAs($this->user)
            ->postJson('/api/gdevelop/chat', [
                'workspace_id' => $this->workspace->id,
                'message' => $initialMessage,
                'session_id' => null
            ]);
        
        $response->assertStatus(200);
        $sessionId = $response->json('data.session_id');
        $aiResponse = $response->json('data.message');
        
        // Store conversation
        $conversationMessages[] = [
            'role' => 'user',
            'content' => $initialMessage,
            'timestamp' => now()
        ];
        $conversationMessages[] = [
            'role' => 'assistant',
            'content' => $aiResponse,
            'thinking_process' => $response->json('data.thinking_process'),
            'timestamp' => now()
        ];
        
        // Verify initial game structure
        $gameData = $response->json('data.game_data');
        expect($gameData['game_json']['objects'])->toHaveCount(4); // 3 towers + 1 enemy type
        
        // First feedback interaction - tower modifications
        $feedback1 = "Make the basic tower shoot twice as fast and increase its range. Also make the splash tower's explosion radius larger and add visual effects to show the splash damage area.";
        
        $response1 = $this->actingAs($this->user)
            ->postJson('/api/gdevelop/chat', [
                'workspace_id' => $this->workspace->id,
                'message' => $feedback1,
                'session_id' => $sessionId
            ]);
        
        $response1->assertStatus(200);
        $aiResponse1 = $response1->json('data.message');
        
        $conversationMessages[] = [
            'role' => 'user',
            'content' => $feedback1,
            'timestamp' => now()
        ];
        $conversationMessages[] = [
            'role' => 'assistant',
            'content' => $aiResponse1,
            'thinking_process' => $response1->json('data.thinking_process'),
            'timestamp' => now()
        ];
        
        // Verify modifications were applied
        $gameData1 = $response1->json('data.game_data');
        expect($gameData1['version'])->toBeGreaterThan($gameData['version']);
        
        // Second feedback interaction - enemy variety
        $feedback2 = "Add two new enemy types: fast enemies that move quickly but have low health, and armored enemies that move slowly but have high health and require more hits to defeat. Make sure the freeze tower affects both types differently.";
        
        $response2 = $this->actingAs($this->user)
            ->postJson('/api/gdevelop/chat', [
                'workspace_id' => $this->workspace->id,
                'message' => $feedback2,
                'session_id' => $sessionId
            ]);
        
        $response2->assertStatus(200);
        $aiResponse2 = $response2->json('data.message');
        
        $conversationMessages[] = [
            'role' => 'user',
            'content' => $feedback2,
            'timestamp' => now()
        ];
        $conversationMessages[] = [
            'role' => 'assistant',
            'content' => $aiResponse2,
            'thinking_process' => $response2->json('data.thinking_process'),
            'timestamp' => now()
        ];
        
        // Verify new enemy types
        $gameData2 = $response2->json('data.game_data');
        $objects = collect($gameData2['game_json']['objects']);
        expect($objects->where('name', 'like', '%Fast%')->count())->toBeGreaterThanOrEqual(1);
        expect($objects->where('name', 'like', '%Armored%')->count())->toBeGreaterThanOrEqual(1);
        
        // Third feedback interaction - game mechanics
        $feedback3 = "Add a wave system with 10 waves where each wave spawns more enemies and introduces new enemy combinations. Add a health system for the player's base (start with 20 health), a currency system for buying towers, and a score system that gives points for defeating enemies.";
        
        $response3 = $this->actingAs($this->user)
            ->postJson('/api/gdevelop/chat', [
                'workspace_id' => $this->workspace->id,
                'message' => $feedback3,
                'session_id' => $sessionId
            ]);
        
        $response3->assertStatus(200);
        $aiResponse3 = $response3->json('data.message');
        
        $conversationMessages[] = [
            'role' => 'user',
            'content' => $feedback3,
            'timestamp' => now()
        ];
        $conversationMessages[] = [
            'role' => 'assistant',
            'content' => $aiResponse3,
            'thinking_process' => $response3->json('data.thinking_process'),
            'timestamp' => now()
        ];
        
        // Verify game mechanics
        $gameData3 = $response3->json('data.game_data');
        $variables = collect($gameData3['game_json']['variables']);
        expect($variables->where('name', 'like', '%wave%')->count())->toBeGreaterThanOrEqual(1);
        expect($variables->where('name', 'like', '%health%')->count())->toBeGreaterThanOrEqual(1);
        expect($variables->where('name', 'like', '%currency%')->count())->toBeGreaterThanOrEqual(1);
        expect($variables->where('name', 'like', '%score%')->count())->toBeGreaterThanOrEqual(1);
        
        // Verify conversation storage
        $session = GDevelopGameSession::where('session_id', $sessionId)->first();
        expect($session)->not->toBeNull();
        expect($session->conversation_history)->not->toBeNull();
        
        $storedConversation = json_decode($session->conversation_history, true);
        expect($storedConversation)->toHaveCount(6); // 3 user messages + 3 AI responses
        
        // Verify each message is stored with proper structure
        foreach ($storedConversation as $index => $message) {
            expect($message)->toHaveKey('role');
            expect($message)->toHaveKey('content');
            expect($message)->toHaveKey('timestamp');
            
            if ($message['role'] === 'assistant') {
                expect($message)->toHaveKey('thinking_process');
                expect($message['thinking_process'])->not->toBeEmpty();
            }
        }
        
        // Verify conversation continuity
        expect($storedConversation[0]['content'])->toBe($initialMessage);
        expect($storedConversation[2]['content'])->toBe($feedback1);
        expect($storedConversation[4]['content'])->toBe($feedback2);
        expect($storedConversation[5]['content'])->toBe($aiResponse3);
        
        // Test conversation retrieval
        $conversationResponse = $this->actingAs($this->user)
            ->getJson("/api/gdevelop/conversation/{$sessionId}");
        
        $conversationResponse->assertStatus(200);
        $retrievedConversation = $conversationResponse->json('data.conversation');
        expect($retrievedConversation)->toHaveCount(6);
    });
    
    test('validates platformer conversation with complex physics modifications', function () {
        $conversationLog = [];
        
        // Initial platformer creation
        $initialRequest = "Create a 2D platformer game with a player character that can run left and right using arrow keys and jump using spacebar. Add solid platforms, collectible coins worth 10 points each, enemies that move back and forth on platforms, and a goal flag at the end of the level.";
        
        $response = $this->actingAs($this->user)
            ->postJson('/api/gdevelop/chat', [
                'workspace_id' => $this->workspace->id,
                'message' => $initialRequest,
                'session_id' => null
            ]);
        
        $response->assertStatus(200);
        $sessionId = $response->json('data.session_id');
        
        $conversationLog[] = [
            'user' => $initialRequest,
            'ai' => $response->json('data.message'),
            'thinking' => $response->json('data.thinking_process'),
            'game_version' => $response->json('data.game_data.version')
        ];
        
        // Physics modification 1
        $physicsRequest1 = "Increase the player's jump height by 50% and add a double jump ability. The player should be able to jump again while in mid-air, but only once per jump cycle.";
        
        $physicsResponse1 = $this->actingAs($this->user)
            ->postJson('/api/gdevelop/chat', [
                'workspace_id' => $this->workspace->id,
                'message' => $physicsRequest1,
                'session_id' => $sessionId
            ]);
        
        $physicsResponse1->assertStatus(200);
        
        $conversationLog[] = [
            'user' => $physicsRequest1,
            'ai' => $physicsResponse1->json('data.message'),
            'thinking' => $physicsResponse1->json('data.thinking_process'),
            'game_version' => $physicsResponse1->json('data.game_data.version')
        ];
        
        // Physics modification 2
        $physicsRequest2 = "Add wall jumping mechanics. When the player touches a wall while in the air, they should stick to it briefly and be able to jump off in the opposite direction. Also add wall sliding where the player slides down walls slowly.";
        
        $physicsResponse2 = $this->actingAs($this->user)
            ->postJson('/api/gdevelop/chat', [
                'workspace_id' => $this->workspace->id,
                'message' => $physicsRequest2,
                'session_id' => $sessionId
            ]);
        
        $physicsResponse2->assertStatus(200);
        
        $conversationLog[] = [
            'user' => $physicsRequest2,
            'ai' => $physicsResponse2->json('data.message'),
            'thinking' => $physicsResponse2->json('data.thinking_process'),
            'game_version' => $physicsResponse2->json('data.game_data.version')
        ];
        
        // Level design modification
        $levelRequest = "Create 3 different levels with increasing difficulty. Level 1 should be simple with basic platforms and a few enemies. Level 2 should add moving platforms and more complex enemy patterns. Level 3 should have challenging wall-jumping sections and multiple paths to the goal.";
        
        $levelResponse = $this->actingAs($this->user)
            ->postJson('/api/gdevelop/chat', [
                'workspace_id' => $this->workspace->id,
                'message' => $levelRequest,
                'session_id' => $sessionId
            ]);
        
        $levelResponse->assertStatus(200);
        
        $conversationLog[] = [
            'user' => $levelRequest,
            'ai' => $levelResponse->json('data.message'),
            'thinking' => $levelResponse->json('data.thinking_process'),
            'game_version' => $levelResponse->json('data.game_data.version')
        ];
        
        // Verify conversation progression
        expect($conversationLog)->toHaveCount(4);
        
        // Verify game version increments
        for ($i = 1; $i < count($conversationLog); $i++) {
            expect($conversationLog[$i]['game_version'])->toBeGreaterThan($conversationLog[$i-1]['game_version']);
        }
        
        // Verify AI thinking process is captured
        foreach ($conversationLog as $entry) {
            expect($entry['thinking'])->not->toBeEmpty();
            expect($entry['ai'])->not->toBeEmpty();
        }
        
        // Verify final game has all requested features
        $finalGameData = $levelResponse->json('data.game_data');
        expect($finalGameData['game_json']['layouts'])->toHaveCount(3); // 3 levels
        
        $objects = collect($finalGameData['game_json']['objects']);
        expect($objects->where('name', 'like', '%Player%')->count())->toBeGreaterThanOrEqual(1);
        expect($objects->where('name', 'like', '%Platform%')->count())->toBeGreaterThanOrEqual(2); // Regular and moving platforms
        
        // Test conversation persistence across sessions
        $session = GDevelopGameSession::where('session_id', $sessionId)->first();
        $storedConversation = json_decode($session->conversation_history, true);
        expect($storedConversation)->toHaveCount(8); // 4 user + 4 AI messages
    });
    
    test('validates puzzle game conversation with logic system iterations', function () {
        $conversationTracker = [];
        
        // Initial puzzle game creation
        $puzzleRequest = "Create a match-3 puzzle game with a 8x8 grid of colored gems (red, blue, green, yellow, purple). Players should be able to click and drag to swap adjacent gems. When 3 or more gems of the same color line up horizontally or vertically, they should disappear and new gems should fall from the top.";
        
        $response = $this->actingAs($this->user)
            ->postJson('/api/gdevelop/chat', [
                'workspace_id' => $this->workspace->id,
                'message' => $puzzleRequest,
                'session_id' => null
            ]);
        
        $response->assertStatus(200);
        $sessionId = $response->json('data.session_id');
        
        $conversationTracker[] = [
            'interaction' => 1,
            'user_message' => $puzzleRequest,
            'ai_response' => $response->json('data.message'),
            'ai_thinking' => $response->json('data.thinking_process'),
            'game_complexity' => count($response->json('data.game_data.game_json.objects'))
        ];
        
        // Logic enhancement 1 - Special gems
        $logicRequest1 = "Add special gems that create more powerful effects: bomb gems that clear a 3x3 area around them when matched, and line gems that clear entire rows or columns. These special gems should be created when you match 4 or 5 gems in a line.";
        
        $logicResponse1 = $this->actingAs($this->user)
            ->postJson('/api/gdevelop/chat', [
                'workspace_id' => $this->workspace->id,
                'message' => $logicRequest1,
                'session_id' => $sessionId
            ]);
        
        $logicResponse1->assertStatus(200);
        
        $conversationTracker[] = [
            'interaction' => 2,
            'user_message' => $logicRequest1,
            'ai_response' => $logicResponse1->json('data.message'),
            'ai_thinking' => $logicResponse1->json('data.thinking_process'),
            'game_complexity' => count($logicResponse1->json('data.game_data.game_json.objects'))
        ];
        
        // Logic enhancement 2 - Combo system
        $logicRequest2 = "Add a combo system that tracks consecutive matches without the player making a move (cascading matches). Each combo level should multiply the score by the combo number. Also add a moves counter that limits the player to 30 moves per level.";
        
        $logicResponse2 = $this->actingAs($this->user)
            ->postJson('/api/gdevelop/chat', [
                'workspace_id' => $this->workspace->id,
                'message' => $logicRequest2,
                'session_id' => $sessionId
            ]);
        
        $logicResponse2->assertStatus(200);
        
        $conversationTracker[] = [
            'interaction' => 3,
            'user_message' => $logicRequest2,
            'ai_response' => $logicResponse2->json('data.message'),
            'ai_thinking' => $logicResponse2->json('data.thinking_process'),
            'game_complexity' => count($logicResponse2->json('data.game_data.game_json.objects'))
        ];
        
        // Logic enhancement 3 - Advanced mechanics
        $logicRequest3 = "Add locked gems that have chains around them and require multiple matches adjacent to them to unlock. Add ice blocks that cannot be moved but can be destroyed by matches next to them. Also add a hint system that highlights possible moves when the player is stuck.";
        
        $logicResponse3 = $this->actingAs($this->user)
            ->postJson('/api/gdevelop/chat', [
                'workspace_id' => $this->workspace->id,
                'message' => $logicRequest3,
                'session_id' => $sessionId
            ]);
        
        $logicResponse3->assertStatus(200);
        
        $conversationTracker[] = [
            'interaction' => 4,
            'user_message' => $logicRequest3,
            'ai_response' => $logicResponse3->json('data.message'),
            'ai_thinking' => $logicResponse3->json('data.thinking_process'),
            'game_complexity' => count($logicResponse3->json('data.game_data.game_json.objects'))
        ];
        
        // Verify conversation flow and complexity growth
        expect($conversationTracker)->toHaveCount(4);
        
        // Verify game complexity increases with each iteration
        for ($i = 1; $i < count($conversationTracker); $i++) {
            expect($conversationTracker[$i]['game_complexity'])->toBeGreaterThanOrEqual($conversationTracker[$i-1]['game_complexity']);
        }
        
        // Verify AI thinking process shows logical progression
        foreach ($conversationTracker as $interaction) {
            expect($interaction['ai_thinking'])->toContain('puzzle');
            expect($interaction['ai_response'])->not->toBeEmpty();
        }
        
        // Verify final game has all requested features
        $finalGameData = $logicResponse3->json('data.game_data');
        $variables = collect($finalGameData['game_json']['variables']);
        expect($variables->where('name', 'like', '%combo%')->count())->toBeGreaterThanOrEqual(1);
        expect($variables->where('name', 'like', '%moves%')->count())->toBeGreaterThanOrEqual(1);
        
        $objects = collect($finalGameData['game_json']['objects']);
        expect($objects->where('name', 'like', '%Bomb%')->count())->toBeGreaterThanOrEqual(1);
        expect($objects->where('name', 'like', '%Locked%')->count())->toBeGreaterThanOrEqual(1);
        expect($objects->where('name', 'like', '%Ice%')->count())->toBeGreaterThanOrEqual(1);
        
        // Test conversation export and analysis
        $conversationAnalysis = $this->actingAs($this->user)
            ->getJson("/api/gdevelop/conversation/{$sessionId}/analysis");
        
        $conversationAnalysis->assertStatus(200);
        $analysis = $conversationAnalysis->json('data');
        
        expect($analysis['total_interactions'])->toBe(4);
        expect($analysis['complexity_progression'])->toBe('increasing');
        expect($analysis['feature_additions'])->toBeGreaterThanOrEqual(8);
    });
});

describe('Conversation Recovery and Continuity', function () {
    test('conversation can be recovered and continued after interruption', function () {
        // Start a conversation
        $response = $this->actingAs($this->user)
            ->postJson('/api/gdevelop/chat', [
                'workspace_id' => $this->workspace->id,
                'message' => 'Create a tower defense game',
                'session_id' => null
            ]);
        
        $sessionId = $response->json('data.session_id');
        
        // Add several interactions
        $interactions = [
            'Add more tower types',
            'Increase enemy variety',
            'Add special abilities'
        ];
        
        foreach ($interactions as $message) {
            $this->actingAs($this->user)
                ->postJson('/api/gdevelop/chat', [
                    'workspace_id' => $this->workspace->id,
                    'message' => $message,
                    'session_id' => $sessionId
                ]);
        }
        
        // Simulate session recovery
        $recoveryResponse = $this->actingAs($this->user)
            ->getJson("/api/gdevelop/session/{$sessionId}/recover");
        
        $recoveryResponse->assertStatus(200);
        $recoveredData = $recoveryResponse->json('data');
        
        expect($recoveredData['session_id'])->toBe($sessionId);
        expect($recoveredData['conversation_history'])->toHaveCount(8); // 4 user + 4 AI messages
        expect($recoveredData['current_game_state'])->not->toBeNull();
        
        // Continue conversation after recovery
        $continuationResponse = $this->actingAs($this->user)
            ->postJson('/api/gdevelop/chat', [
                'workspace_id' => $this->workspace->id,
                'message' => 'Add a boss enemy at the end of each wave',
                'session_id' => $sessionId
            ]);
        
        $continuationResponse->assertStatus(200);
        
        // Verify conversation continuity
        $session = GDevelopGameSession::where('session_id', $sessionId)->first();
        $finalConversation = json_decode($session->conversation_history, true);
        expect($finalConversation)->toHaveCount(10); // 5 user + 5 AI messages
    });
});