import { useState, useEffect } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { 
    Users, 
    Wifi, 
    WifiOff, 
    MessageCircle, 
    Activity,
    Clock,
    Zap
} from 'lucide-react';

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

interface Props {
    workspaceId: number;
    sessionId?: string;
    onUserClick?: (user: User) => void;
}

const statusColors = {
    connected: 'bg-green-100 text-green-800',
    disconnected: 'bg-gray-100 text-gray-800',
    reconnecting: 'bg-yellow-100 text-yellow-800',
};

const statusIcons = {
    connected: Wifi,
    disconnected: WifiOff,
    reconnecting: Activity,
};

export default function CollaborationStatus({ workspaceId, sessionId, onUserClick }: Props) {
    const [stats, setStats] = useState<CollaborationStats>({
        active_users: 0,
        total_connections: 0,
        recent_messages: 0,
        connections: [],
    });
    const [isLoading, setIsLoading] = useState(true);

    const fetchStats = async () => {
        try {
            const response = await fetch(`/api/workspaces/${workspaceId}/collaboration-stats`);
            if (response.ok) {
                const data = await response.json();
                setStats(data);
            }
        } catch (error) {
            console.error('Failed to fetch collaboration stats:', error);
        } finally {
            setIsLoading(false);
        }
    };

    useEffect(() => {
        fetchStats();
        
        // Refresh stats every 10 seconds
        const interval = setInterval(fetchStats, 10000);
        
        return () => clearInterval(interval);
    }, [workspaceId]);

    const formatLastActivity = (timestamp: string) => {
        const date = new Date(timestamp);
        const now = new Date();
        const diffInMinutes = Math.floor((now.getTime() - date.getTime()) / (1000 * 60));
        
        if (diffInMinutes < 1) return 'Just now';
        if (diffInMinutes < 60) return `${diffInMinutes}m ago`;
        
        const diffInHours = Math.floor(diffInMinutes / 60);
        if (diffInHours < 24) return `${diffInHours}h ago`;
        
        return date.toLocaleDateString();
    };

    const getInitials = (name: string) => {
        return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
    };

    if (isLoading) {
        return (
            <Card>
                <CardContent className="p-6">
                    <div className="flex items-center justify-center">
                        <Activity className="h-6 w-6 animate-spin text-muted-foreground" />
                        <span className="ml-2 text-muted-foreground">Loading collaboration status...</span>
                    </div>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Users className="h-5 w-5" />
                    Real-time Collaboration
                </CardTitle>
                <CardDescription>
                    See who's currently working in this workspace
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                {/* Quick Stats */}
                <div className="grid grid-cols-3 gap-4">
                    <div className="text-center">
                        <div className="text-2xl font-bold text-green-600">{stats.active_users}</div>
                        <div className="text-xs text-muted-foreground">Active Users</div>
                    </div>
                    <div className="text-center">
                        <div className="text-2xl font-bold text-blue-600">{stats.recent_messages}</div>
                        <div className="text-xs text-muted-foreground">Recent Messages</div>
                    </div>
                    <div className="text-center">
                        <div className="text-2xl font-bold text-purple-600">{stats.total_connections}</div>
                        <div className="text-xs text-muted-foreground">Total Connections</div>
                    </div>
                </div>

                {/* Active Users */}
                {stats.connections.length === 0 ? (
                    <div className="text-center py-8">
                        <Users className="h-12 w-12 text-muted-foreground mx-auto mb-2" />
                        <p className="text-muted-foreground">No active collaborators</p>
                        <p className="text-sm text-muted-foreground">
                            Share your workspace to start collaborating
                        </p>
                    </div>
                ) : (
                    <div className="space-y-3">
                        <h4 className="font-medium text-sm">Active Collaborators</h4>
                        {stats.connections.map((connection) => {
                            const StatusIcon = statusIcons[connection.status];
                            
                            return (
                                <div
                                    key={connection.user.id}
                                    className="flex items-center justify-between p-3 rounded-lg border hover:bg-muted/50 transition-colors"
                                >
                                    <div className="flex items-center space-x-3">
                                        <div className="relative">
                                            <Avatar className="h-8 w-8">
                                                <AvatarImage src={connection.user.avatar} />
                                                <AvatarFallback className="text-xs">
                                                    {getInitials(connection.user.name)}
                                                </AvatarFallback>
                                            </Avatar>
                                            <div className={`absolute -bottom-1 -right-1 w-3 h-3 rounded-full border-2 border-white ${
                                                connection.status === 'connected' ? 'bg-green-500' :
                                                connection.status === 'reconnecting' ? 'bg-yellow-500' : 'bg-gray-400'
                                            }`} />
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-center gap-2">
                                                <p className="font-medium text-sm truncate">
                                                    {connection.user.name}
                                                </p>
                                                <Badge className={statusColors[connection.status]} size="sm">
                                                    <StatusIcon className="h-3 w-3 mr-1" />
                                                    {connection.status}
                                                </Badge>
                                            </div>
                                            <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                                <Clock className="h-3 w-3" />
                                                <span>{formatLastActivity(connection.timestamp)}</span>
                                                {connection.metadata?.typing_in && (
                                                    <>
                                                        <MessageCircle className="h-3 w-3" />
                                                        <span>Typing...</span>
                                                    </>
                                                )}
                                                {connection.metadata?.current_tool && (
                                                    <>
                                                        <Zap className="h-3 w-3" />
                                                        <span>Using {connection.metadata.current_tool}</span>
                                                    </>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                    {onUserClick && (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => onUserClick(connection.user)}
                                        >
                                            <MessageCircle className="h-4 w-4" />
                                        </Button>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                )}

                {/* Refresh Button */}
                <Button
                    variant="outline"
                    size="sm"
                    onClick={fetchStats}
                    className="w-full"
                >
                    <Activity className="h-4 w-4 mr-2" />
                    Refresh Status
                </Button>
            </CardContent>
        </Card>
    );
}