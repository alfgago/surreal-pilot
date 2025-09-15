<?php

namespace App\Services;

use App\Models\GDevelopGameSession;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class GDevelopSessionManager
{
    /**
     * Archive inactive sessions older than specified days.
     */
    public function archiveInactiveSessions(int $inactiveDays = 7): int
    {
        $sessions = GDevelopGameSession::getSessionsForArchival($inactiveDays);
        $archivedCount = 0;

        foreach ($sessions as $session) {
            try {
                $session->archive();
                $archivedCount++;
                
                Log::info("Archived GDevelop session", [
                    'session_id' => $session->session_id,
                    'workspace_id' => $session->workspace_id,
                    'user_id' => $session->user_id,
                    'last_modified' => $session->last_modified,
                ]);
            } catch (\Exception $e) {
                Log::error("Failed to archive GDevelop session", [
                    'session_id' => $session->session_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $archivedCount;
    }

    /**
     * Clean up archived sessions older than specified days.
     */
    public function cleanupArchivedSessions(int $archiveDays = 30): int
    {
        $sessions = GDevelopGameSession::getSessionsForCleanup($archiveDays);
        $cleanedCount = 0;

        foreach ($sessions as $session) {
            try {
                // Clean up session files
                $this->cleanupSessionFiles($session);
                
                // Delete the session record
                $session->delete();
                $cleanedCount++;
                
                Log::info("Cleaned up GDevelop session", [
                    'session_id' => $session->session_id,
                    'workspace_id' => $session->workspace_id,
                    'user_id' => $session->user_id,
                    'last_modified' => $session->last_modified,
                ]);
            } catch (\Exception $e) {
                Log::error("Failed to cleanup GDevelop session", [
                    'session_id' => $session->session_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $cleanedCount;
    }

    /**
     * Clean up files associated with a session.
     */
    public function cleanupSessionFiles(GDevelopGameSession $session): void
    {
        $sessionPath = $session->getStoragePath();
        
        if (Storage::exists($sessionPath)) {
            Storage::deleteDirectory($sessionPath);
            
            Log::info("Deleted session files", [
                'session_id' => $session->session_id,
                'path' => $sessionPath,
            ]);
        }
    }

    /**
     * Get session statistics.
     */
    public function getSessionStatistics(): array
    {
        return [
            'total_sessions' => GDevelopGameSession::count(),
            'active_sessions' => GDevelopGameSession::active()->count(),
            'archived_sessions' => GDevelopGameSession::archived()->count(),
            'error_sessions' => GDevelopGameSession::where('status', 'error')->count(),
            'sessions_last_24h' => GDevelopGameSession::where('created_at', '>=', Carbon::now()->subDay())->count(),
            'sessions_last_week' => GDevelopGameSession::where('created_at', '>=', Carbon::now()->subWeek())->count(),
            'sessions_last_month' => GDevelopGameSession::where('created_at', '>=', Carbon::now()->subMonth())->count(),
        ];
    }

    /**
     * Create a new game session.
     */
    public function createSession(int $workspaceId, int $userId, ?string $gameTitle = null): GDevelopGameSession
    {
        return GDevelopGameSession::create([
            'workspace_id' => $workspaceId,
            'user_id' => $userId,
            'game_title' => $gameTitle,
            'status' => 'active',
        ]);
    }

    /**
     * Find or create a session for a workspace and user.
     */
    public function findOrCreateSession(int $workspaceId, int $userId, ?string $gameTitle = null): GDevelopGameSession
    {
        // Try to find an active session for this workspace and user
        $session = GDevelopGameSession::active()
            ->forWorkspace($workspaceId)
            ->forUser($userId)
            ->orderBy('last_modified', 'desc')
            ->first();

        if (!$session) {
            $session = $this->createSession($workspaceId, $userId, $gameTitle);
        }

        return $session;
    }

    /**
     * Get active sessions for a workspace.
     */
    public function getActiveSessionsForWorkspace(int $workspaceId): \Illuminate\Database\Eloquent\Collection
    {
        return GDevelopGameSession::active()
            ->forWorkspace($workspaceId)
            ->orderBy('last_modified', 'desc')
            ->get();
    }

    /**
     * Get active sessions for a user.
     */
    public function getActiveSessionsForUser(int $userId): \Illuminate\Database\Eloquent\Collection
    {
        return GDevelopGameSession::active()
            ->forUser($userId)
            ->orderBy('last_modified', 'desc')
            ->get();
    }

    /**
     * Restore an archived session.
     */
    public function restoreSession(string $sessionId): ?GDevelopGameSession
    {
        $session = GDevelopGameSession::where('session_id', $sessionId)->first();
        
        if ($session && $session->isArchived()) {
            $session->restore();
            
            Log::info("Restored GDevelop session", [
                'session_id' => $session->session_id,
                'workspace_id' => $session->workspace_id,
                'user_id' => $session->user_id,
            ]);
            
            return $session;
        }
        
        return null;
    }

    /**
     * Get storage usage for all sessions.
     */
    public function getStorageUsage(): array
    {
        $totalSize = 0;
        $sessionCount = 0;
        $sessions = GDevelopGameSession::all();

        foreach ($sessions as $session) {
            $sessionPath = $session->getStoragePath();
            if (Storage::exists($sessionPath)) {
                $size = $this->getDirectorySize($sessionPath);
                $totalSize += $size;
                $sessionCount++;
            }
        }

        return [
            'total_size_bytes' => $totalSize,
            'total_size_mb' => round($totalSize / 1024 / 1024, 2),
            'session_count' => $sessionCount,
            'average_size_mb' => $sessionCount > 0 ? round(($totalSize / 1024 / 1024) / $sessionCount, 2) : 0,
        ];
    }

    /**
     * Get a session by session ID.
     */
    public function getSession(string $sessionId): ?GDevelopGameSession
    {
        return GDevelopGameSession::where('session_id', $sessionId)->first();
    }

    /**
     * Get or create a session by session ID.
     */
    public function getOrCreateSession(string $sessionId, ?int $workspaceId = null, ?int $userId = null): GDevelopGameSession
    {
        $session = $this->getSession($sessionId);
        
        if (!$session) {
            $session = GDevelopGameSession::create([
                'session_id' => $sessionId,
                'workspace_id' => $workspaceId,
                'user_id' => $userId,
                'status' => 'active',
            ]);
        }
        
        return $session;
    }

    /**
     * Delete a session by session ID.
     */
    public function deleteSession(string $sessionId): bool
    {
        $session = $this->getSession($sessionId);
        
        if ($session) {
            // Clean up session files
            $this->cleanupSessionFiles($session);
            
            // Delete the session record
            $session->delete();
            
            Log::info("Deleted GDevelop session", [
                'session_id' => $sessionId,
                'workspace_id' => $session->workspace_id,
                'user_id' => $session->user_id,
            ]);
            
            return true;
        }
        
        return false;
    }

    /**
     * Get the size of a directory in bytes.
     */
    private function getDirectorySize(string $path): int
    {
        $size = 0;
        $files = Storage::allFiles($path);
        
        foreach ($files as $file) {
            $size += Storage::size($file);
        }
        
        return $size;
    }
}