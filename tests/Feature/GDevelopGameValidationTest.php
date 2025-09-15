<?php

use App\Models\User;
use App\Models\Company;
use App\Models\Workspace;
use App\Models\GDevelopGameSession;
use App\Services\GDevelopGameService;
use App\Services\GDevelopAIService;
use App\Services\GDevelopRuntimeService;
use App\Services\GDevelopPreviewService;
use App\Services\GDevelopExportService;
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
    
    // Mock services for testing
    $this->gameService = app(GDevelopGameService::class);
    $this->aiService = app(GDevelopAIService::class);
    $this->runtimeService = app(GDevelopRuntimeService::class);
    $this->previewService = app(GDevelopPreviewService::class);
    $this->exportService = app(GDevelopExportService::class);
    
    // Setup test storage
    Storage::fake('gdevelop');
});

describe('Tower Defense Game Creation and Iteration', function () {
    test('creates tower defense game through chat with multiple feedback interactions', function () {
        // Initial game creation request
        $initialRequest = "Create a tower defense game with 3 different tower types and enemies that spawn from the left side of the screen";
        
        $response = $this->actingAs($this->user)
            ->postJson('/api/gdevelop/chat', [
                'workspace_id' => $this->workspace->id,
                'message' => $initialRequest,
                'session_id' => null
            ]);
        
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'session_id',
                'game_data',
                'preview_url',
                'message'
            ]
        ]);
        
        $sessionId = $response->json('data.session_id');
        $gameData = $response->json('data.game_data');
        
        // Verify initial game structure
        expect($gameData)->toHaveKey('game_json');
        expect($gameData['game_json'])->toHaveKey('layouts');
        expect($gameData['game_json'])->toHaveKey('objects');
        
        // Verify tower defense specific elements
        $objects = collect($gameData['game_json']['objects']);
        expect($objects->where('name', 'like', '%Tower%')->count())->toBeGreaterThanOrEqual(3);
        expect($objects->where('name', 'like', '%Enemy%')->count())->toBeGreaterThanOrEqual(1);
        
        // First feedback interaction - modify tower properties
        $feedback1 = "Make the basic tower shoot faster and add a splash damage tower that affects multiple enemies";
        
        $response1 = $this->actingAs($this->user)
            ->postJson('/api/gdevelop/chat', [
                'workspace_id' => $this->workspace->id,
                'message' => $feedback1,
                'session_id' => $sessionId
            ]);
        
        $response1->assertStatus(200);
        $gameData1 = $response1->json('data.game_data');
        
        // Verify modifications were applied
        expect($gameData1['version'])->toBeGreaterThan($gameData['version']);
        
        // Second feedback interaction - add enemy variety
        $feedback2 = "Add flying enemies that can only be hit by anti-air towers, and make ground enemies move in a zigzag pattern";
        
        $response2 = $this->actingAs($this->user)
            ->postJson('/api/gdevelop/chat', [
                'workspace_id' => $this->workspace->id,
                'message' => $feedback2,
                'session_id' => $sessionId
            ]);
        
        $response2->assertStatus(200);
        $gameData2 = $response2->json('data.game_data');
        
        // Verify flying enemies and anti-air towers were added
        $objects2 = collect($gameData2['game_json']['objects']);
        expect($objects2->where('name', 'like', '%Flying%')->count())->toBeGreaterThanOrEqual(1);
        expect($objects2->where('name', 'like', '%AntiAir%')->count())->toBeGreaterThanOrEqual(1);
        
        // Third feedback interaction - add game mechanics
        $feedback3 = "Add a wave system with 5 waves, each wave should have more enemies than the previous one, and add a score system";
        
        $response3 = $this->actingAs($this->user)
            ->postJson('/api/gdevelop/chat', [
                'workspace_id' => $this->workspace->id,
                'message' => $feedback3,
                'session_id' => $sessionId
            ]);
        
        $response3->assertStatus(200);
        $gameData3 = $response3->json('data.game_data');
        
        // Verify wave system and scoring
        expect($gameData3['game_json'])->toHaveKey('variables');
        $variables = collect($gameData3['game_json']['variables']);
        expect($variables->where('name', 'like', '%wave%')->count())->toBeGreaterThanOrEqual(1);
        expect($variables->where('name', 'like', '%score%')->count())->toBeGreaterThanOrEqual(1);
        
        // Test preview generation
        $previewResponse = $this->actingAs($this->user)
            ->getJson("/api/gdevelop/preview/{$sessionId}");
        
        $previewResponse->assertStatus(200);
        $previewResponse->assertJsonStructure([
            'success',
            'data' => [
                'preview_url',
                'game_loaded'
            ]
        ]);
        
        // Test export functionality
        $exportResponse = $this->actingAs($this->user)
            ->postJson("/api/gdevelop/export/{$sessionId}", [
                'options' => [
                    'includeAssets' => true,
                    'optimizeForMobile' => false,
                    'compressionLevel' => 'standard'
                ]
            ]);
        
        $exportResponse->assertStatus(200);
        $exportResponse->assertJsonStructure([
            'success',
            'data' => [
                'download_url',
                'export_size'
            ]
        ]);
        
        // Verify session persistence
        $session = GDevelopGameSession::where('session_id', $sessionId)->first();
        expect($session)->not->toBeNull();
        expect($session->version)->toBe($gameData3['version']);
        expect($session->game_json)->toEqual($gameData3['game_json']);
    });
});

describe('Platformer Game Creation and Testing', function () {
    test('creates platformer game to test physics and controls', function () {
        // Create platformer game
        $platformerRequest = "Create a 2D platformer game with a player character that can jump, run, and collect coins. Add platforms, enemies, and a goal flag";
        
        $response = $this->actingAs($this->user)
            ->postJson('/api/gdevelop/chat', [
                'workspace_id' => $this->workspace->id,
                'message' => $platformerRequest,
                'session_id' => null
            ]);
        
        $response->assertStatus(200);
        $sessionId = $response->json('data.session_id');
        $gameData = $response->json('data.game_data');
        
        // Verify platformer elements
        $objects = collect($gameData['game_json']['objects']);
        expect($objects->where('name', 'like', '%Player%')->count())->toBeGreaterThanOrEqual(1);
        expect($objects->where('name', 'like', '%Platform%')->count())->toBeGreaterThanOrEqual(1);
        expect($objects->where('name', 'like', '%Coin%')->count())->toBeGreaterThanOrEqual(1);
        expect($objects->where('name', 'like', '%Enemy%')->count())->toBeGreaterThanOrEqual(1);
        
        // Test physics modifications
        $physicsRequest = "Make the player jump higher and add double jump ability. Also add moving platforms that go up and down";
        
        $physicsResponse = $this->actingAs($this->user)
            ->postJson('/api/gdevelop/chat', [
                'workspace_id' => $this->workspace->id,
                'message' => $physicsRequest,
                'session_id' => $sessionId
            ]);
        
        $physicsResponse->assertStatus(200);
        $updatedGameData = $physicsResponse->json('data.game_data');
        
        // Verify physics updates
        expect($updatedGameData['version'])->toBeGreaterThan($gameData['version']);
        
        // Test controls modification
        $controlsRequest = "Add wall jumping ability and make the player slide down walls slowly";
        
        $controlsResponse = $this->actingAs($this->user)
            ->postJson('/api/gdevelop/chat', [
                'workspace_id' => $this->workspace->id,
                'message' => $controlsRequest,
                'session_id' => $sessionId
            ]);
        
        $controlsResponse->assertStatus(200);
        
        // Test level design modification
        $levelRequest = "Add 3 different levels with increasing difficulty, each with unique obstacles and enemy patterns";
        
        $levelResponse = $this->actingAs($this->user)
            ->postJson('/api/gdevelop/chat', [
                'workspace_id' => $this->workspace->id,
                'message' => $levelRequest,
                'session_id' => $sessionId
            ]);
        
        $levelResponse->assertStatus(200);
        $finalGameData = $levelResponse->json('data.game_data');
        
        // Verify multiple levels
        expect($finalGameData['game_json']['layouts'])->toHaveCount(3);
        
        // Test preview and export
        $this->verifyPreviewAndExport($sessionId);
    });
});

describe('Puzzle Game Creation and Logic Testing', function () {
    test('generates puzzle game to validate logic and interaction systems', function () {
        // Create puzzle game
        $puzzleRequest = "Create a match-3 puzzle game where players swap adjacent gems to create lines of 3 or more matching colors. Add score system and level progression";
        
        $response = $this->actingAs($this->user)
            ->postJson('/api/gdevelop/chat', [
                'workspace_id' => $this->workspace->id,
                'message' => $puzzleRequest,
                'session_id' => null
            ]);
        
        $response->assertStatus(200);
        $sessionId = $response->json('data.session_id');
        $gameData = $response->json('data.game_data');
        
        // Verify puzzle elements
        $objects = collect($gameData['game_json']['objects']);
        expect($objects->where('name', 'like', '%Gem%')->count())->toBeGreaterThanOrEqual(1);
        expect($objects->where('name', 'like', '%Grid%')->count())->toBeGreaterThanOrEqual(1);
        
        // Test logic modification - add special gems
        $logicRequest = "Add special gems: bomb gems that clear surrounding gems, and line gems that clear entire rows or columns";
        
        $logicResponse = $this->actingAs($this->user)
            ->postJson('/api/gdevelop/chat', [
                'workspace_id' => $this->workspace->id,
                'message' => $logicRequest,
                'session_id' => $sessionId
            ]);
        
        $logicResponse->assertStatus(200);
        $updatedGameData = $logicResponse->json('data.game_data');
        
        // Verify special gems added
        $updatedObjects = collect($updatedGameData['game_json']['objects']);
        expect($updatedObjects->where('name', 'like', '%Bomb%')->count())->toBeGreaterThanOrEqual(1);
        expect($updatedObjects->where('name', 'like', '%Line%')->count())->toBeGreaterThanOrEqual(1);
        
        // Test interaction system - add combo system
        $interactionRequest = "Add a combo system that gives bonus points for consecutive matches, and add a timer for each level";
        
        $interactionResponse = $this->actingAs($this->user)
            ->postJson('/api/gdevelop/chat', [
                'workspace_id' => $this->workspace->id,
                'message' => $interactionRequest,
                'session_id' => $sessionId
            ]);
        
        $interactionResponse->assertStatus(200);
        $comboGameData = $interactionResponse->json('data.game_data');
        
        // Verify combo system variables
        $variables = collect($comboGameData['game_json']['variables']);
        expect($variables->where('name', 'like', '%combo%')->count())->toBeGreaterThanOrEqual(1);
        expect($variables->where('name', 'like', '%timer%')->count())->toBeGreaterThanOrEqual(1);
        
        // Test advanced puzzle mechanics
        $advancedRequest = "Add locked gems that require multiple matches to unlock, and add a moves limit for each level instead of timer";
        
        $advancedResponse = $this->actingAs($this->user)
            ->postJson('/api/gdevelop/chat', [
                'workspace_id' => $this->workspace->id,
                'message' => $advancedRequest,
                'session_id' => $sessionId
            ]);
        
        $advancedResponse->assertStatus(200);
        $finalGameData = $advancedResponse->json('data.game_data');
        
        // Verify advanced mechanics
        $finalObjects = collect($finalGameData['game_json']['objects']);
        expect($finalObjects->where('name', 'like', '%Locked%')->count())->toBeGreaterThanOrEqual(1);
        
        $finalVariables = collect($finalGameData['game_json']['variables']);
        expect($finalVariables->where('name', 'like', '%moves%')->count())->toBeGreaterThanOrEqual(1);
        
        // Test preview and export
        $this->verifyPreviewAndExport($sessionId);
    });
});

describe('Cross-Game Type Testing', function () {
    test('validates multiple game types with complex modifications', function () {
        // Test creating a hybrid game that combines elements
        $hybridRequest = "Create a tower defense game with platformer elements where the player can run around the map to manually place towers and collect resources";
        
        $response = $this->actingAs($this->user)
            ->postJson('/api/gdevelop/chat', [
                'workspace_id' => $this->workspace->id,
                'message' => $hybridRequest,
                'session_id' => null
            ]);
        
        $response->assertStatus(200);
        $sessionId = $response->json('data.session_id');
        $gameData = $response->json('data.game_data');
        
        // Verify hybrid elements
        $objects = collect($gameData['game_json']['objects']);
        expect($objects->where('name', 'like', '%Tower%')->count())->toBeGreaterThanOrEqual(1);
        expect($objects->where('name', 'like', '%Player%')->count())->toBeGreaterThanOrEqual(1);
        expect($objects->where('name', 'like', '%Resource%')->count())->toBeGreaterThanOrEqual(1);
        
        // Test complex modification
        $complexRequest = "Add puzzle elements where the player must solve mini-puzzles to unlock new tower types, and add RPG elements like player leveling and skill trees";
        
        $complexResponse = $this->actingAs($this->user)
            ->postJson('/api/gdevelop/chat', [
                'workspace_id' => $this->workspace->id,
                'message' => $complexRequest,
                'session_id' => $sessionId
            ]);
        
        $complexResponse->assertStatus(200);
        $complexGameData = $complexResponse->json('data.game_data');
        
        // Verify complex elements
        $complexObjects = collect($complexGameData['game_json']['objects']);
        expect($complexObjects->where('name', 'like', '%Puzzle%')->count())->toBeGreaterThanOrEqual(1);
        
        $complexVariables = collect($complexGameData['game_json']['variables']);
        expect($complexVariables->where('name', 'like', '%level%')->count())->toBeGreaterThanOrEqual(1);
        expect($complexVariables->where('name', 'like', '%experience%')->count())->toBeGreaterThanOrEqual(1);
        
        // Test mobile optimization request
        $mobileRequest = "Optimize this game for mobile devices with touch controls and responsive UI elements";
        
        $mobileResponse = $this->actingAs($this->user)
            ->postJson('/api/gdevelop/chat', [
                'workspace_id' => $this->workspace->id,
                'message' => $mobileRequest,
                'session_id' => $sessionId
            ]);
        
        $mobileResponse->assertStatus(200);
        $mobileGameData = $mobileResponse->json('data.game_data');
        
        // Verify mobile optimizations
        expect($mobileGameData['version'])->toBeGreaterThan($complexGameData['version']);
        
        // Test preview and export with mobile optimization
        $exportResponse = $this->actingAs($this->user)
            ->postJson("/api/gdevelop/export/{$sessionId}", [
                'options' => [
                    'includeAssets' => true,
                    'optimizeForMobile' => true,
                    'compressionLevel' => 'maximum'
                ]
            ]);
        
        $exportResponse->assertStatus(200);
        expect($exportResponse->json('data.mobile_optimized'))->toBe(true);
    });
});

// Helper method to verify preview and export functionality
function verifyPreviewAndExport(string $sessionId): void
{
    // Test preview generation
    $previewResponse = test()->actingAs(test()->user)
        ->getJson("/api/gdevelop/preview/{$sessionId}");
    
    $previewResponse->assertStatus(200);
    $previewResponse->assertJsonStructure([
        'success',
        'data' => [
            'preview_url',
            'game_loaded'
        ]
    ]);
    
    // Test export functionality
    $exportResponse = test()->actingAs(test()->user)
        ->postJson("/api/gdevelop/export/{$sessionId}", [
            'options' => [
                'includeAssets' => true,
                'optimizeForMobile' => false,
                'compressionLevel' => 'standard'
            ]
        ]);
    
    $exportResponse->assertStatus(200);
    $exportResponse->assertJsonStructure([
        'success',
        'data' => [
            'download_url',
            'export_size'
        ]
    ]);
    
    // Verify session was updated
    $session = GDevelopGameSession::where('session_id', $sessionId)->first();
    expect($session)->not->toBeNull();
    expect($session->preview_url)->not->toBeNull();
    expect($session->export_url)->not->toBeNull();
}