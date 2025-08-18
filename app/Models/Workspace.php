<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workspace extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'name',
        'engine_type',
        'template_id',
        'mcp_port',
        'mcp_pid',
        'preview_url',
        'published_url',
        'status',
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
            'mcp_port' => 'integer',
            'mcp_pid' => 'integer',
        ];
    }

    /**
     * Get the company that owns the workspace.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the multiplayer sessions for this workspace.
     */
    public function multiplayerSessions(): HasMany
    {
        return $this->hasMany(MultiplayerSession::class);
    }

    /**
     * Get the chat conversations for this workspace.
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(ChatConversation::class);
    }

    /**
     * Get the games for this workspace.
     */
    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }

    /**
     * Get the patches for this workspace.
     */
    public function patches(): HasMany
    {
        return $this->hasMany(Patch::class);
    }

    /**
     * Check if this workspace uses PlayCanvas engine.
     */
    public function isPlayCanvas(): bool
    {
        return $this->engine_type === 'playcanvas';
    }

    /**
     * Check if this workspace uses Unreal Engine.
     */
    public function isUnreal(): bool
    {
        return $this->engine_type === 'unreal';
    }

    /**
     * Get the preview URL for this workspace.
     */
    public function getPreviewUrl(): string
    {
        return $this->preview_url ?? '';
    }

    /**
     * Get the published URL for this workspace.
     */
    public function getPublishedUrl(): string
    {
        return $this->published_url ?? '';
    }

    /**
     * Get the MCP server URL for this workspace.
     */
    public function getMcpServerUrl(): string
    {
        if (!$this->mcp_port) {
            return '';
        }
        
        return "http://localhost:{$this->mcp_port}";
    }

    /**
     * Check if the workspace is ready for use.
     */
    public function isReady(): bool
    {
        return $this->status === 'ready';
    }

    /**
     * Check if the workspace is published.
     */
    public function isPublished(): bool
    {
        return $this->status === 'published' && !empty($this->published_url);
    }

    /**
     * Validate engine type compatibility before saving.
     */
    protected static function boot()
    {
        parent::boot();
        
        static::saving(function ($workspace) {
            // Validate engine type
            if (!in_array($workspace->engine_type, ['playcanvas', 'unreal'])) {
                throw new \InvalidArgumentException("Invalid engine type: {$workspace->engine_type}. Must be 'playcanvas' or 'unreal'.");
            }
            
            // Validate PlayCanvas-specific requirements (skip in testing)
            if (!app()->environment('testing')) {
                if ($workspace->engine_type === 'playcanvas' && $workspace->status === 'ready') {
                    if (empty($workspace->mcp_port)) {
                        throw new \InvalidArgumentException("PlayCanvas workspaces must have an MCP port when ready.");
                    }
                }
            }
            
            // Validate template compatibility if template_id is set
            if ($workspace->template_id) {
                $template = \App\Models\DemoTemplate::find($workspace->template_id);
                if ($template && $template->engine_type !== $workspace->engine_type) {
                    throw new \InvalidArgumentException("Template engine type ({$template->engine_type}) does not match workspace engine type ({$workspace->engine_type}).");
                }
            }
        });
        
        static::creating(function ($workspace) {
            // Ensure MCP port uniqueness for PlayCanvas workspaces
            if ($workspace->engine_type === 'playcanvas' && $workspace->mcp_port) {
                $existingWorkspace = static::where('mcp_port', $workspace->mcp_port)
                    ->where('id', '!=', $workspace->id ?? 0)
                    ->first();
                    
                if ($existingWorkspace) {
                    throw new \InvalidArgumentException("MCP port {$workspace->mcp_port} is already in use by workspace {$existingWorkspace->id}.");
                }
            }
        });
        
        static::updating(function ($workspace) {
            // Prevent engine type changes after creation
            if ($workspace->isDirty('engine_type') && $workspace->exists) {
                throw new \InvalidArgumentException("Engine type cannot be changed after workspace creation for cross-engine compatibility.");
            }
            
            // Ensure MCP port uniqueness for PlayCanvas workspaces
            if ($workspace->engine_type === 'playcanvas' && $workspace->mcp_port && $workspace->isDirty('mcp_port')) {
                $existingWorkspace = static::where('mcp_port', $workspace->mcp_port)
                    ->where('id', '!=', $workspace->id)
                    ->first();
                    
                if ($existingWorkspace) {
                    throw new \InvalidArgumentException("MCP port {$workspace->mcp_port} is already in use by workspace {$existingWorkspace->id}.");
                }
            }
        });
    }

    /**
     * Scope to filter workspaces by engine type.
     */
    public function scopeByEngine($query, string $engineType)
    {
        return $query->where('engine_type', $engineType);
    }

    /**
     * Scope to filter workspaces by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Get active conversations for this workspace.
     */
    public function getActiveConversations()
    {
        return $this->conversations()->orderBy('updated_at', 'desc')->get();
    }

    /**
     * Get recent games for this workspace.
     */
    public function getRecentGames()
    {
        return $this->games()->orderBy('updated_at', 'desc')->get();
    }
}
