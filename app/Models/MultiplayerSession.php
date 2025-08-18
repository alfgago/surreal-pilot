<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MultiplayerSession extends Model
{
    use HasFactory;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'workspace_id',
        'fargate_task_arn',
        'ngrok_url',
        'session_url',
        'status',
        'max_players',
        'current_players',
        'expires_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'max_players' => 'integer',
            'current_players' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Get the workspace that owns this multiplayer session.
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Check if the session is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active' && $this->expires_at->isFuture();
    }

    /**
     * Check if the session is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if the session is starting.
     */
    public function isStarting(): bool
    {
        return $this->status === 'starting';
    }

    /**
     * Check if the session is stopping.
     */
    public function isStopping(): bool
    {
        return $this->status === 'stopping';
    }

    /**
     * Check if the session is stopped.
     */
    public function isStopped(): bool
    {
        return $this->status === 'stopped';
    }

    /**
     * Check if the session can accept more players.
     */
    public function canAcceptPlayers(): bool
    {
        return $this->isActive() && $this->current_players < $this->max_players;
    }

    /**
     * Get the remaining time until expiration.
     */
    public function getRemainingTime(): int
    {
        if ($this->isExpired()) {
            return 0;
        }

        // Calculate seconds until expiration using timestamp difference
        $now = now();
        if ($this->expires_at->isAfter($now)) {
            return $this->expires_at->timestamp - $now->timestamp;
        }
        
        return 0;
    }

    /**
     * Mark the session as active.
     */
    public function markAsActive(): void
    {
        $this->update(['status' => 'active']);
    }

    /**
     * Mark the session as stopping.
     */
    public function markAsStopping(): void
    {
        $this->update(['status' => 'stopping']);
    }

    /**
     * Mark the session as stopped.
     */
    public function markAsStopped(): void
    {
        $this->update(['status' => 'stopped']);
    }

    /**
     * Increment the current player count.
     */
    public function addPlayer(): void
    {
        if ($this->canAcceptPlayers()) {
            $this->increment('current_players');
        }
    }

    /**
     * Decrement the current player count.
     */
    public function removePlayer(): void
    {
        if ($this->current_players > 0) {
            $this->decrement('current_players');
        }
    }

    /**
     * Scope to get active sessions.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
                    ->where('expires_at', '>', now());
    }

    /**
     * Scope to get expired sessions.
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    /**
     * Scope to get sessions by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Validate multiplayer session data before saving.
     */
    protected static function boot()
    {
        parent::boot();
        
        static::saving(function ($session) {
            // Validate status
            $validStatuses = ['starting', 'active', 'stopping', 'stopped'];
            if (!in_array($session->status, $validStatuses)) {
                throw new \InvalidArgumentException("Invalid session status: {$session->status}. Must be one of: " . implode(', ', $validStatuses));
            }
            
            // Validate expires_at is in the future for new sessions
            if (!$session->exists && $session->expires_at && $session->expires_at->isPast()) {
                throw new \InvalidArgumentException("Session expiration time must be in the future.");
            }
            
            // Validate max_players is reasonable
            if ($session->max_players < 1 || $session->max_players > 100) {
                throw new \InvalidArgumentException("Max players must be between 1 and 100.");
            }
            
            // Validate current_players doesn't exceed max_players
            if ($session->current_players > $session->max_players) {
                throw new \InvalidArgumentException("Current players cannot exceed max players.");
            }
        });
    }
}
