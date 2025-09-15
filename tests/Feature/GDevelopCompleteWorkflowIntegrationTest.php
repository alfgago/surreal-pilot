<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Models\Workspace;
use App\Models\GDevelopGameSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class GDevelopCompleteWorkflowIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $user;
    private Workspace $workspace;
    private string $sessionId;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test company and user
        $this->company = Company::factory()->create([
            'name' => 'Test Game Studio',
            'credits' => 1000,
        ]);

        $this->user = User::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Test Developer',
            'email' => 'developer@testgamestudio.com',
        ]);

        // Create GDevelop workspace
        $this->workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'name' => 'Test GDevelop Game',
            'engine' => 'gdevelop',
            'status' => 'active',
        ]);

        $this->sessionId = Str::uuid()->toString();

        // Setup storage directories
        Storage::fake('local');
        Storage::makeDirectory("gdevelop/sessions/{$this->sessionId}");
        Storage::makeDirectory("gdevelop/templates");
        Storage::makeDirectory("gdevelop/exports");
    }

    protected function tearDown(): void
    {
        // Clean up test files
        Storage::deleteDirectory("gdevelop/sessions/{$this->sessionId}");
        Storage::deleteDirectory("gdevelop/exports/{$this->sessionId}");
        
        parent::tearDown();
    }

    public function test_complete_chat_to_game_creation_workflow()
    {
        // Test the complete workflow from initial chat request to game creation
        $this->actingAs($this->user);

        // Step 1: Initial game creation request
        $initialRequest = "Create a simple tower defense game with 3 different tower types and enemies that spawn from the left side of the screen";
        
        $response = $this->postJson('/api/gdevelop/chat', [
            'workspace_id' => $this->workspace->id,
            'session_id' => $this->sessionId,
            'message' => $initialRequest,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'session_id',
                    'game_json',
                    'assets',
                    'preview_url',
                    'message',
                    'conversation_history',
                ],
            ]);

        $gameData = $response->json('data');
        
        // Verify game session was created
        $this->assertDatabaseHas('gdevelop_game_sessions', [
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'session_id' => $this->sessionId,
        ]);

        // Verify game JSON structure contains tower defense elements
        $gameJson = $gameData['game_json'];
        $this->assertIsArray($gameJson);
        $this->assertArrayHasKey('layouts', $gameJson);
        $this->assertArrayHasKey('objects', $gameJson);
        
        // Check for tower defense specific objects
        $objects = collect($gameJson['objects']);
        $this->assertTrue($objects->contains('name', 'Tower'));
        $this->assertTrue($objects->contains('name', 'Enemy'));

        // Verify conversation history is stored
        $this->assertArrayHasKey('conversation_history', $gameData);
        $this->assertCount(2, $gameData['conversation_history']); // User message + AI response

        return $gameData;
    }

    public function test_iterative_game_modifications_through_chat()
    {
        $this->actingAs($this->user);

        // First create initial game
        $this->postJson('/api/gdevelop/chat', [
            'workspace_id' => $this->workspace->id,
            'session_id' => $this->sessionId,
            'message' => 'Create a simple tower defense game with 3 different tower types',
        ]);

        // Step 2: First modification - Add a new tower type
        $modificationRequest1 = "Add a new ice tower that slows down enemies when it hits them";
        
        $response = $this->postJson('/api/gdevelop/chat', [
            'workspace_id' => $this->workspace->id,
            'session_id' => $this->sessionId,
            'message' => $modificationRequest1,
        ]);

        $response->assertStatus(200);
        $gameData1 = $response->json('data');

        // Verify ice tower was added
        $gameJson1 = $gameData1['game_json'];
        $objects1 = collect($gameJson1['objects']);
        $this->assertTrue($objects1->contains('name', 'IceTower'));

        // Verify conversation history is growing
        $this->assertGreaterThan(2, count($gameData1['conversation_history']));

        // Step 3: Second modification - Change enemy properties
        $modificationRequest2 = "Make the enemies move 50% faster and change their color to red";
        
        $response = $this->postJson('/api/gdevelop/chat', [
            'workspace_id' => $this->workspace->id,
            'session_id' => $this->sessionId,
            'message' => $modificationRequest2,
        ]);

        $response->assertStatus(200);
        $gameData2 = $response->json('data');

        // Verify enemy modifications
        $gameJson2 = $gameData2['game_json'];
        $objects2 = collect($gameJson2['objects']);
        $enemyObject = $objects2->firstWhere('name', 'Enemy');
        $this->assertNotNull($enemyObject);

        // Step 4: Third modification - Add scoring system
        $modificationRequest3 = "Add a scoring system that gives 10 points for each enemy destroyed and displays the score on screen";
        
        $response = $this->postJson('/api/gdevelop/chat', [
            'workspace_id' => $this->workspace->id,
            'session_id' => $this->sessionId,
            'message' => $modificationRequest3,
        ]);

        $response->assertStatus(200);
        $gameData3 = $response->json('data');

        // Verify scoring system was added
        $gameJson3 = $gameData3['game_json'];
        $this->assertArrayHasKey('variables', $gameJson3);
        $variables = collect($gameJson3['variables']);
        $this->assertTrue($variables->contains('name', 'Score'));

        // Verify conversation history contains all interactions
        $this->assertGreaterThanOrEqual(6, count($gameData3['conversation_history'])); // 3 user + 3 AI messages
    }

    public function test_preview_generation_and_serving()
    {
        $this->actingAs($this->user);

        // First create a game
        $this->postJson('/api/gdevelop/chat', [
            'workspace_id' => $this->workspace->id,
            'session_id' => $this->sessionId,
            'message' => 'Create a simple platformer game',
        ]);

        // Test preview generation
        $response = $this->getJson("/api/gdevelop/preview/{$this->sessionId}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'preview_url',
                    'game_loaded',
                    'build_time',
                    'assets_count',
                ],
            ]);

        $previewData = $response->json('data');
        $this->assertNotEmpty($previewData['preview_url']);
        $this->assertTrue($previewData['game_loaded']);
        $this->assertIsNumeric($previewData['build_time']);
        $this->assertGreaterThan(0, $previewData['assets_count']);

        // Verify preview files were created
        $previewPath = "gdevelop/sessions/{$this->sessionId}/preview";
        Storage::assertExists("{$previewPath}/index.html");
        Storage::assertExists("{$previewPath}/game.json");

        // Test preview URL accessibility
        $previewUrl = $previewData['preview_url'];
        $previewResponse = $this->get($previewUrl);
        $previewResponse->assertStatus(200);
        $previewResponse->assertHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    public function test_export_process_with_zip_creation()
    {
        $this->actingAs($this->user);

        // First create a game
        $this->postJson('/api/gdevelop/chat', [
            'workspace_id' => $this->workspace->id,
            'session_id' => $this->sessionId,
            'message' => 'Create a simple puzzle game',
        ]);

        // Test game export
        $response = $this->postJson("/api/gdevelop/export/{$this->sessionId}", [
            'options' => [
                'include_assets' => true,
                'optimize_for_mobile' => true,
                'compression_level' => 'standard',
                'export_format' => 'html5',
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'export_url',
                    'file_size',
                    'build_time',
                    'included_files',
                ],
            ]);

        $exportData = $response->json('data');
        $this->assertNotEmpty($exportData['export_url']);
        $this->assertIsNumeric($exportData['file_size']);
        $this->assertGreaterThan(0, $exportData['file_size']);
        $this->assertIsArray($exportData['included_files']);
        $this->assertContains('index.html', $exportData['included_files']);
        $this->assertContains('game.json', $exportData['included_files']);

        // Verify export ZIP file was created
        $exportPath = "gdevelop/exports/{$this->sessionId}";
        Storage::assertExists("{$exportPath}/game.zip");

        // Test export download
        $exportUrl = $exportData['export_url'];
        $downloadResponse = $this->get($exportUrl);
        $downloadResponse->assertStatus(200);
        $downloadResponse->assertHeader('Content-Type', 'application/zip');
        $downloadResponse->assertHeader('Content-Disposition');
    }

    public function test_session_management_and_game_state_persistence()
    {
        $this->actingAs($this->user);

        // Create initial game
        $response = $this->postJson('/api/gdevelop/chat', [
            'workspace_id' => $this->workspace->id,
            'session_id' => $this->sessionId,
            'message' => 'Create a simple platformer game with a player character that can jump',
        ]);

        $response->assertStatus(200);
        $initialGameData = $response->json('data');

        // Verify session was created in database
        $session = GDevelopGameSession::where('session_id', $this->sessionId)->first();
        $this->assertNotNull($session);
        $this->assertEquals($this->workspace->id, $session->workspace_id);
        $this->assertEquals($this->user->id, $session->user_id);
        $this->assertNotNull($session->game_json);
        $this->assertIsArray($session->game_json);

        // Make a modification
        $response = $this->postJson('/api/gdevelop/chat', [
            'workspace_id' => $this->workspace->id,
            'session_id' => $this->sessionId,
            'message' => 'Add collectible coins that give points when collected',
        ]);

        $response->assertStatus(200);
        $modifiedGameData = $response->json('data');

        // Verify session was updated
        $session->refresh();
        $this->assertNotEquals($initialGameData['game_json'], $session->game_json);
        $this->assertGreaterThan(1, $session->version);

        // Verify game state persistence across requests
        $objects = collect($session->game_json['objects']);
        $this->assertTrue($objects->contains('name', 'Player'));
        $this->assertTrue($objects->contains('name', 'Coin'));

        // Test session recovery
        $recoveryResponse = $this->getJson("/api/gdevelop/session/{$this->sessionId}");
        $recoveryResponse->assertStatus(200);
        $recoveredData = $recoveryResponse->json('data');
        
        $this->assertEquals($session->game_json, $recoveredData['game_json']);
        $this->assertEquals($session->version, $recoveredData['version']);
    }

    public function test_error_handling_and_recovery_mechanisms()
    {
        $this->actingAs($this->user);

        // Test invalid game modification request
        $response = $this->postJson('/api/gdevelop/chat', [
            'workspace_id' => $this->workspace->id,
            'session_id' => $this->sessionId,
            'message' => 'Add a nuclear reactor that destroys the entire universe',
        ]);

        // Should handle gracefully and provide alternative
        $response->assertStatus(200);
        $responseData = $response->json('data');
        $this->assertArrayHasKey('message', $responseData);
        $this->assertStringContainsString('alternative', strtolower($responseData['message']));

        // Test malformed session ID
        $invalidResponse = $this->postJson('/api/gdevelop/chat', [
            'workspace_id' => $this->workspace->id,
            'session_id' => 'invalid-session-id',
            'message' => 'Create a game',
        ]);

        $invalidResponse->assertStatus(422);

        // Test missing workspace
        $missingWorkspaceResponse = $this->postJson('/api/gdevelop/chat', [
            'workspace_id' => 99999,
            'session_id' => $this->sessionId,
            'message' => 'Create a game',
        ]);

        $missingWorkspaceResponse->assertStatus(404);
    }

    public function test_conversation_history_storage_and_retrieval()
    {
        $this->actingAs($this->user);

        $messages = [
            'Create a puzzle game with colored blocks',
            'Add a timer that counts down from 60 seconds',
            'Make the blocks fall when clicked',
            'Add sound effects for block clicks',
        ];

        $conversationHistory = [];

        foreach ($messages as $index => $message) {
            $response = $this->postJson('/api/gdevelop/chat', [
                'workspace_id' => $this->workspace->id,
                'session_id' => $this->sessionId,
                'message' => $message,
            ]);

            $response->assertStatus(200);
            $responseData = $response->json('data');

            // Verify conversation history grows with each interaction
            $this->assertArrayHasKey('conversation_history', $responseData);
            $history = $responseData['conversation_history'];
            $this->assertCount(($index + 1) * 2, $history); // User message + AI response for each interaction

            // Verify user message is stored
            $userMessage = collect($history)->where('role', 'user')->last();
            $this->assertEquals($message, $userMessage['content']);

            // Verify AI response is stored
            $aiMessage = collect($history)->where('role', 'assistant')->last();
            $this->assertNotEmpty($aiMessage['content']);
            $this->assertArrayHasKey('thinking_process', $aiMessage);

            $conversationHistory = $history;
        }

        // Verify complete conversation history is preserved
        $session = GDevelopGameSession::where('session_id', $this->sessionId)->first();
        $this->assertNotNull($session);
        
        // Check that conversation history is stored in the session
        $this->assertArrayHasKey('conversation_history', $session->toArray());
        $storedHistory = $session->conversation_history ?? [];
        $this->assertCount(8, $storedHistory); // 4 user messages + 4 AI responses

        // Verify AI thinking process is captured
        $aiResponses = collect($storedHistory)->where('role', 'assistant');
        foreach ($aiResponses as $aiResponse) {
            $this->assertArrayHasKey('thinking_process', $aiResponse);
            $this->assertNotEmpty($aiResponse['thinking_process']);
        }
    }

    public function test_mobile_optimization_features()
    {
        $this->actingAs($this->user);

        // Create a mobile-optimized game
        $response = $this->postJson('/api/gdevelop/chat', [
            'workspace_id' => $this->workspace->id,
            'session_id' => $this->sessionId,
            'message' => 'Create a mobile-friendly endless runner game with touch controls',
        ]);

        $response->assertStatus(200);
        $gameData = $response->json('data');
        $gameJson = $gameData['game_json'];

        // Verify mobile-specific properties
        $this->assertArrayHasKey('properties', $gameJson);
        $properties = $gameJson['properties'];
        
        // Check for mobile-friendly settings
        $this->assertTrue(in_array($properties['orientation'], ['landscape', 'portrait']));
        $this->assertTrue($properties['adaptGameResolutionAtRuntime']);

        // Verify touch controls are included
        $objects = collect($gameJson['objects']);
        $touchControls = $objects->filter(function ($object) {
            return str_contains(strtolower($object['name']), 'touch') || 
                   str_contains(strtolower($object['name']), 'button');
        });
        $this->assertGreaterThan(0, $touchControls->count());

        // Test mobile export
        $exportResponse = $this->postJson("/api/gdevelop/export/{$this->sessionId}", [
            'options' => [
                'optimize_for_mobile' => true,
                'export_format' => 'html5',
            ],
        ]);

        $exportResponse->assertStatus(200);
        $exportData = $exportResponse->json('data');
        $this->assertArrayHasKey('mobile_optimized', $exportData);
        $this->assertTrue($exportData['mobile_optimized']);
    }
}