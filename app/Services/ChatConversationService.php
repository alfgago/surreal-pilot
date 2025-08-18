<?php

namespace App\Services;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Collection;

class ChatConversationService
{
    /**
     * Create a new conversation for a workspace.
     */
    public function createConversation(Workspace $workspace, ?string $title = null): ChatConversation
    {
        return ChatConversation::create([
            'workspace_id' => $workspace->id,
            'title' => $title,
        ]);
    }

    /**
     * Get all conversations for a workspace.
     */
    public function getWorkspaceConversations(Workspace $workspace): Collection
    {
        return $workspace->conversations()
            ->orderBy('updated_at', 'desc')
            ->get();
    }

    /**
     * Get messages for a conversation.
     */
    public function getConversationMessages(ChatConversation $conversation): Collection
    {
        return $conversation->messages()
            ->orderBy('created_at', 'asc')
            ->get();
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
        $message = ChatMessage::create([
            'conversation_id' => $conversation->id,
            'role' => $role,
            'content' => $content,
            'metadata' => $metadata,
        ]);

        // Update conversation activity
        $conversation->updateActivity();

        return $message;
    }

    /**
     * Update conversation activity timestamp.
     */
    public function updateConversationActivity(ChatConversation $conversation): void
    {
        $conversation->updateActivity();
    }

    /**
     * Delete a conversation and all its messages.
     */
    public function deleteConversation(ChatConversation $conversation): bool
    {
        return $conversation->delete();
    }

    /**
     * Update conversation details.
     */
    public function updateConversation(ChatConversation $conversation, array $data): ChatConversation
    {
        $conversation->update($data);
        return $conversation->fresh();
    }

    /**
     * Get recent conversations across all workspaces for a company.
     */
    public function getRecentConversations(int $companyId, int $limit = 10): Collection
    {
        return ChatConversation::whereHas('workspace', function ($query) use ($companyId) {
            $query->where('company_id', $companyId);
        })
        ->orderBy('updated_at', 'desc')
        ->limit($limit)
        ->with(['workspace', 'messages' => function ($query) {
            $query->latest()->limit(1);
        }])
        ->get();
    }

    /**
     * Get conversation statistics.
     */
    public function getConversationStats(ChatConversation $conversation): array
    {
        return [
            'message_count' => $conversation->getMessageCount(),
            'last_activity' => $conversation->updated_at,
            'created_at' => $conversation->created_at,
            'games_created' => $conversation->games()->count(),
        ];
    }

    /**
     * Search conversations by content.
     */
    public function searchConversations(Workspace $workspace, string $query): Collection
    {
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
    }
}