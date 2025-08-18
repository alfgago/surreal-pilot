<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Game extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'workspace_id',
        'conversation_id',
        'title',
        'description',
        'preview_url',
        'published_url',
        'thumbnail_url',
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
     * Get the workspace that owns the game.
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Get the conversation that created the game.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }

    /**
     * Check if the game is published.
     */
    public function isPublished(): bool
    {
        return !empty($this->published_url);
    }

    /**
     * Check if the game has a preview.
     */
    public function hasPreview(): bool
    {
        return !empty($this->preview_url);
    }

    /**
     * Check if the game has a thumbnail.
     */
    public function hasThumbnail(): bool
    {
        return !empty($this->thumbnail_url);
    }

    /**
     * Generate a thumbnail URL for the game.
     */
    public function generateThumbnail(): ?string
    {
        // This would integrate with a thumbnail generation service
        // For now, return a placeholder or existing thumbnail
        return $this->thumbnail_url;
    }

    /**
     * Get the game's engine type from its workspace.
     */
    public function getEngineType(): string
    {
        return $this->workspace->engine_type ?? 'unknown';
    }

    /**
     * Get the display URL for the game (published or preview).
     */
    public function getDisplayUrl(): ?string
    {
        return $this->published_url ?? $this->preview_url;
    }
}
