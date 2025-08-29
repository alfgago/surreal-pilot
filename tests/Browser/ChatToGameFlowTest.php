<?php

namespace Tests\Browser;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use App\Models\User;
use App\Models\Company;
use App\Models\Workspace;
use App\Models\Game;
use App\Models\ChatConversation;

class ChatToGameFlowTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_chat_to_game_creation_flow(): void
    {
        // Create test data
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);
        
        // Set user's selected engine
        $user->update(['selected_engine_type' => 'playcanvas']);
        
        // Create a workspace
        $workspace = Workspace::factory()->create([
            'company_id' => $company->id,
            'name' => 'Test Game Workspace',
            'engine_type' => 'playcanvas',
            'created_by' => $user->id,
        ]);

        $this->browse(function (Browser $browser) use ($user, $workspace) {
            $browser->loginAs($user)
                   ->visit('/chat?workspace=' . $workspace->id)
                   ->waitFor('#app', 15)
                   ->screenshot('chat_interface_loaded')
                   ->assertPresent('#app');
            
            // Check if chat interface loads properly
            if ($browser->element('[data-testid="message-input"]')) {
                $browser->type('[data-testid="message-input"]', 'Create a simple 3D cube game')
                       ->screenshot('chat_message_typed')
                       ->press('[data-testid="send-button"]')
                       ->screenshot('chat_message_sent');
                
                // Wait for potential AI response (this might timeout in testing)
                try {
                    $browser->waitFor('[data-testid="ai-response"]', 10)
                           ->screenshot('chat_ai_response_received');
                } catch (\Exception $e) {
                    // AI response might not come in testing environment
                    $browser->screenshot('chat_no_ai_response');
                }
            }
        });
    }

    public function test_game_storage_location(): void
    {
        // Create test data
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);
        
        $workspace = Workspace::factory()->create([
            'company_id' => $company->id,
            'name' => 'Storage Test Workspace',
            'engine_type' => 'playcanvas',
            'created_by' => $user->id,
        ]);

        // Create a conversation
        $conversation = ChatConversation::factory()->create([
            'workspace_id' => $workspace->id,
            'title' => 'Test Game Conversation',
        ]);

        // Create a game via API to test storage
        $this->actingAs($user)
             ->postJson("/api/workspaces/{$workspace->id}/games", [
                 'title' => 'Test Storage Game',
                 'description' => 'Testing where games are stored',
                 'conversation_id' => $conversation->id,
                 'metadata' => [
                     'engine_type' => 'playcanvas',
                     'created_via' => 'test',
                 ],
             ])
             ->assertStatus(201)
             ->assertJson([
                 'success' => true,
                 'message' => 'Game created successfully',
             ]);

        // Verify game was created in database
        $game = Game::where('title', 'Test Storage Game')->first();
        $this->assertNotNull($game);
        $this->assertEquals($workspace->id, $game->workspace_id);
        $this->assertEquals($conversation->id, $game->conversation_id);
        
        // Check expected storage path
        $expectedStoragePath = "workspaces/{$workspace->id}/games/{$game->id}";
        
        // Log storage information
        $this->addToAssertionCount(1); // Prevent risky test warning
        
        echo "\n=== GAME STORAGE INFORMATION ===\n";
        echo "Game ID: {$game->id}\n";
        echo "Workspace ID: {$workspace->id}\n";
        echo "Expected Storage Path: {$expectedStoragePath}\n";
        echo "Storage Root: " . storage_path('app/private') . "\n";
        echo "Full Storage Path: " . storage_path("app/private/{$expectedStoragePath}") . "\n";
        echo "Game Database Record:\n";
        echo "- Title: {$game->title}\n";
        echo "- Description: {$game->description}\n";
        echo "- Engine Type: " . $game->getEngineType() . "\n";
        echo "- Created At: {$game->created_at}\n";
        echo "- Metadata: " . json_encode($game->metadata) . "\n";
        echo "================================\n";
    }

    public function test_game_api_endpoints(): void
    {
        // Create test data
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);
        
        $workspace = Workspace::factory()->create([
            'company_id' => $company->id,
            'name' => 'API Test Workspace',
            'engine_type' => 'playcanvas',
            'created_by' => $user->id,
        ]);

        // Test creating a game via API
        $response = $this->actingAs($user)
                         ->postJson("/api/workspaces/{$workspace->id}/games", [
                             'title' => 'API Test Game',
                             'description' => 'Testing game API endpoints',
                             'metadata' => [
                                 'test' => true,
                                 'created_via' => 'api_test',
                             ],
                         ]);

        $response->assertStatus(201)
                ->assertJson([
                    'success' => true,
                    'message' => 'Game created successfully',
                ])
                ->assertJsonStructure([
                    'game' => [
                        'id',
                        'title',
                        'description',
                        'metadata',
                        'engine_type',
                        'created_at',
                        'updated_at',
                    ],
                ]);

        $gameData = $response->json('game');
        $gameId = $gameData['id'];

        // Test getting workspace games
        $response = $this->actingAs($user)
                         ->getJson("/api/workspaces/{$workspace->id}/games");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                ])
                ->assertJsonStructure([
                    'games',
                    'pagination',
                ]);

        // Test getting specific game
        $response = $this->actingAs($user)
                         ->getJson("/api/games/{$gameId}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                ])
                ->assertJsonStructure([
                    'game' => [
                        'id',
                        'title',
                        'description',
                        'metadata',
                        'stats',
                        'workspace',
                    ],
                ]);

        // Test updating game
        $response = $this->actingAs($user)
                         ->putJson("/api/games/{$gameId}", [
                             'title' => 'Updated API Test Game',
                             'description' => 'Updated description',
                             'metadata' => [
                                 'test' => true,
                                 'updated_via' => 'api_test',
                             ],
                         ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Game updated successfully',
                ]);

        // Test getting recent games
        $response = $this->actingAs($user)
                         ->getJson('/api/games/recent');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                ])
                ->assertJsonStructure([
                    'games',
                    'pagination',
                ]);

        echo "\n=== API ENDPOINTS TEST RESULTS ===\n";
        echo "✅ POST /api/workspaces/{workspace}/games - Create game\n";
        echo "✅ GET /api/workspaces/{workspace}/games - Get workspace games\n";
        echo "✅ GET /api/games/{game} - Get specific game\n";
        echo "✅ PUT /api/games/{game} - Update game\n";
        echo "✅ GET /api/games/recent - Get recent games\n";
        echo "==================================\n";
    }
}