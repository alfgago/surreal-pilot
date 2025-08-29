<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Collection;

class CacheService
{
    public function getCachedWorkspaceGames(int $workspaceId): ?Collection
    {
        return null;
    }

    public function cacheWorkspaceGames(int $workspaceId, Collection $games): void
    {
        // Simple implementation for now
    }

    public function invalidateGameCaches($game): void
    {
        // Simple implementation for now
    }

    public function invalidateConversationCaches($conversation): void
    {
        // Simple implementation for now
        // In a full implementation, this would clear conversation-related caches
    }
}