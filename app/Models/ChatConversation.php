<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatConversation extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'workspace_id',
        'user_id',
        'title',
        'description',
    ];

    /**
     * Get the workspace that owns the conversation.
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Get the user that owns the conversation.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the messages for the conversation.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id');
    }

    /**
     * Get the games created in this conversation.
     */
    public function games(): HasMany
    {
        return $this->hasMany(Game::class, 'conversation_id');
    }

    /**
     * Get the last message in the conversation.
     */
    public function getLastMessage(): ?ChatMessage
    {
        return $this->messages()->latest()->first();
    }

    /**
     * Get the count of messages in the conversation.
     */
    public function getMessageCount(): int
    {
        return $this->messages()->count();
    }

    /**
     * Update the conversation's updated_at timestamp.
     */
    public function updateActivity(): void
    {
        $this->touch();
    }

    /**
     * Get the last message preview text.
     */
    public function getLastMessagePreview(): string
    {
        $lastMessage = $this->getLastMessage();
        if (!$lastMessage) {
            return 'No messages yet';
        }

        $content = $lastMessage->content;
        return strlen($content) > 100 ? substr($content, 0, 100) . '...' : $content;
    }
}
