<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use App\Models\Workspace;
use App\Models\ChatConversation;
use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class InterfaceRedesignTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $user;
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create([
            'email' => 'alfgago@gmail.com',
            'password' => bcrypt('123Test!'),
        ]);
        
        $this->company = Company::factory()->create([
            'user_id' => $this->user->id,
            'credits' => 1000,
        ]);
        
        $this->user->companies()->attach($this->company, ['role' => 'owner']);
        $this->user->update(['current_company_id' => $this->company->id]);
        
        // Refresh the user to ensure the relationship is loaded
        $this->user = $this->user->fresh();
    }

    public function test_engine_selection_endpoints()
    {
        // Test getting available engines
        $response = $this->actingAs($this->user)
            ->getJson('/api/engines');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'engines' => [
                    '*' => [
                        'type',
                        'name',
                        'description',
                        'icon',
                        'features'
                    ]
                ]
            ]);

        // Test setting engine preference
        $response = $this->actingAs($this->user)
            ->postJson('/api/user/engine-preference', [
                'engine_type' => 'playcanvas'
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'engine_type' => 'playcanvas'
            ]);

        // Test getting engine preference
        $response = $this->actingAs($this->user)
            ->getJson('/api/user/engine-preference');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'engine_type' => 'playcanvas',
                'has_selection' => true
            ]);
    }

    public function test_conversation_management_endpoints()
    {
        $workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
            'status' => 'ready'
        ]);

        // Test creating a conversation
        $response = $this->actingAs($this->user)
            ->postJson("/api/workspaces/{$workspace->id}/conversations", [
                'title' => 'Test Conversation',
                'description' => 'A test conversation'
            ]);
            
        if ($response->status() !== 201) {
            dump($response->json());
        }

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'conversation' => [
                    'id',
                    'title',
                    'description',
                    'created_at',
                    'updated_at'
                ]
            ]);

        $conversationId = $response->json('conversation.id');

        // Test getting workspace conversations
        $response = $this->actingAs($this->user)
            ->getJson("/api/workspaces/{$workspace->id}/conversations");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'conversations');

        // Test adding a message
        $response = $this->actingAs($this->user)
            ->postJson("/api/conversations/{$conversationId}/messages", [
                'role' => 'user',
                'content' => 'Hello, create a simple game'
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'chat_message' => [
                    'id',
                    'role',
                    'content',
                    'created_at'
                ]
            ]);

        // Test getting conversation messages
        $response = $this->actingAs($this->user)
            ->getJson("/api/conversations/{$conversationId}/messages");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'messages');
    }

    public function test_games_management_endpoints()
    {
        $workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
            'status' => 'ready'
        ]);

        $conversation = ChatConversation::factory()->create([
            'workspace_id' => $workspace->id,
            'title' => 'Game Creation Chat'
        ]);

        // Test creating a game
        $response = $this->actingAs($this->user)
            ->postJson("/api/workspaces/{$workspace->id}/games", [
                'title' => 'Test Game',
                'description' => 'A simple test game',
                'conversation_id' => $conversation->id
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'game' => [
                    'id',
                    'title',
                    'description',
                    'engine_type',
                    'conversation_id'
                ]
            ]);

        $gameId = $response->json('game.id');

        // Test getting workspace games
        $response = $this->actingAs($this->user)
            ->getJson("/api/workspaces/{$workspace->id}/games");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'games');

        // Test getting game details
        $response = $this->actingAs($this->user)
            ->getJson("/api/games/{$gameId}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'game' => [
                    'id',
                    'title',
                    'workspace',
                    'stats'
                ]
            ]);
    }

    public function test_chat_settings_endpoints()
    {
        // Test getting chat settings
        $response = $this->actingAs($this->user)
            ->getJson('/api/chat/settings');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'settings' => [
                    'ai_model',
                    'temperature',
                    'max_tokens',
                    'streaming_enabled'
                ]
            ]);

        // Test saving chat settings
        $response = $this->actingAs($this->user)
            ->postJson('/api/chat/settings', [
                'ai_model' => 'claude-sonnet-4-20250514',
                'temperature' => 0.8,
                'max_tokens' => 2048,
                'streaming_enabled' => true
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ]);

        // Test getting available models
        $response = $this->actingAs($this->user)
            ->getJson('/api/chat/models');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'models' => [
                    '*' => [
                        'id',
                        'name',
                        'provider',
                        'available'
                    ]
                ]
            ]);
    }

    public function test_recent_conversations_and_games()
    {
        $workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas'
        ]);

        $conversation = ChatConversation::factory()->create([
            'workspace_id' => $workspace->id
        ]);

        $game = Game::factory()->create([
            'workspace_id' => $workspace->id,
            'conversation_id' => $conversation->id
        ]);

        // Test getting recent conversations
        $response = $this->actingAs($this->user)
            ->getJson('/api/conversations/recent');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'conversations'
            ]);

        // Test getting recent games
        $response = $this->actingAs($this->user)
            ->getJson('/api/games/recent');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'games'
            ]);
    }

    public function test_conversation_context_in_chat()
    {
        $workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas'
        ]);

        $conversation = ChatConversation::factory()->create([
            'workspace_id' => $workspace->id
        ]);

        // Test chat with conversation context
        $response = $this->actingAs($this->user)
            ->postJson('/api/chat', [
                'messages' => [
                    ['role' => 'user', 'content' => 'Create a simple game']
                ],
                'context' => [
                    'workspace_id' => $workspace->id,
                    'conversation_id' => $conversation->id
                ],
                'stream' => false
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'response',
                'metadata' => [
                    'conversation_id'
                ]
            ]);
    }
}