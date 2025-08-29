<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Company;
use App\Models\Workspace;
use App\Models\Game;
use App\Models\ChatConversation;

class GameStorageTest extends TestCase
{
    use RefreshDatabase;

    public function test_game_creation_and_storage_location(): void
    {
        // Create test data with proper relationships
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'selected_engine_type' => 'playcanvas',
        ]);

        $company = Company::create([
            'name' => 'Test Company',
            'user_id' => $user->id,
            'personal_company' => true,
            'credits' => 1000,
        ]);

        $user->companies()->attach($company->id, ['role' => 'admin']);
        $user->update(['current_company_id' => $company->id]);

        $workspace = Workspace::create([
            'company_id' => $company->id,
            'created_by' => $user->id,
            'name' => 'Test Workspace',
            'engine_type' => 'playcanvas',
            'status' => 'active',
        ]);

        $conversation = ChatConversation::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'title' => 'Test Conversation',
        ]);

        // Test game creation via API
        $response = $this->actingAs($user)
                         ->postJson("/api/workspaces/{$workspace->id}/games", [
                             'title' => 'Test Game',
                             'description' => 'A test game for storage testing',
                             'conversation_id' => $conversation->id,
                             'metadata' => [
                                 'engine_type' => 'playcanvas',
                                 'test_data' => true,
                             ],
                         ]);

        $response->assertStatus(201)
                ->assertJson([
                    'success' => true,
                    'message' => 'Game created successfully',
                ]);

        // Verify game was created
        $game = Game::where('title', 'Test Game')->first();
        $this->assertNotNull($game);
        $this->assertEquals($workspace->id, $game->workspace_id);
        $this->assertEquals($conversation->id, $game->conversation_id);

        // Check storage information
        $expectedStoragePath = "workspaces/{$workspace->id}/games/{$game->id}";
        $fullStoragePath = storage_path("app/private/{$expectedStoragePath}");

        echo "\n=== GAME STORAGE ANALYSIS ===\n";
        echo "Game ID: {$game->id}\n";
        echo "Workspace ID: {$workspace->id}\n";
        echo "Company ID: {$company->id}\n";
        echo "Conversation ID: {$conversation->id}\n";
        echo "\n--- Storage Paths ---\n";
        echo "Expected Storage Path: {$expectedStoragePath}\n";
        echo "Full Storage Path: {$fullStoragePath}\n";
        echo "Storage Root: " . storage_path('app/private') . "\n";
        echo "\n--- Game Data ---\n";
        echo "Title: {$game->title}\n";
        echo "Description: {$game->description}\n";
        echo "Engine Type: " . $game->getEngineType() . "\n";
        echo "Status: {$game->status}\n";
        echo "Created At: {$game->created_at}\n";
        echo "Metadata: " . json_encode($game->metadata, JSON_PRETTY_PRINT) . "\n";
        echo "\n--- URLs ---\n";
        echo "Preview URL: " . ($game->preview_url ?? 'Not set') . "\n";
        echo "Published URL: " . ($game->published_url ?? 'Not set') . "\n";
        echo "Thumbnail URL: " . ($game->thumbnail_url ?? 'Not set') . "\n";
        echo "Display URL: " . ($game->getDisplayUrl() ?? 'Not set') . "\n";
        echo "\n--- File System Info ---\n";
        echo "Default Filesystem: " . config('filesystems.default') . "\n";
        echo "Storage Directory Exists: " . (is_dir(storage_path('app/private')) ? 'Yes' : 'No') . "\n";
        echo "Game Directory Exists: " . (is_dir($fullStoragePath) ? 'Yes' : 'No') . "\n";
        echo "=============================\n";

        $this->assertTrue(true); // Prevent risky test warning
    }

    public function test_game_api_endpoints_functionality(): void
    {
        // Create test data with proper relationships
        $user = User::create([
            'name' => 'API Test User',
            'email' => 'apitest@example.com',
            'password' => bcrypt('password'),
            'selected_engine_type' => 'playcanvas',
        ]);

        $company = Company::create([
            'name' => 'API Test Company',
            'user_id' => $user->id,
            'personal_company' => true,
            'credits' => 1000,
        ]);

        $user->companies()->attach($company->id, ['role' => 'admin']);
        $user->update(['current_company_id' => $company->id]);

        $workspace = Workspace::create([
            'company_id' => $company->id,
            'created_by' => $user->id,
            'name' => 'API Test Workspace',
            'engine_type' => 'playcanvas',
            'status' => 'active',
        ]);

        // Test 1: Create Game
        $createResponse = $this->actingAs($user)
                              ->postJson("/api/workspaces/{$workspace->id}/games", [
                                  'title' => 'API Created Game',
                                  'description' => 'Game created via API test',
                                  'metadata' => ['test' => 'api_creation'],
                              ]);

        $createResponse->assertStatus(201);
        $gameId = $createResponse->json('game.id');

        // Test 2: Get Workspace Games
        $workspaceGamesResponse = $this->actingAs($user)
                                      ->getJson("/api/workspaces/{$workspace->id}/games");

        $workspaceGamesResponse->assertStatus(200)
                              ->assertJsonStructure([
                                  'success',
                                  'games',
                                  'pagination',
                              ]);

        // Test 3: Get Specific Game
        $gameResponse = $this->actingAs($user)
                            ->getJson("/api/games/{$gameId}");

        $gameResponse->assertStatus(200)
                    ->assertJsonStructure([
                        'success',
                        'game' => [
                            'id',
                            'title',
                            'description',
                            'metadata',
                            'stats',
                            'workspace',
                        ],
                    ]);

        // Test 4: Update Game
        $updateResponse = $this->actingAs($user)
                              ->putJson("/api/games/{$gameId}", [
                                  'title' => 'Updated API Game',
                                  'description' => 'Updated via API test',
                                  'metadata' => ['test' => 'api_update'],
                              ]);

        $updateResponse->assertStatus(200);

        // Test 5: Get Recent Games
        $recentGamesResponse = $this->actingAs($user)
                                   ->getJson('/api/games/recent');

        $recentGamesResponse->assertStatus(200)
                           ->assertJsonStructure([
                               'success',
                               'games',
                               'pagination',
                           ]);

        echo "\n=== API ENDPOINTS TEST RESULTS ===\n";
        echo "✅ POST /api/workspaces/{workspace}/games - Create game: " . $createResponse->status() . "\n";
        echo "✅ GET /api/workspaces/{workspace}/games - Get workspace games: " . $workspaceGamesResponse->status() . "\n";
        echo "✅ GET /api/games/{game} - Get specific game: " . $gameResponse->status() . "\n";
        echo "✅ PUT /api/games/{game} - Update game: " . $updateResponse->status() . "\n";
        echo "✅ GET /api/games/recent - Get recent games: " . $recentGamesResponse->status() . "\n";
        echo "\nGame ID Created: {$gameId}\n";
        echo "Workspace ID: {$workspace->id}\n";
        echo "Company ID: {$company->id}\n";
        echo "==================================\n";

        $this->assertTrue(true);
    }

    public function test_chat_conversation_to_game_relationship(): void
    {
        // Create test data with proper relationships
        $user = User::create([
            'name' => 'Chat Test User',
            'email' => 'chattest@example.com',
            'password' => bcrypt('password'),
            'selected_engine_type' => 'playcanvas',
        ]);

        $company = Company::create([
            'name' => 'Chat Test Company',
            'user_id' => $user->id,
            'personal_company' => true,
            'credits' => 1000,
        ]);

        $user->companies()->attach($company->id, ['role' => 'admin']);
        $user->update(['current_company_id' => $company->id]);

        $workspace = Workspace::create([
            'company_id' => $company->id,
            'created_by' => $user->id,
            'name' => 'Chat Test Workspace',
            'engine_type' => 'playcanvas',
            'status' => 'active',
        ]);

        // Create a conversation
        $conversation = ChatConversation::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'title' => 'Game Creation Chat',
        ]);

        // Create a game linked to the conversation
        $response = $this->actingAs($user)
                         ->postJson("/api/workspaces/{$workspace->id}/games", [
                             'title' => 'Chat Generated Game',
                             'description' => 'Game created from chat conversation',
                             'conversation_id' => $conversation->id,
                             'metadata' => [
                                 'created_from_chat' => true,
                                 'conversation_title' => $conversation->title,
                             ],
                         ]);

        $response->assertStatus(201);
        $gameData = $response->json('game');

        // Verify the relationship
        $game = Game::find($gameData['id']);
        $this->assertNotNull($game->conversation);
        $this->assertEquals($conversation->id, $game->conversation_id);
        $this->assertEquals($conversation->title, $game->conversation->title);

        echo "\n=== CHAT TO GAME RELATIONSHIP ===\n";
        echo "Conversation ID: {$conversation->id}\n";
        echo "Conversation Title: {$conversation->title}\n";
        echo "Game ID: {$game->id}\n";
        echo "Game Title: {$game->title}\n";
        echo "Game Conversation ID: {$game->conversation_id}\n";
        echo "Relationship Verified: " . ($game->conversation ? 'Yes' : 'No') . "\n";
        echo "Game Metadata: " . json_encode($game->metadata, JSON_PRETTY_PRINT) . "\n";
        echo "=================================\n";

        $this->assertTrue(true);
    }
}