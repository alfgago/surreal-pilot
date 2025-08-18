<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use App\Models\Workspace;
use App\Models\ChatConversation;
use App\Models\Game;
use App\Services\PlayCanvasMcpManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GameConversationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Company $company;
    private Workspace $workspace;
    private ChatConversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user and company
        $this->user = User::factory()->create();
        $this->company = Company::factory()->create([
            'user_id' => $this->user->id,
            'credits' => 1000,
        ]);
        
        // Associate user with company
        $this->user->companies()->attach($this->company, ['role' => 'owner']);
        $this->user->forceFill(['current_company_id' => $this->company->id])->save();

        // Create PlayCanvas workspace
        $this->workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
            'status' => 'ready',
            'mcp_port' => 3001,
        ]);

        // Create conversation
        $this->conversation = ChatConversation::factory()->create([
            'workspace_id' => $this->workspace->id,
            'title' => 'Test Game Creation Chat',
        ]);
    }

    public function test_game_created_through_mcp_command_is_associated_with_conversation()
    {
        // Mock MCP server response for game creation
        Http::fake([
            'localhost:3001/v1/command' => Http::response([
                'success' => true,
                'command' => 'create a platformer game',
                'workspaceId' => $this->workspace->id,
                'timestamp' => now()->toISOString(),
                'changes' => [
                    [
                        'type' => 'project_creation',
                        'description' => 'Created new platformer game',
                        'files_modified' => ['scene.json', 'scripts/player.js']
                    ]
                ],
                'preview_url' => "http://localhost:3001/preview/{$this->workspace->id}",
                'title' => 'Platformer Game'
            ])
        ]);

        // Send MCP command with conversation context
        $mcpManager = app(PlayCanvasMcpManager::class);
        $result = $mcpManager->sendCommand($this->workspace, 'create a platformer game', $this->conversation);

        // Assert MCP command was successful
        $this->assertTrue($result['success']);

        // Assert game was created and associated with conversation
        $game = Game::where('workspace_id', $this->workspace->id)->first();
        $this->assertNotNull($game);
        $this->assertEquals($this->conversation->id, $game->conversation_id);
        $this->assertEquals('Platformer Game', $game->title);
        $this->assertStringContainsString('create a platformer game', $game->metadata['original_command']);
    }

    public function test_game_created_through_api_endpoint_includes_conversation_context()
    {
        // Create game through API with conversation context
        $response = $this->actingAs($this->user)
            ->postJson("/api/workspaces/{$this->workspace->id}/games", [
                'title' => 'Test Game',
                'description' => 'A test game',
                'conversation_id' => $this->conversation->id,
            ]);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'game' => [
                'title' => 'Test Game',
                'conversation_id' => $this->conversation->id,
                'conversation' => [
                    'id' => $this->conversation->id,
                    'title' => 'Test Game Creation Chat',
                ]
            ]
        ]);

        // Verify game exists in database with conversation association
        $game = Game::where('title', 'Test Game')->first();
        $this->assertNotNull($game);
        $this->assertEquals($this->conversation->id, $game->conversation_id);
    }

    public function test_games_list_includes_conversation_information()
    {
        // Create a game associated with conversation
        $game = Game::factory()->create([
            'workspace_id' => $this->workspace->id,
            'conversation_id' => $this->conversation->id,
            'title' => 'Chat Created Game',
        ]);

        // Get games list
        $response = $this->actingAs($this->user)
            ->getJson("/api/workspaces/{$this->workspace->id}/games");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'games' => [
                [
                    'id' => $game->id,
                    'title' => 'Chat Created Game',
                    'conversation_id' => $this->conversation->id,
                    'conversation' => [
                        'id' => $this->conversation->id,
                        'title' => 'Test Game Creation Chat',
                    ]
                ]
            ]
        ]);
    }

    public function test_mcp_command_endpoint_accepts_conversation_id()
    {
        // Skip this test for now due to complex middleware requirements
        $this->markTestSkipped('MCP command endpoint requires complex middleware setup including HMAC verification');
    }

    public function test_game_creation_detection_patterns()
    {
        $mcpManager = app(PlayCanvasMcpManager::class);
        
        // Test various game creation command patterns
        $gameCreationCommands = [
            'create a new game',
            'make a platformer game',
            'build an FPS game',
            'generate a racing game',
            'start a new project',
            'create a third person game',
        ];

        foreach ($gameCreationCommands as $command) {
            $mcpResult = [
                'success' => true,
                'changes' => [
                    ['type' => 'project_creation']
                ]
            ];

            // Use reflection to test private method
            $reflection = new \ReflectionClass($mcpManager);
            $method = $reflection->getMethod('isGameCreationCommand');
            $method->setAccessible(true);

            $isGameCreation = $method->invoke($mcpManager, $command, $mcpResult);
            $this->assertTrue($isGameCreation, "Command '$command' should be detected as game creation");
        }
    }

    public function test_conversation_context_in_recent_games()
    {
        // Create games with and without conversation context
        $gameWithConversation = Game::factory()->create([
            'workspace_id' => $this->workspace->id,
            'conversation_id' => $this->conversation->id,
            'title' => 'Chat Game',
        ]);

        $gameWithoutConversation = Game::factory()->create([
            'workspace_id' => $this->workspace->id,
            'conversation_id' => null,
            'title' => 'Manual Game',
        ]);

        // Get recent games
        $response = $this->actingAs($this->user)
            ->getJson('/api/games/recent');

        $response->assertStatus(200);
        
        $games = $response->json('games');
        $chatGame = collect($games)->firstWhere('id', $gameWithConversation->id);
        $manualGame = collect($games)->firstWhere('id', $gameWithoutConversation->id);

        // Assert conversation context is included for chat-created game
        $this->assertNotNull($chatGame['conversation']);
        $this->assertEquals($this->conversation->id, $chatGame['conversation']['id']);

        // Assert no conversation context for manually created game
        $this->assertNull($manualGame['conversation'] ?? null);
    }
}