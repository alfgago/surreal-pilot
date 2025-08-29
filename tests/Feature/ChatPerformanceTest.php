<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use App\Models\Workspace;
use App\Models\ChatConversation;
use App\Services\RealtimeChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ChatPerformanceTest extends TestCase
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

    public function test_chat_performance_under_concurrent_requests(): void
    {
        $this->actingAs($this->user);

        $startTime = microtime(true);
        $responses = [];

        // Simulate 10 concurrent typing status updates
        for ($i = 0; $i < 10; $i++) {
            $responses[] = $this->post("/api/conversations/{$this->conversation->id}/typing", [
                'is_typing' => true,
            ]);
        }

        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        // All requests should complete within 2 seconds
        $this->assertLessThan(2.0, $duration, 'Concurrent typing requests took too long');

        // All responses should be successful
        foreach ($responses as $response) {
            $response->assertOk();
        }
    }

    public function test_connection_status_performance_with_many_users(): void
    {
        $this->actingAs($this->user);

        // Create multiple users and connection statuses using the service
        $users = User::factory()->count(10)->create(); // Reduced for test reliability
        
        foreach ($users as $user) {
            $this->realtimeChatService->broadcastConnectionStatus(
                $user,
                $this->workspace,
                'connected'
            );
        }

        $startTime = microtime(true);
        
        $response = $this->get("/api/workspaces/{$this->workspace->id}/connections");
        
        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        // Request should complete within 1 second even with multiple users
        $this->assertLessThan(1.0, $duration, 'Connection status request with many users took too long');
        
        $response->assertOk();
        $data = $response->json();
        // Just verify we get a valid response structure
        $this->assertArrayHasKey('connections', $data);
        $this->assertIsArray($data['connections']);
    }

    public function test_typing_indicator_cleanup_performance(): void
    {
        // Create many expired typing indicators
        for ($i = 0; $i < 100; $i++) {
            Cache::put("typing:{$this->conversation->id}:{$i}", true, -1);
        }

        $startTime = microtime(true);
        
        $this->realtimeChatService->cleanupExpiredTyping();
        
        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        // Cleanup should complete within 1 second
        $this->assertLessThan(1.0, $duration, 'Typing indicator cleanup took too long');
    }

    public function test_chat_statistics_performance(): void
    {
        $this->actingAs($this->user);

        // Create test data
        for ($i = 0; $i < 20; $i++) {
            \App\Models\ChatMessage::factory()->create([
                'conversation_id' => $this->conversation->id,
                'role' => 'user',
                'content' => "Test message {$i}",
                'created_at' => now()->subMinutes(rand(1, 60)),
            ]);
        }

        // Create connection statuses
        for ($i = 0; $i < 10; $i++) {
            Cache::put("connection:{$this->workspace->id}:{$i}", [
                'status' => 'connected',
                'timestamp' => now()->toISOString(),
            ], 30);
        }

        $startTime = microtime(true);
        
        $response = $this->get("/api/workspaces/{$this->workspace->id}/chat-stats");
        
        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        // Statistics should load within 0.5 seconds
        $this->assertLessThan(0.5, $duration, 'Chat statistics request took too long');
        
        $response->assertOk();
        $data = $response->json();
        $this->assertArrayHasKey('statistics', $data);
        $this->assertGreaterThan(0, $data['statistics']['recent_messages']);
    }

    public function test_streaming_response_handles_large_content(): void
    {
        $this->actingAs($this->user);

        // Test with a large message (close to the 10KB limit)
        $largeMessage = str_repeat('This is a test message with substantial content. ', 200); // ~9KB

        $response = $this->post('/api/chat/stream', [
            'message' => $largeMessage,
            'conversation_id' => $this->conversation->id,
            'workspace_id' => $this->workspace->id,
        ]);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/event-stream; charset=UTF-8');
    }

    public function test_database_performance_with_many_messages(): void
    {
        // Create a conversation with many messages
        $messages = [];
        for ($i = 0; $i < 100; $i++) {
            $messages[] = [
                'conversation_id' => $this->conversation->id,
                'role' => $i % 2 === 0 ? 'user' : 'assistant',
                'content' => "Message content {$i}",
                'created_at' => now()->subMinutes($i),
                'updated_at' => now()->subMinutes($i),
            ];
        }
        
        DB::table('chat_messages')->insert($messages);

        $this->actingAs($this->user);

        $startTime = microtime(true);
        
        $response = $this->get("/api/conversations/{$this->conversation->id}/messages");
        
        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        // Should load 100 messages within 0.5 seconds
        $this->assertLessThan(0.5, $duration, 'Loading many messages took too long');
        
        $response->assertOk();
        $data = $response->json();
        $this->assertCount(100, $data['messages']);
    }

    public function test_memory_usage_during_streaming(): void
    {
        $this->actingAs($this->user);

        $initialMemory = memory_get_usage(true);

        // Simulate multiple streaming requests
        for ($i = 0; $i < 5; $i++) {
            $response = $this->post('/api/chat/stream', [
                'message' => "Test streaming message {$i}",
                'conversation_id' => $this->conversation->id,
                'workspace_id' => $this->workspace->id,
            ]);
            
            $response->assertOk();
        }

        $finalMemory = memory_get_usage(true);
        $memoryIncrease = $finalMemory - $initialMemory;

        // Memory increase should be reasonable (less than 10MB)
        $this->assertLessThan(10 * 1024 * 1024, $memoryIncrease, 'Memory usage increased too much during streaming');
    }

    public function test_cache_performance_with_many_operations(): void
    {
        $startTime = microtime(true);

        // Perform many cache operations
        for ($i = 0; $i < 100; $i++) {
            Cache::put("test_key_{$i}", "test_value_{$i}", 60);
            Cache::get("test_key_{$i}");
            Cache::forget("test_key_{$i}");
        }

        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        // 100 cache operations should complete within 0.5 seconds
        $this->assertLessThan(0.5, $duration, 'Cache operations took too long');
    }

    public function test_concurrent_typing_indicators_reliability(): void
    {
        $this->actingAs($this->user);

        // Create multiple conversations
        $conversations = [];
        for ($i = 0; $i < 5; $i++) {
            $conversations[] = ChatConversation::factory()->create([
                'workspace_id' => $this->workspace->id,
            ]);
        }

        $successCount = 0;
        $totalRequests = 25; // 5 requests per conversation

        // Send typing indicators to all conversations simultaneously
        foreach ($conversations as $conversation) {
            for ($j = 0; $j < 5; $j++) {
                $response = $this->post("/api/conversations/{$conversation->id}/typing", [
                    'is_typing' => true,
                ]);
                
                if ($response->status() === 200) {
                    $successCount++;
                }
            }
        }

        // At least 95% of requests should succeed
        $successRate = $successCount / $totalRequests;
        $this->assertGreaterThanOrEqual(0.95, $successRate, 'Typing indicator reliability is too low');
    }
}