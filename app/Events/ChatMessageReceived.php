<?php

namespace App\Events;

use App\Models\ChatMessage;
use App\Models\ChatConversation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ChatMessage $message,
        public ChatConversation $conversation
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('workspace.' . $this->conversation->workspace_id),
            new PrivateChannel('conversation.' . $this->conversation->id),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'message' => [
                'id' => $this->message->id,
                'role' => $this->message->role,
                'content' => $this->message->content,
                'metadata' => $this->message->metadata,
                'created_at' => $this->message->created_at->toISOString(),
                'role_display_name' => $this->message->getRoleDisplayName(),
            ],
            'conversation' => [
                'id' => $this->conversation->id,
                'title' => $this->conversation->title,
                'updated_at' => $this->conversation->updated_at->toISOString(),
            ],
            'workspace_id' => $this->conversation->workspace_id,
        ];
    }

    /**
     * Get the broadcast event name.
     */
    public function broadcastAs(): string
    {
        return 'message.received';
    }
}