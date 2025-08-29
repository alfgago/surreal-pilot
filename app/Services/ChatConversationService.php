<?php

namespace App\Services;

use App\Exceptions\ConversationException;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Workspace;
use App\Services\ErrorMonitoringService;
use App\Services\CacheService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChatConversationService
{
    public function __construct(
        private ErrorMonitoringService $errorMonitoring,
        private CacheService $cacheService
    ) {}

    /**
     * Create a new conversation for a workspace.
     */
    public function createConversation(Workspace $workspace, ?string $title = null): ChatConversation
    {
        try {
            DB::beginTransaction();

            $conversation = ChatConversation::create([
                'workspace_id' => $workspace->id,
                'title' => $title,
            ]);

            DB::commit();

            // Invalidate related caches
            $this->cacheService->invalidateConversationCaches($conversation);

            Log::info('Conversation created successfully', [
                'conversation_id' => $conversation->id,
                'workspace_id' => $workspace->id,
                'title' => $title,
            ]);

            return $conversation;

        } catch (\Throwable $e) {
            DB::rollBack();

            $this->errorMonitoring->trackError(
                'conversation_creation_failed',
                "Failed to create conversation: {$e->getMessage()}",
                auth()->user(),
                $workspace->company,
                [
                    'workspace_id' => $workspace->id,
                    'title' => $title,
                    'exception_class' => get_class($e),
                ]
            );

            Log::error('Failed to create conversation', [
                'workspace_id' => $workspace->id,
                'title' => $title,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            throw ConversationException::conversationCreationFailed($e->getMessage());
        }
    }

    /**
     * Get all conversations for a workspace.
     */
    public function getWorkspaceConversations(Workspace $workspace): Collection
    {
        try {
            // Try to get from cache first
            $cached = $this->cacheService->getCachedWorkspaceConversations($workspace->id);
            if ($cached !== null) {
                return $cached;
            }

            // Fetch from database with optimized query
            $conversations = $workspace->conversations()
                ->select(['id', 'workspace_id', 'title', 'description', 'created_at', 'updated_at'])
                ->orderBy('updated_at', 'desc')
                ->get();

            // Cache the result
            $this->cacheService->cacheWorkspaceConversations($workspace->id, $conversations);

            return $conversations;
        } catch (\Throwable $e) {
            Log::error('Failed to get workspace conversations', [
                'workspace_id' => $workspace->id,
                'error' => $e->getMessage(),
            ]);

            $this->errorMonitoring->trackError(
                'get_conversations_failed',
                "Failed to get workspace conversations: {$e->getMessage()}",
                auth()->user(),
                $workspace->company,
                ['workspace_id' => $workspace->id]
            );

            // Return empty collection on error
            return new Collection();
        }
    }

    /**
     * Get messages for a conversation.
     */
    public function getConversationMessages(ChatConversation $conversation): Collection
    {
        try {
            return $conversation->messages()
                ->orderBy('created_at', 'asc')
                ->get();
        } catch (\Throwable $e) {
            Log::error('Failed to get conversation messages', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);

            $this->errorMonitoring->trackError(
                'get_messages_failed',
                "Failed to get conversation messages: {$e->getMessage()}",
                auth()->user(),
                $conversation->workspace->company,
                ['conversation_id' => $conversation->id]
            );

            // Return empty collection on error
            return new Collection();
        }
    }

    /**
     * Add a message to a conversation.
     */
    public function addMessage(
        ChatConversation $conversation, 
        string $role, 
        string $content, 
        ?array $metadata = null
    ): ChatMessage {
        try {
            DB::beginTransaction();

            $message = ChatMessage::create([
                'conversation_id' => $conversation->id,
                'role' => $role,
                'content' => $content,
                'metadata' => $metadata,
            ]);

            // Update conversation activity
            $conversation->updateActivity();

            DB::commit();

            // Invalidate related caches
            $this->cacheService->invalidateConversationCaches($conversation);

            Log::info('Message added to conversation', [
                'conversation_id' => $conversation->id,
                'message_id' => $message->id,
                'role' => $role,
                'content_length' => strlen($content),
            ]);

            return $message;

        } catch (\Throwable $e) {
            DB::rollBack();

            $this->errorMonitoring->trackError(
                'add_message_failed',
                "Failed to add message to conversation: {$e->getMessage()}",
                auth()->user(),
                $conversation->workspace->company,
                [
                    'conversation_id' => $conversation->id,
                    'role' => $role,
                    'content_length' => strlen($content),
                    'exception_class' => get_class($e),
                ]
            );

            Log::error('Failed to add message to conversation', [
                'conversation_id' => $conversation->id,
                'role' => $role,
                'error' => $e->getMessage(),
            ]);

            throw ConversationException::messageAddFailed($e->getMessage());
        }
    }

    /**
     * Update conversation activity timestamp.
     */
    public function updateConversationActivity(ChatConversation $conversation): void
    {
        try {
            $conversation->updateActivity();
        } catch (\Throwable $e) {
            Log::error('Failed to update conversation activity', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);

            // Don't throw exception for activity updates as it's not critical
        }
    }

    /**
     * Delete a conversation and all its messages.
     */
    public function deleteConversation(ChatConversation $conversation): bool
    {
        try {
            DB::beginTransaction();

            $conversationId = $conversation->id;
            $workspaceId = $conversation->workspace_id;

            $result = $conversation->delete();

            // Invalidate related caches before deletion
            $this->cacheService->invalidateConversationCaches($conversation);

            DB::commit();

            Log::info('Conversation deleted successfully', [
                'conversation_id' => $conversationId,
                'workspace_id' => $workspaceId,
            ]);

            return $result;

        } catch (\Throwable $e) {
            DB::rollBack();

            $this->errorMonitoring->trackError(
                'conversation_delete_failed',
                "Failed to delete conversation: {$e->getMessage()}",
                auth()->user(),
                $conversation->workspace->company,
                [
                    'conversation_id' => $conversation->id,
                    'exception_class' => get_class($e),
                ]
            );

            Log::error('Failed to delete conversation', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);

            throw ConversationException::conversationDeleteFailed($e->getMessage());
        }
    }

    /**
     * Update conversation details.
     */
    public function updateConversation(ChatConversation $conversation, array $data): ChatConversation
    {
        try {
            DB::beginTransaction();

            $conversation->update($data);
            $updatedConversation = $conversation->fresh();

            DB::commit();

            Log::info('Conversation updated successfully', [
                'conversation_id' => $conversation->id,
                'updated_fields' => array_keys($data),
            ]);

            return $updatedConversation;

        } catch (\Throwable $e) {
            DB::rollBack();

            $this->errorMonitoring->trackError(
                'conversation_update_failed',
                "Failed to update conversation: {$e->getMessage()}",
                auth()->user(),
                $conversation->workspace->company,
                [
                    'conversation_id' => $conversation->id,
                    'update_data' => $data,
                    'exception_class' => get_class($e),
                ]
            );

            Log::error('Failed to update conversation', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);

            throw ConversationException::conversationUpdateFailed($e->getMessage());
        }
    }

    /**
     * Get recent conversations across all workspaces for a company.
     */
    public function getRecentConversations(int $companyId, int $limit = 10): Collection
    {
        try {
            // Try to get from cache first
            $cached = $this->cacheService->getCachedRecentConversations($companyId, $limit);
            if ($cached !== null) {
                return $cached;
            }

            // Fetch from database with optimized query
            $conversations = ChatConversation::select([
                'chat_conversations.id',
                'chat_conversations.workspace_id',
                'chat_conversations.title',
                'chat_conversations.description',
                'chat_conversations.created_at',
                'chat_conversations.updated_at'
            ])
            ->whereHas('workspace', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })
            ->orderBy('updated_at', 'desc')
            ->limit($limit)
            ->with([
                'workspace:id,company_id,name,engine_type',
                'messages' => function ($query) {
                    $query->select(['id', 'conversation_id', 'role', 'content', 'created_at'])
                          ->latest()
                          ->limit(1);
                }
            ])
            ->get();

            // Cache the result
            $this->cacheService->cacheRecentConversations($companyId, $conversations, $limit);

            return $conversations;
        } catch (\Throwable $e) {
            Log::error('Failed to get recent conversations', [
                'company_id' => $companyId,
                'limit' => $limit,
                'error' => $e->getMessage(),
            ]);

            $this->errorMonitoring->trackError(
                'get_recent_conversations_failed',
                "Failed to get recent conversations: {$e->getMessage()}",
                auth()->user(),
                null,
                ['company_id' => $companyId, 'limit' => $limit]
            );

            // Return empty collection on error
            return new Collection();
        }
    }

    /**
     * Get conversation statistics.
     */
    public function getConversationStats(ChatConversation $conversation): array
    {
        try {
            return [
                'message_count' => $conversation->getMessageCount(),
                'last_activity' => $conversation->updated_at,
                'created_at' => $conversation->created_at,
                'games_created' => $conversation->games()->count(),
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to get conversation stats', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);

            // Return basic stats on error
            return [
                'message_count' => 0,
                'last_activity' => $conversation->updated_at,
                'created_at' => $conversation->created_at,
                'games_created' => 0,
            ];
        }
    }

    /**
     * Search conversations by content.
     */
    public function searchConversations(Workspace $workspace, string $query): Collection
    {
        try {
            return $workspace->conversations()
                ->where(function ($q) use ($query) {
                    $q->where('title', 'like', "%{$query}%")
                      ->orWhere('description', 'like', "%{$query}%")
                      ->orWhereHas('messages', function ($messageQuery) use ($query) {
                          $messageQuery->where('content', 'like', "%{$query}%");
                      });
                })
                ->orderBy('updated_at', 'desc')
                ->get();
        } catch (\Throwable $e) {
            Log::error('Failed to search conversations', [
                'workspace_id' => $workspace->id,
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            $this->errorMonitoring->trackError(
                'search_conversations_failed',
                "Failed to search conversations: {$e->getMessage()}",
                auth()->user(),
                $workspace->company,
                ['workspace_id' => $workspace->id, 'query' => $query]
            );

            // Return empty collection on error
            return new Collection();
        }
    }

    /**
     * Get paginated workspace conversations with search support.
     */
    public function getPaginatedWorkspaceConversations(
        Workspace $workspace, 
        int $page = 1, 
        int $limit = 20, 
        ?string $search = null
    ): array {
        try {
            $query = $workspace->conversations()
                ->select([
                    'id', 
                    'workspace_id', 
                    'title', 
                    'description', 
                    'created_at', 
                    'updated_at'
                ])
                ->with([
                    'messages' => function ($query) {
                        $query->select(['id', 'conversation_id', 'role', 'content', 'created_at'])
                              ->latest()
                              ->limit(1);
                    }
                ]);

            // Add search filter if provided
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhereHas('messages', function ($messageQuery) use ($search) {
                          $messageQuery->where('content', 'like', "%{$search}%");
                      });
                });
            }

            $query->orderBy('updated_at', 'desc');

            // Get total count for pagination
            $total = $query->count();
            
            // Calculate offset
            $offset = ($page - 1) * $limit;
            
            // Get conversations for current page
            $conversations = $query->offset($offset)->limit($limit)->get();

            // Add computed fields
            $conversations->each(function ($conversation) {
                $lastMessage = $conversation->messages->first();
                $conversation->last_message_preview = $lastMessage 
                    ? substr(strip_tags($lastMessage->content), 0, 100) 
                    : null;
                $conversation->message_count = $conversation->getMessageCount();
                
                // Remove messages relation to reduce payload size
                unset($conversation->messages);
            });

            return [
                'conversations' => $conversations,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $total,
                    'last_page' => ceil($total / $limit),
                    'has_more_pages' => $page < ceil($total / $limit),
                    'from' => $offset + 1,
                    'to' => min($offset + $limit, $total),
                ]
            ];

        } catch (\Throwable $e) {
            Log::error('Failed to get paginated workspace conversations', [
                'workspace_id' => $workspace->id,
                'page' => $page,
                'limit' => $limit,
                'search' => $search,
                'error' => $e->getMessage(),
            ]);

            $this->errorMonitoring->trackError(
                'get_paginated_conversations_failed',
                "Failed to get paginated workspace conversations: {$e->getMessage()}",
                auth()->user(),
                $workspace->company,
                [
                    'workspace_id' => $workspace->id,
                    'page' => $page,
                    'limit' => $limit,
                    'search' => $search,
                ]
            );

            return [
                'conversations' => new Collection(),
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => $limit,
                    'total' => 0,
                    'last_page' => 1,
                    'has_more_pages' => false,
                    'from' => 0,
                    'to' => 0,
                ]
            ];
        }
    }

    /**
     * Get paginated recent conversations across all workspaces for a company.
     */
    public function getPaginatedRecentConversations(
        int $companyId, 
        int $page = 1, 
        int $limit = 20, 
        ?string $search = null
    ): array {
        try {
            $query = ChatConversation::select([
                'chat_conversations.id',
                'chat_conversations.workspace_id',
                'chat_conversations.title',
                'chat_conversations.description',
                'chat_conversations.created_at',
                'chat_conversations.updated_at'
            ])
            ->whereHas('workspace', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            })
            ->with([
                'workspace:id,company_id,name,engine_type',
                'messages' => function ($query) {
                    $query->select(['id', 'conversation_id', 'role', 'content', 'created_at'])
                          ->latest()
                          ->limit(1);
                }
            ]);

            // Add search filter if provided
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhereHas('messages', function ($messageQuery) use ($search) {
                          $messageQuery->where('content', 'like', "%{$search}%");
                      });
                });
            }

            $query->orderBy('updated_at', 'desc');

            // Get total count for pagination
            $total = $query->count();
            
            // Calculate offset
            $offset = ($page - 1) * $limit;
            
            // Get conversations for current page
            $conversations = $query->offset($offset)->limit($limit)->get();

            // Add computed fields
            $conversations->each(function ($conversation) {
                $lastMessage = $conversation->messages->first();
                $conversation->last_message_preview = $lastMessage 
                    ? substr(strip_tags($lastMessage->content), 0, 100) 
                    : null;
                $conversation->message_count = $conversation->getMessageCount();
                
                // Remove messages relation to reduce payload size
                unset($conversation->messages);
            });

            return [
                'conversations' => $conversations,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $total,
                    'last_page' => ceil($total / $limit),
                    'has_more_pages' => $page < ceil($total / $limit),
                    'from' => $offset + 1,
                    'to' => min($offset + $limit, $total),
                ]
            ];

        } catch (\Throwable $e) {
            Log::error('Failed to get paginated recent conversations', [
                'company_id' => $companyId,
                'page' => $page,
                'limit' => $limit,
                'search' => $search,
                'error' => $e->getMessage(),
            ]);

            $this->errorMonitoring->trackError(
                'get_paginated_recent_conversations_failed',
                "Failed to get paginated recent conversations: {$e->getMessage()}",
                auth()->user(),
                null,
                [
                    'company_id' => $companyId,
                    'page' => $page,
                    'limit' => $limit,
                    'search' => $search,
                ]
            );

            return [
                'conversations' => new Collection(),
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => $limit,
                    'total' => 0,
                    'last_page' => 1,
                    'has_more_pages' => false,
                    'from' => 0,
                    'to' => 0,
                ]
            ];
        }
    }
}