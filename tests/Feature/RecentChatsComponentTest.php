<?php

namespace Tests\Feature;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Company;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecentChatsComponentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Company $company;
    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->user = User::factory()->create([
            'current_company_id' => $this->company->id,
        ]);
        $this->user->companies()->attach($this->company, ['role' => 'admin']);

        $this->workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
        ]);
    }

    /** @test */
    public function it_can_load_workspace_conversations()
    {
        // Create test conversations
        $conversation1 = ChatConversation::factory()->create([
            'workspace_id' => $this->workspace->id,
            'title' => 'Test Conversation 1',
            'updated_at' => now()->subHour(),
        ]);

        $conversation2 = ChatConversation::factory()->create([
            'workspace_id' => $this->workspace->id,
            'title' => 'Test Conversation 2',
            'updated_at' => now(),
        ]);

        // Add messages to conversations
        ChatMessage::factory()->create([
            'conversation_id' => $conversation1->id,
            'role' => 'user',
            'content' => 'Hello from conversation 1',
        ]);

        ChatMessage::factory()->create([
            'conversation_id' => $conversation2->id,
            'role' => 'user',
            'content' => 'Hello from conversation 2',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/workspaces/{$this->workspace->id}/conversations");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonCount(2, 'conversations');

        // Verify conversations are ordered by most recent
        $conversations = $response->json('conversations');
        $this->assertEquals('Test Conversation 2', $conversations[0]['title']);
        $this->assertEquals('Test Conversation 1', $conversations[1]['title']);
    }

    /** @test */
    public function it_can_load_recent_conversations_across_workspaces()
    {
        // Create another workspace
        $workspace2 = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'unreal',
        ]);

        // Create conversations in both workspaces
        $conversation1 = ChatConversation::factory()->create([
            'workspace_id' => $this->workspace->id,
            'title' => 'PlayCanvas Chat',
            'updated_at' => now()->subHour(),
        ]);

        $conversation2 = ChatConversation::factory()->create([
            'workspace_id' => $workspace2->id,
            'title' => 'Unreal Chat',
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/conversations/recent?limit=10');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonCount(2, 'conversations');

        $conversations = $response->json('conversations');
        
        // Verify workspace information is included
        $this->assertArrayHasKey('workspace', $conversations[0]);
        $this->assertEquals('unreal', $conversations[0]['workspace']['engine_type']);
        $this->assertEquals('playcanvas', $conversations[1]['workspace']['engine_type']);
    }

    /** @test */
    public function it_can_create_new_conversation()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/workspaces/{$this->workspace->id}/conversations", [
                'title' => 'New Test Conversation',
                'description' => 'A test conversation for the Recent Chats component',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Conversation created successfully',
            ]);

        $this->assertDatabaseHas('chat_conversations', [
            'workspace_id' => $this->workspace->id,
            'title' => 'New Test Conversation',
            'description' => 'A test conversation for the Recent Chats component',
        ]);
    }

    /** @test */
    public function it_can_delete_conversation()
    {
        $conversation = ChatConversation::factory()->create([
            'workspace_id' => $this->workspace->id,
            'title' => 'Conversation to Delete',
        ]);

        // Add a message to the conversation
        ChatMessage::factory()->create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'This conversation will be deleted',
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/conversations/{$conversation->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Conversation deleted successfully',
            ]);

        $this->assertDatabaseMissing('chat_conversations', [
            'id' => $conversation->id,
        ]);

        // Verify messages are also deleted (cascade)
        $this->assertDatabaseMissing('chat_messages', [
            'conversation_id' => $conversation->id,
        ]);
    }

    /** @test */
    public function it_can_update_conversation_details()
    {
        $conversation = ChatConversation::factory()->create([
            'workspace_id' => $this->workspace->id,
            'title' => 'Original Title',
            'description' => 'Original description',
        ]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/conversations/{$conversation->id}", [
                'title' => 'Updated Title',
                'description' => 'Updated description',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Conversation updated successfully',
            ]);

        $this->assertDatabaseHas('chat_conversations', [
            'id' => $conversation->id,
            'title' => 'Updated Title',
            'description' => 'Updated description',
        ]);
    }

    /** @test */
    public function it_returns_conversation_with_message_count_and_preview()
    {
        $conversation = ChatConversation::factory()->create([
            'workspace_id' => $this->workspace->id,
            'title' => 'Test Conversation',
        ]);

        // Add multiple messages
        ChatMessage::factory()->create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'First message',
            'created_at' => now()->subMinutes(10),
        ]);

        ChatMessage::factory()->create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Assistant response',
            'created_at' => now()->subMinutes(5),
        ]);

        ChatMessage::factory()->create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Latest message from user',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/workspaces/{$this->workspace->id}/conversations");

        $response->assertStatus(200);
        
        $conversations = $response->json('conversations');
        $this->assertCount(1, $conversations);
        
        $conversationData = $conversations[0];
        $this->assertEquals(3, $conversationData['message_count']);
        $this->assertStringContainsString('Latest message from user', $conversationData['last_message_preview']);
    }

    /** @test */
    public function it_prevents_unauthorized_access_to_conversations()
    {
        // Create another company and user
        $otherCompany = Company::factory()->create();
        $otherUser = User::factory()->create([
            'current_company_id' => $otherCompany->id,
        ]);
        $otherUser->companies()->attach($otherCompany, ['role' => 'admin']);

        $conversation = ChatConversation::factory()->create([
            'workspace_id' => $this->workspace->id,
            'title' => 'Private Conversation',
        ]);

        // Try to access conversation from different company
        $response = $this->actingAs($otherUser)
            ->getJson("/api/workspaces/{$this->workspace->id}/conversations");

        $response->assertStatus(404);
    }

    /** @test */
    public function it_handles_empty_conversation_list()
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/workspaces/{$this->workspace->id}/conversations");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'conversations' => [],
            ]);
    }

    /** @test */
    public function it_respects_conversation_limit()
    {
        // Create more conversations than the limit
        for ($i = 1; $i <= 15; $i++) {
            ChatConversation::factory()->create([
                'workspace_id' => $this->workspace->id,
                'title' => "Conversation {$i}",
                'updated_at' => now()->subMinutes($i),
            ]);
        }

        $response = $this->actingAs($this->user)
            ->getJson('/api/conversations/recent?limit=5');

        $response->assertStatus(200);
        
        $conversations = $response->json('conversations');
        $this->assertCount(5, $conversations);
        
        // Verify they are the most recent ones
        $this->assertEquals('Conversation 1', $conversations[0]['title']);
        $this->assertEquals('Conversation 5', $conversations[4]['title']);
    }

    /** @test */
    public function it_validates_conversation_creation_input()
    {
        // Test with invalid title (too long)
        $response = $this->actingAs($this->user)
            ->postJson("/api/workspaces/{$this->workspace->id}/conversations", [
                'title' => str_repeat('a', 256), // Exceeds 255 character limit
                'description' => 'Valid description',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title']);

        // Test with invalid description (too long)
        $response = $this->actingAs($this->user)
            ->postJson("/api/workspaces/{$this->workspace->id}/conversations", [
                'title' => 'Valid title',
                'description' => str_repeat('a', 1001), // Exceeds 1000 character limit
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['description']);
    }
}