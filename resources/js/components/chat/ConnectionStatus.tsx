import React, { useState, useEffect } from 'react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ConnectionStatus as ConnectionStatusType } from '@/services/realtimeChatService';
import { 
    Wifi, 
    WifiOff, 
    Loader2, 
    Users, 
    Activity,
    AlertCircle
} from 'lucide-react';

interface ConnectionStatusProps {
    workspaceId: number;
    className?: string;
}

export default function ConnectionStatus({ workspaceId, className = '' }: ConnectionStatusProps) {
    const [connections, setConnections] = useState<ConnectionStatusType[]>([]);
    const [connectionState, setConnectionState] = useState<{
        sse: boolean;
        websocket: boolean;
        reconnectAttempts: number;
    }>({
        sse: false,
        websocket: false,
        reconnectAttempts: 0,
    });

    useEffect(() => {
        const handleConnectionStatus = (event: CustomEvent) => {
            const { user, workspace_id, status, timestamp, metadata } = event.detail;
            if (workspace_id === workspaceId) {
                setConnections(prev => {
                    const existing = prev.find(conn => conn.user.id === user.id);
                    const newConnection: ConnectionStatusType = {
                        user,
                        status,
                        timestamp,
                        metadata,
                    };

                    if (existing) {
                        return prev.map(conn => 
                            conn.user.id === user.id ? newConnection : conn
                        );
                    } else {
                        return [...prev, newConnection];
                    }
                });
            }
        };

        // Listen for connection status events
        window.addEventListener('chat:connection-status', handleConnectionStatus as EventListener);

        return () => {
            window.removeEventListener('chat:connection-status', handleConnectionStatus as EventListener);
        };
    }, [workspaceId]);

    const getConnectionIcon = (status: string) => {
        switch (status) {
            case 'connected':
                return <Wifi className="w-4 h-4 text-green-600" />;
            case 'reconnecting':
                return <Loader2 className="w-4 h-4 text-yellow-600 animate-spin" />;
            case 'disconnected':
            default:
                return <WifiOff className="w-4 h-4 text-red-600" />;
        }
    };

    const getConnectionBadgeVariant = (status: string) => {
        switch (status) {
            case 'connected':
                return 'default';
            case 'reconnecting':
                return 'secondary';
            case 'disconnected':
            default:
                return 'destructive';
        }
    };

    const getOverallStatus = () => {
        if (!connectionState.websocket && !connectionState.sse) {
            return { status: 'disconnected', color: 'text-red-600' };
        } else if (connectionState.reconnectAttempts > 0) {
            return { status: 'reconnecting', color: 'text-yellow-600' };
        } else {
            return { status: 'connected', color: 'text-green-600' };
        }
    };

    const connectedUsers = connections.filter(conn => conn.status === 'connected');
    const overallStatus = getOverallStatus();

    return (
        <Card className={className}>
            <CardHeader className="pb-3">
                <CardTitle className="flex items-center space-x-2 text-sm">
                    <Activity className="w-4 h-4" />
                    <span>Connection Status</span>
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
                {/* Overall Connection Status */}
                <div className="flex items-center justify-between">
                    <span className="text-sm text-muted-foreground">Real-time Features</span>
                    <div className="flex items-center space-x-2">
                        {getConnectionIcon(overallStatus.status)}
                        <Badge 
                            variant={getConnectionBadgeVariant(overallStatus.status)}
                            className="text-xs"
                        >
                            {overallStatus.status}
                        </Badge>
                    </div>
                </div>

                {/* Connection Details */}
                <div className="space-y-2 text-xs">
                    <div className="flex items-center justify-between">
                        <span className="text-muted-foreground">WebSocket</span>
                        <div className="flex items-center space-x-1">
                            {connectionState.websocket ? (
                                <Wifi className="w-3 h-3 text-green-600" />
                            ) : (
                                <WifiOff className="w-3 h-3 text-red-600" />
                            )}
                            <span className={connectionState.websocket ? 'text-green-600' : 'text-red-600'}>
                                {connectionState.websocket ? 'Connected' : 'Disconnected'}
                            </span>
                        </div>
                    </div>

                    <div className="flex items-center justify-between">
                        <span className="text-muted-foreground">Streaming</span>
                        <div className="flex items-center space-x-1">
                            {connectionState.sse ? (
                                <Wifi className="w-3 h-3 text-green-600" />
                            ) : (
                                <WifiOff className="w-3 h-3 text-muted-foreground" />
                            )}
                            <span className={connectionState.sse ? 'text-green-600' : 'text-muted-foreground'}>
                                {connectionState.sse ? 'Active' : 'Inactive'}
                            </span>
                        </div>
                    </div>

                    {connectionState.reconnectAttempts > 0 && (
                        <div className="flex items-center space-x-2 text-yellow-600">
                            <AlertCircle className="w-3 h-3" />
                            <span>Reconnecting... (Attempt {connectionState.reconnectAttempts})</span>
                        </div>
                    )}
                </div>

                {/* Active Users */}
                {connectedUsers.length > 0 && (
                    <div className="border-t pt-3">
                        <div className="flex items-center space-x-2 mb-2">
                            <Users className="w-4 h-4 text-muted-foreground" />
                            <span className="text-sm text-muted-foreground">
                                Active Users ({connectedUsers.length})
                            </span>
                        </div>
                        <div className="space-y-1">
                            {connectedUsers.slice(0, 3).map((connection) => (
                                <div key={connection.user.id} className="flex items-center space-x-2">
                                    <div className="w-2 h-2 bg-green-500 rounded-full" />
                                    <span className="text-xs">{connection.user.name}</span>
                                </div>
                            ))}
                            {connectedUsers.length > 3 && (
                                <div className="text-xs text-muted-foreground">
                                    +{connectedUsers.length - 3} more
                                </div>
                            )}
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

// Simple connection indicator for inline use
interface SimpleConnectionIndicatorProps {
    isConnected: boolean;
    isReconnecting?: boolean;
    className?: string;
}

export function SimpleConnectionIndicator({ 
    isConnected, 
    isReconnecting = false, 
    className = '' 
}: SimpleConnectionIndicatorProps) {
    if (isReconnecting) {
        return (
            <div className={`flex items-center space-x-1 ${className}`}>
                <Loader2 className="w-3 h-3 text-yellow-600 animate-spin" />
                <span className="text-xs text-yellow-600">Reconnecting...</span>
            </div>
        );
    }

    return (
        <div className={`flex items-center space-x-1 ${className}`}>
            {isConnected ? (
                <>
                    <div className="w-2 h-2 bg-green-500 rounded-full animate-pulse" />
                    <span className="text-xs text-green-600">Connected</span>
                </>
            ) : (
                <>
                    <div className="w-2 h-2 bg-red-500 rounded-full" />
                    <span className="text-xs text-red-600">Disconnected</span>
                </>
            )}
        </div>
    );
}