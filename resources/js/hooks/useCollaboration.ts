import { useState, useEffect, useCallback } from 'react';
import { router } from '@inertiajs/react';

interface User {
    id: number;
    name: string;
    avatar?: string;
}

interface Connection {
    user: User;
    status: 'connected' | 'disconnected' | 'reconnecting';
    timestamp: string;
    metadata?: {
        last_activity?: string;
        typing_in?: string;
        current_tool?: string;
    };
}

interface CollaborationStats {
    active_users: number;
    total_connections: number;
    recent_messages: number;
    connections: Connection[];
}

interface UseCollaborationOptions {
    workspaceId: number;
    autoJoin?: boolean;
    refreshInterval?: number;
    currentTool?: string;
}

export function useCollaboration({
    workspaceId,
    autoJoin = true,
    refreshInterval = 10000,
    currentTool
}: UseCollaborationOptions) {
    const [stats, setStats] = useState<CollaborationStats>({
        active_users: 0,
        total_connections: 0,
        recent_messages: 0,
        connections: [],
    });
    const [isConnected, setIsConnected] = useState(false);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const fetchStats = useCallback(async () => {
        try {
            const response = await fetch(`/api/workspaces/${workspaceId}/collaboration-stats`);
            if (response.ok) {
                const data = await response.json();
                setStats(data);
                setError(null);
            } else {
                throw new Error('Failed to fetch collaboration stats');
            }
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Unknown error');
            console.error('Failed to fetch collaboration stats:', err);
        } finally {
            setIsLoading(false);
        }
    }, [workspaceId]);

    const joinCollaboration = useCallback(async (tool?: string) => {
        try {
            const response = await fetch(`/api/workspaces/${workspaceId}/collaboration/join`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    tool: tool || currentTool,
                    metadata: {
                        joined_at: new Date().toISOString(),
                        user_agent: navigator.userAgent,
                    },
                }),
            });

            if (response.ok) {
                setIsConnected(true);
                setError(null);
                // Refresh stats immediately after joining
                await fetchStats();
            } else {
                throw new Error('Failed to join collaboration');
            }
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to join collaboration');
            console.error('Failed to join collaboration:', err);
        }
    }, [workspaceId, currentTool, fetchStats]);

    const leaveCollaboration = useCallback(async () => {
        try {
            const response = await fetch(`/api/workspaces/${workspaceId}/collaboration/leave`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });

            if (response.ok) {
                setIsConnected(false);
                setError(null);
            } else {
                throw new Error('Failed to leave collaboration');
            }
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to leave collaboration');
            console.error('Failed to leave collaboration:', err);
        }
    }, [workspaceId]);

    const updateCurrentTool = useCallback(async (tool: string) => {
        if (isConnected) {
            try {
                await joinCollaboration(tool);
            } catch (err) {
                console.error('Failed to update current tool:', err);
            }
        }
    }, [isConnected, joinCollaboration]);

    // Auto-join collaboration on mount
    useEffect(() => {
        if (autoJoin) {
            joinCollaboration();
        } else {
            fetchStats();
        }

        // Cleanup: leave collaboration when component unmounts
        return () => {
            if (isConnected) {
                leaveCollaboration();
            }
        };
    }, [autoJoin, joinCollaboration, fetchStats]);

    // Set up periodic refresh
    useEffect(() => {
        const interval = setInterval(fetchStats, refreshInterval);
        return () => clearInterval(interval);
    }, [fetchStats, refreshInterval]);

    // Handle page visibility changes
    useEffect(() => {
        const handleVisibilityChange = () => {
            if (document.hidden && isConnected) {
                // Page is hidden, leave collaboration
                leaveCollaboration();
            } else if (!document.hidden && autoJoin && !isConnected) {
                // Page is visible again, rejoin collaboration
                joinCollaboration();
            }
        };

        document.addEventListener('visibilitychange', handleVisibilityChange);
        return () => document.removeEventListener('visibilitychange', handleVisibilityChange);
    }, [isConnected, autoJoin, joinCollaboration, leaveCollaboration]);

    // Handle beforeunload to clean up collaboration
    useEffect(() => {
        const handleBeforeUnload = () => {
            if (isConnected) {
                // Use sendBeacon for reliable cleanup
                navigator.sendBeacon(
                    `/api/workspaces/${workspaceId}/collaboration/leave`,
                    JSON.stringify({})
                );
            }
        };

        window.addEventListener('beforeunload', handleBeforeUnload);
        return () => window.removeEventListener('beforeunload', handleBeforeUnload);
    }, [isConnected, workspaceId]);

    return {
        stats,
        isConnected,
        isLoading,
        error,
        joinCollaboration,
        leaveCollaboration,
        updateCurrentTool,
        refreshStats: fetchStats,
    };
}