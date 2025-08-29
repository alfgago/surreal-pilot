<?php

namespace Tests\Feature;

use App\Events\ChatMessageReceived;
use App\Events\UserTyping;
use App\Events\ChatConnectionStatus;
use App\Models\User;
use App\Models\Company;
use App\Models\Workspace;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Services\RealtimeChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RealtimeChatTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Company $company;
    private Workspace $workspace;
    private ChatConversation $conversation;
    private RealtimeChatService $realtimeChatService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->company = Company::factory()->create(['user_id' => $this->user->id]);
        $this->user->companies()->attach($this->company, ['role' => 'owner']);
        $this->user->update(['current_company_id' => $this->company->id]);

        $this->workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
        ]);

        $this->conversation = ChatConversation::factory()->create([
            'workspace_id' => $this->workspace->id,
        ]);

        $this->realtimeChatService = app(RealtimeChatService::class);
    }

    public function test_can_stream_chat_messages(): void
    {
        $this->actingAs($this->user);

        $response = $this->post('/api/chat/stream', [
            'message' => 'Hello AI assistant',
            'conversation_id' => $this->conversation->id,
            'workspace_id' => $this->workspace->id,
        ]);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/event-stream; charset=UTF-8');
    }

    public function test_can_update_typing_status(): void
    {
        Event::fake();
        $this->actingAs($this->user);

        $response = $this->post("/api/conversations/{$this->conversation->id}/typing", [
            'is_typing' => true,
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'is_typing' => true,
        ]);

        Event::assertDispatched(UserTyping::class, function ($event) {
            return $event->user->id === $this->user->id &&
                   $event->conversation->id === $this->conversation->id &&
                   $event->isTyping === true;
        });
    }

    public function test_can_update_connection_status(): void
    {
        Event::fake();
        $this->actingAs($this->user);

        $response = $this->post("/api/workspaces/{$this->workspace->id}/connection", [
            'status' => 'connected',
            'metadata' => ['client_info' => 'test'],
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'status' => 'connected',
        ]);

        Event::assertDispatched(ChatConnectionStatus::class, function ($event) {
            return $event->user->id === $this->user->id &&
                   $event->workspace->id === $this->workspace->id &&
                   $event->status === 'connected';
        });
    }

    public function test_can_get_typing_users(): void
    {
        $this->actingAs($this->user);

        // Set up typing status in cache
        Cache::put("typing:{$this->conversation->id}:{$this->user->id}", true, 3);

        $response = $this->get("/api/conversations/{$this->conversation->id}/typing");

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'typing_users' => [
                '*' => ['id', 'name']
            ]
        ]);
    }

    public function test_can_get_connection_statuses(): void
    {
        $this->actingAs($this->user);

        // Set up connection status in cache
        Cache::put("connection:{$this->workspace->id}:{$this->user->id}", [
            'status' => 'connected',
            'timestamp' => now()->toISOString(),
            'metadata' => null,
        ], 30);

        $response = $this->get("/api/workspaces/{$this->workspace->id}/connections");

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'connections' => [
                '*' => [
                    'user' => ['id', 'name'],
                    'status',
                    'timestamp'
                ]
            ]
        ]);
    }

    public function test_can_get_chat_statistics(): void
    {
        $this->actingAs($this->user);

        // Create some test data
        ChatMessage::factory()->create([
            'conversation_id' => $this->conversation->id,
            'role' => 'user',
            'content' => 'Test message',
            'created_at' => now()->subMinutes(30),
        ]);

        Cache::put("connection:{$this->workspace->id}:{$this->user->id}", [
            'status' => 'connected',
            'timestamp' => now()->toISOString(),
        ], 30);

        $response = $this->get("/api/workspaces/{$this->workspace->id}/chat-stats");

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'statistics' => [
                'active_users',
                'total_connections',
                'recent_messages',
                'connections'
            ]
        ]);
    }

    public function test_typing_status_broadcasts_event(): void
    {
        Event::fake();

        $this->realtimeChatService->broadcastTyping($this->user, $this->conversation, true);

        Event::assertDispatched(UserTyping::class, function ($event) {
            return $event->user->id === $this->user->id &&
                   $event->conversation->id === $this->conversation->id &&
                   $event->isTyping === true;
        });
    }

    public function test_message_broadcasts_event(): void
    {
        Event::fake();

        $message = ChatMessage::factory()->create([
            'conversation_id' => $this->conversation->id,
            'role' => 'user',
            'content' => 'Test message',
        ]);

        $this->realtimeChatService->broadcastMessage($message, $this->conversation);

        Event::assertDispatched(ChatMessageReceived::class, function ($event) use ($message) {
            return $event->message->id === $message->id &&
                   $event->conversation->id === $this->conversation->id;
        });
    }

    public function test_connection_status_broadcasts_event(): void
    {
        Event::fake();

        $this->realtimeChatService->broadcastConnectionStatus(
            $this->user,
            $this->workspace,
            'connected',
            ['test' => 'metadata']
        );

        Event::assertDispatched(ChatConnectionStatus::class, function ($event) {
            return $event->user->id === $this->user->id &&
                   $event->workspace->id === $this->workspace->id &&
                   $event->status === 'connected';
        });
    }

    public function test_requires_authentication_for_streaming(): void
    {
        $response = $this->post('/api/chat/stream', [
            'message' => 'Hello AI assistant',
            'conversation_id' => $this->conversation->id,
            'workspace_id' => $this->workspace->id,
        ]);

        $response->assertRedirect(); // Laravel redirects unauthenticated users
    }

    public function test_validates_streaming_request_data(): void
    {
        $this->actingAs($this->user);

        $response = $this->post('/api/chat/stream', [
            'message' => '', // Empty message
            'conversation_id' => 999, // Non-existent conversation
            'workspace_id' => 999, // Non-existent workspace
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['message', 'conversation_id', 'workspace_id']);
    }

    public function test_prevents_access_to_other_company_conversations(): void
    {
        $this->actingAs($this->user);

        $otherCompany = Company::factory()->create();
        $otherWorkspace = Workspace::factory()->create(['company_id' => $otherCompany->id]);
        $otherConversation = ChatConversation::factory()->create(['workspace_id' => $otherWorkspace->id]);

        $response = $this->post('/api/chat/stream', [
            'message' => 'Hello AI assistant',
            'conversation_id' => $otherConversation->id,
            'workspace_id' => $otherWorkspace->id,
        ]);

        $response->assertStatus(404);
    }

    public function test_cleanup_expired_typing_indicators(): void
    {
        // Set up expired typing indicator
        Cache::put("typing:{$this->conversation->id}:{$this->user->id}", true, -1);

        $this->realtimeChatService->cleanupExpiredTyping();

        $this->assertFalse(Cache::has("typing:{$this->conversation->id}:{$this->user->id}"));
    }

    public function test_cleanup_expired_connections(): void
    {
        // Set up expired connection
        Cache::put("connection:{$this->workspace->id}:{$this->user->id}", [
            'status' => 'connected',
            'timestamp' => now()->subMinutes(10)->toISOString(),
        ], -1);

        $this->realtimeChatService->cleanupExpiredConnections();

        $this->assertFalse(Cache::has("connection:{$this->workspace->id}:{$this->user->id}"));
    }
}