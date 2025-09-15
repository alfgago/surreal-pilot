<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

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
        'status',
        'version',
        'tags',
        'play_count',
        'last_played_at',
        'published_at',
        'is_public',
        'share_token',
        'sharing_settings',
        'build_status',
        'build_log',
        'last_build_at',
        'deployment_config',
        'custom_domain',
        'domain_status',
        'domain_config',
        'thinking_history',
        'game_mechanics',
        'interaction_count',
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
            'tags' => 'array',
            'sharing_settings' => 'array',
            'deployment_config' => 'array',
            'domain_config' => 'array',
            'thinking_history' => 'array',
            'game_mechanics' => 'array',
            'last_played_at' => 'datetime',
            'published_at' => 'datetime',
            'last_build_at' => 'datetime',
            'is_public' => 'boolean',
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
     * Get the builds for this game.
     */
    public function builds(): HasMany
    {
        return $this->hasMany(GameBuild::class)->orderBy('created_at', 'desc');
    }

    /**
     * Get the latest build for this game.
     */
    public function latestBuild(): ?GameBuild
    {
        return $this->builds()->first();
    }

    /**
     * Check if the game is published.
     */
    public function isPublished(): bool
    {
        return $this->status === 'published' && !empty($this->published_url);
    }

    /**
     * Check if the game is a draft.
     */
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Check if the game is archived.
     */
    public function isArchived(): bool
    {
        return $this->status === 'archived';
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
     * Get the game's engine type.
     */
    public function getEngineType(): string
    {
        return $this->metadata['engine_type'] ?? $this->workspace->engine_type ?? 'unknown';
    }

    /**
     * Get the current interaction count.
     */
    public function getInteractionCount(): int
    {
        return $this->metadata['interaction_count'] ?? 0;
    }

    /**
     * Increment the interaction count.
     */
    public function incrementInteractionCount(): void
    {
        $metadata = $this->metadata ?? [];
        $metadata['interaction_count'] = $this->getInteractionCount() + 1;
        $this->update(['metadata' => $metadata]);
    }

    /**
     * Add a thinking process entry to the history.
     */
    public function addThinkingProcess(array $thinking): void
    {
        $metadata = $this->metadata ?? [];
        $history = $metadata['thinking_history'] ?? [];
        $history[] = array_merge($thinking, [
            'timestamp' => now()->toISOString(),
            'interaction' => $this->getInteractionCount() + 1,
        ]);
        
        $metadata['thinking_history'] = $history;
        $this->update(['metadata' => $metadata]);
    }

    /**
     * Get the latest thinking process entry.
     */
    public function getLatestThinking(): ?array
    {
        $history = $this->metadata['thinking_history'] ?? [];
        return empty($history) ? null : end($history);
    }

    /**
     * Update game mechanics data.
     */
    public function updateGameMechanics(array $mechanics): void
    {
        $metadata = $this->metadata ?? [];
        $currentMechanics = $metadata['game_mechanics'] ?? [];
        $updatedMechanics = array_merge($currentMechanics, $mechanics);
        
        $metadata['game_mechanics'] = $updatedMechanics;
        $this->update(['metadata' => $metadata]);
    }

    /**
     * Get specific game mechanic value.
     */
    public function getGameMechanic(string $key, $default = null)
    {
        $mechanics = $this->metadata['game_mechanics'] ?? [];
        return $mechanics[$key] ?? $default;
    }

    /**
     * Get the display URL for the game (published or preview).
     */
    public function getDisplayUrl(): ?string
    {
        return $this->published_url ?? $this->preview_url;
    }

    /**
     * Generate a unique share token for the game.
     */
    public function generateShareToken(): string
    {
        $token = Str::random(32);
        $this->update(['share_token' => $token]);
        return $token;
    }

    /**
     * Get the public share URL for the game.
     */
    public function getShareUrl(): ?string
    {
        if (!$this->share_token) {
            return null;
        }
        
        return url("/games/shared/{$this->share_token}");
    }

    /**
     * Get the embed URL for the game.
     */
    public function getEmbedUrl(): ?string
    {
        if (!$this->share_token) {
            return null;
        }
        
        return url("/games/embed/{$this->share_token}");
    }

    /**
     * Check if the game is currently building.
     */
    public function isBuilding(): bool
    {
        return $this->build_status === 'building';
    }

    /**
     * Check if the last build was successful.
     */
    public function hasSuccessfulBuild(): bool
    {
        return $this->build_status === 'success';
    }

    /**
     * Check if the last build failed.
     */
    public function hasBuildFailed(): bool
    {
        return $this->build_status === 'failed';
    }

    /**
     * Increment the play count.
     */
    public function incrementPlayCount(): void
    {
        $this->increment('play_count');
        $this->update(['last_played_at' => now()]);
    }

    /**
     * Check if the game has a custom domain configured.
     */
    public function hasCustomDomain(): bool
    {
        return !empty($this->custom_domain);
    }

    /**
     * Check if the custom domain is active and verified.
     */
    public function isDomainActive(): bool
    {
        return $this->domain_status === 'active';
    }

    /**
     * Check if the custom domain is pending verification.
     */
    public function isDomainPending(): bool
    {
        return $this->domain_status === 'pending';
    }

    /**
     * Check if the custom domain setup failed.
     */
    public function isDomainFailed(): bool
    {
        return $this->domain_status === 'failed';
    }

    /**
     * Get the custom domain URL for the game.
     */
    public function getCustomDomainUrl(): ?string
    {
        if (!$this->hasCustomDomain()) {
            return null;
        }

        $protocol = ($this->domain_config['ssl_enabled'] ?? false) ? 'https' : 'http';
        return "{$protocol}://{$this->custom_domain}";
    }

    /**
     * Get the primary access URL (custom domain, published, or preview).
     */
    public function getPrimaryUrl(): ?string
    {
        if ($this->hasCustomDomain() && $this->isDomainActive()) {
            return $this->getCustomDomainUrl();
        }

        return $this->getDisplayUrl();
    }

    /**
     * Update domain configuration.
     */
    public function updateDomainConfig(array $config): void
    {
        $currentConfig = $this->domain_config ?? [];
        $updatedConfig = array_merge($currentConfig, $config);
        
        $this->update(['domain_config' => $updatedConfig]);
    }

    /**
     * Set domain status with optional message.
     */
    public function setDomainStatus(string $status, ?string $message = null): void
    {
        $updates = ['domain_status' => $status];
        
        if ($message) {
            $config = $this->domain_config ?? [];
            $config['status_message'] = $message;
            $config['last_check'] = now()->toISOString();
            $updates['domain_config'] = $config;
        }
        
        $this->update($updates);
    }
}
