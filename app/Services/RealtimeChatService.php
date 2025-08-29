<?php

namespace App\Services;

use App\Events\ChatMessageReceived;
use App\Events\UserTyping;
use App\Events\ChatConnectionStatus;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class RealtimeChatService
{
    private const TYPING_TIMEOUT = 3; // seconds
    private const CONNECTION_TIMEOUT = 30; // seconds

    /**
     * Broadcast that a user is typing in a conversation
     */
    public function broadcastTyping(User $user, ChatConversation $conversation, bool $isTyping = true): void
    {
        try {
            // Store typing state in cache with expiration
            $cacheKey = "typing:{$conversation->id}:{$user->id}";
            
            if ($isTyping) {
                Cache::put($cacheKey, true, self::TYPING_TIMEOUT);
            } else {
                Cache::forget($cacheKey);
            }

            // Broadcast typing event
            broadcast(new UserTyping($user, $conversation, $isTyping));

            Log::debug('Typing status broadcasted', [
                'user_id' => $user->id,
                'conversation_id' => $conversation->id,
                'is_typing' => $isTyping,
            ]);

        } catch (\Throwable $e) {
            Log::error('Failed to broadcast typing status', [
                'user_id' => $user->id,
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Broadcast a new chat message
     */
    public function broadcastMessage(ChatMessage $message, ChatConversation $conversation): void
    {
        try {
            broadcast(new ChatMessageReceived($message, $conversation));

            Log::info('Chat message broadcasted', [
                'message_id' => $message->id,
                'conversation_id' => $conversation->id,
                'role' => $message->role,
            ]);

        } catch (\Throwable $e) {
            Log::error('Failed to broadcast chat message', [
                'message_id' => $message->id,
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Broadcast connection status change
     */
    public function broadcastConnectionStatus(
        User $user, 
        Workspace $workspace, 
        string $status, 
        ?array $metadata = null
    ): void {
        try {
            // Store connection status in cache
            $cacheKey = "connection:{$workspace->id}:{$user->id}";
            Cache::put($cacheKey, [
                'status' => $status,
                'timestamp' => now()->toISOString(),
                'metadata' => $metadata,
            ], self::CONNECTION_TIMEOUT);

            broadcast(new ChatConnectionStatus($user, $workspace, $status, $metadata));

            Log::debug('Connection status broadcasted', [
                'user_id' => $user->id,
                'workspace_id' => $workspace->id,
                'status' => $status,
            ]);

        } catch (\Throwable $e) {
            Log::error('Failed to broadcast connection status', [
                'user_id' => $user->id,
                'workspace_id' => $workspace->id,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get currently typing users in a conversation
     */
    public function getTypingUsers(ChatConversation $conversation): array
    {
        try {
            $pattern = "typing:{$conversation->id}:*";
            
            $typingUsers = [];
            $redis = Cache::getRedis();
            
            // Get all keys matching the pattern
            $keys = $redis->keys($pattern);
            
            foreach ($keys as $key) {
                // Remove the cache prefix if it exists
                $cleanKey = str_replace(config('cache.prefix') . ':', '', $key);
                
                if (Cache::has($cleanKey)) {
                    $keyParts = explode(':', $cleanKey);
                    if (count($keyParts) >= 3) {
                        $userId = $keyParts[2];
                        $user = User::find($userId);
                        if ($user) {
                            $typingUsers[] = [
                                'id' => $user->id,
                                'name' => $user->name,
                            ];
                        }
                    }
                }
            }

            return $typingUsers;

        } catch (\Throwable $e) {
            Log::error('Failed to get typing users', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Get connection status for users in a workspace
     */
    public function getConnectionStatuses(Workspace $workspace): array
    {
        try {
            $pattern = "connection:{$workspace->id}:*";
            
            // Use Laravel's cache store to get keys
            $connections = [];
            $redis = Cache::getRedis();
            
            // Get all keys matching the pattern
            $keys = $redis->keys($pattern);
            
            foreach ($keys as $key) {
                // Remove the cache prefix if it exists
                $cleanKey = str_replace(config('cache.prefix') . ':', '', $key);
                $data = Cache::get($cleanKey);
                
                if ($data && is_array($data)) {
                    $keyParts = explode(':', $cleanKey);
                    if (count($keyParts) >= 3) {
                        $userId = $keyParts[2];
                        $user = User::find($userId);
                        if ($user) {
                            $connections[] = [
                                'user' => [
                                    'id' => $user->id,
                                    'name' => $user->name,
                                ],
                                'status' => $data['status'],
                                'timestamp' => $data['timestamp'],
                                'metadata' => $data['metadata'] ?? null,
                            ];
                        }
                    }
                }
            }

            return $connections;

        } catch (\Throwable $e) {
            Log::error('Failed to get connection statuses', [
                'workspace_id' => $workspace->id,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Clean up expired typing indicators
     */
    public function cleanupExpiredTyping(): void
    {
        try {
            $pattern = "typing:*";
            $redis = Cache::getRedis();
            $keys = $redis->keys($pattern);
            
            foreach ($keys as $key) {
                $cleanKey = str_replace(config('cache.prefix') . ':', '', $key);
                
                if (!Cache::has($cleanKey)) {
                    // Key has expired, broadcast stop typing
                    $parts = explode(':', $cleanKey);
                    if (count($parts) === 3) {
                        $conversationId = $parts[1];
                        $userId = $parts[2];
                        
                        $conversation = ChatConversation::find($conversationId);
                        $user = User::find($userId);
                        
                        if ($conversation && $user) {
                            broadcast(new UserTyping($user, $conversation, false));
                        }
                    }
                }
            }

        } catch (\Throwable $e) {
            Log::error('Failed to cleanup expired typing indicators', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Clean up expired connections
     */
    public function cleanupExpiredConnections(): void
    {
        try {
            $pattern = "connection:*";
            $redis = Cache::getRedis();
            $keys = $redis->keys($pattern);
            
            foreach ($keys as $key) {
                $cleanKey = str_replace(config('cache.prefix') . ':', '', $key);
                
                if (!Cache::has($cleanKey)) {
                    // Connection has expired, broadcast disconnected status
                    $parts = explode(':', $cleanKey);
                    if (count($parts) === 3) {
                        $workspaceId = $parts[1];
                        $userId = $parts[2];
                        
                        $workspace = Workspace::find($workspaceId);
                        $user = User::find($userId);
                        
                        if ($workspace && $user) {
                            broadcast(new ChatConnectionStatus($user, $workspace, 'disconnected'));
                        }
                    }
                }
            }

        } catch (\Throwable $e) {
            Log::error('Failed to cleanup expired connections', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get real-time chat statistics for a workspace
     */
    public function getChatStatistics(Workspace $workspace): array
    {
        try {
            $connections = $this->getConnectionStatuses($workspace);
            $activeUsers = collect($connections)->where('status', 'connected')->count();
            
            // Get recent message activity (last hour)
            $recentMessages = ChatMessage::whereHas('conversation.workspace', function ($query) use ($workspace) {
                $query->where('id', $workspace->id);
            })
            ->where('created_at', '>=', now()->subHour())
            ->count();

            return [
                'active_users' => $activeUsers,
                'total_connections' => count($connections),
                'recent_messages' => $recentMessages,
                'connections' => $connections,
            ];

        } catch (\Throwable $e) {
            Log::error('Failed to get chat statistics', [
                'workspace_id' => $workspace->id,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'active_users' => 0,
                'total_connections' => 0,
                'recent_messages' => 0,
                'connections' => [],
            ];
        }
    }
}