<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'metadata',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    /**
     * Get the conversation that owns the message.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }

    /**
     * Check if the message is from a user.
     */
    public function isFromUser(): bool
    {
        return $this->role === 'user';
    }

    /**
     * Check if the message is from the assistant.
     */
    public function isFromAssistant(): bool
    {
        return $this->role === 'assistant';
    }

    /**
     * Check if the message is a system message.
     */
    public function isSystemMessage(): bool
    {
        return $this->role === 'system';
    }

    /**
     * Get the role display name.
     */
    public function getRoleDisplayName(): string
    {
        return match($this->role) {
            'user' => 'You',
            'assistant' => 'SurrealPilot',
            'system' => 'System',
            default => ucfirst($this->role)
        };
    }
}
