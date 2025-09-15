<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Carbon\Carbon;

class GDevelopGameSession extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'gdevelop_game_sessions';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'workspace_id',
        'user_id',
        'session_id',
        'game_title',
        'game_json',
        'assets_manifest',
        'version',
        'last_modified',
        'preview_url',
        'export_url',
        'status',
        'error_log',
        'conversation_history',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'game_json' => 'array',
            'assets_manifest' => 'array',
            'conversation_history' => 'array',
            'last_modified' => 'datetime',
            'version' => 'integer',
        ];
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'error_log',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($session) {
            if (empty($session->session_id)) {
                $session->session_id = Str::uuid()->toString();
            }
            
            if (empty($session->version)) {
                $session->version = 1;
            }
            
            if (empty($session->status)) {
                $session->status = 'active';
            }
            
            if (empty($session->last_modified)) {
                $session->last_modified = now();
            }
        });

        static::updating(function ($session) {
            // Auto-increment version when game_json changes
            if ($session->isDirty('game_json')) {
                $session->version = ($session->getOriginal('version') ?? 0) + 1;
                $session->last_modified = now();
            }
        });
    }

    /**
     * Get the workspace that owns this game session.
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Get the user who owns this game session.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the session is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if the session is archived.
     */
    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }

    /**
     * Check if the session has errors.
     */
    public function hasErrors(): bool
    {
        return $this->status === 'error' && !empty($this->error_log);
    }

    /**
     * Get the game title or generate one from session ID.
     */
    public function getGameTitle(): string
    {
        return $this->game_title ?? "Game " . substr($this->session_id, 0, 8);
    }

    /**
     * Get the current game JSON or return empty structure.
     */
    public function getGameJson(): array
    {
        return $this->game_json ?? [];
    }

    /**
     * Get the assets manifest or return empty array.
     */
    public function getAssetsManifest(): array
    {
        return $this->assets_manifest ?? [];
    }

    /**
     * Get the conversation history or return empty array.
     */
    public function getConversationHistory(): array
    {
        return $this->conversation_history ?? [];
    }

    /**
     * Add a message to the conversation history.
     */
    public function addToConversationHistory(string $role, string $content, array $metadata = []): void
    {
        $history = $this->getConversationHistory();
        
        $message = [
            'role' => $role,
            'content' => $content,
            'timestamp' => now()->toISOString(),
        ];

        if (!empty($metadata)) {
            $message = array_merge($message, $metadata);
        }

        $history[] = $message;
        
        $this->update(['conversation_history' => $history]);
    }

    /**
     * Add a user message to the conversation history.
     */
    public function addUserMessage(string $content): void
    {
        $this->addToConversationHistory('user', $content);
    }

    /**
     * Add an AI message to the conversation history with thinking process.
     */
    public function addAIMessage(string $content, string $thinkingProcess = ''): void
    {
        $metadata = [];
        if (!empty($thinkingProcess)) {
            $metadata['thinking_process'] = $thinkingProcess;
        }
        
        $this->addToConversationHistory('assistant', $content, $metadata);
    }

    /**
     * Update the game JSON and increment version.
     */
    public function updateGameJson(array $gameJson): void
    {
        $this->update([
            'game_json' => $gameJson,
            'last_modified' => now(),
        ]);
    }

    /**
     * Update the assets manifest.
     */
    public function updateAssetsManifest(array $assetsManifest): void
    {
        $this->update([
            'assets_manifest' => $assetsManifest,
            'last_modified' => now(),
        ]);
    }

    /**
     * Set the preview URL for this session.
     */
    public function setPreviewUrl(string $url): void
    {
        $this->update(['preview_url' => $url]);
    }

    /**
     * Set the export URL for this session.
     */
    public function setExportUrl(string $url): void
    {
        $this->update(['export_url' => $url]);
    }

    /**
     * Mark the session as having an error.
     */
    public function markAsError(string $errorMessage): void
    {
        $this->update([
            'status' => 'error',
            'error_log' => $errorMessage,
        ]);
    }

    /**
     * Archive the session.
     */
    public function archive(): void
    {
        $this->update(['status' => 'archived']);
    }

    /**
     * Restore an archived session.
     */
    public function restore(): void
    {
        $this->update(['status' => 'active']);
    }

    /**
     * Get the session storage path.
     */
    public function getStoragePath(): string
    {
        return "gdevelop/sessions/{$this->session_id}";
    }

    /**
     * Get the assets storage path.
     */
    public function getAssetsPath(): string
    {
        return $this->getStoragePath() . '/assets';
    }

    /**
     * Get the exports storage path.
     */
    public function getExportsPath(): string
    {
        return $this->getStoragePath() . '/exports';
    }

    /**
     * Scope to filter active sessions.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to filter archived sessions.
     */
    public function scopeArchived($query)
    {
        return $query->where('status', 'archived');
    }

    /**
     * Scope to filter sessions by workspace.
     */
    public function scopeForWorkspace($query, int $workspaceId)
    {
        return $query->where('workspace_id', $workspaceId);
    }

    /**
     * Scope to filter sessions by user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to filter sessions older than specified days.
     */
    public function scopeOlderThan($query, int $days)
    {
        return $query->where('last_modified', '<', Carbon::now()->subDays($days));
    }

    /**
     * Get sessions that should be cleaned up (archived sessions older than specified days).
     */
    public static function getSessionsForCleanup(int $archiveDays = 30): \Illuminate\Database\Eloquent\Collection
    {
        return static::archived()
            ->olderThan($archiveDays)
            ->get();
    }

    /**
     * Get sessions that should be archived (active sessions older than specified days).
     */
    public static function getSessionsForArchival(int $inactiveDays = 7): \Illuminate\Database\Eloquent\Collection
    {
        return static::active()
            ->olderThan($inactiveDays)
            ->get();
    }
}