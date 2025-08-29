import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import MainLayout from '@/Layouts/MainLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { 
    Users, 
    Play, 
    Square, 
    ExternalLink, 
    Clock, 
    Server,
    Plus,
    Activity,
    Timer,
    UserCheck
} from 'lucide-react';
import { useForm } from '@inertiajs/react';

interface Workspace {
    id: number;
    name: string;
    engine_type: string;
    status: string;
}

interface MultiplayerSession {
    id: string;
    workspace: {
        id: number;
        name: string;
        engine_type: string;
    };
    session_url?: string;
    status: 'starting' | 'active' | 'stopping' | 'stopped' | 'failed';
    max_players: number;
    current_players: number;
    expires_at: string;
    created_at: string;
    updated_at: string;
}

interface Props {
    activeSessions: MultiplayerSession[];
    recentSessions: MultiplayerSession[];
    workspaces: Workspace[];
    stats: {
        active_sessions: number;
        total_sessions_this_month: number;
        total_players_this_month: number;
        average_session_duration: number;
    };
}

const statusColors = {
    starting: 'bg-yellow-100 text-yellow-800',
    active: 'bg-green-100 text-green-800',
    stopping: 'bg-orange-100 text-orange-800',
    stopped: 'bg-gray-100 text-gray-800',
    failed: 'bg-red-100 text-red-800',
};

export default function MultiplayerIndex({ activeSessions, recentSessions, workspaces, stats }: Props) {
    const [isCreateDialogOpen, setIsCreateDialogOpen] = useState(false);
    const [isLoading, setIsLoading] = useState<string | null>(null);

    const { data, setData, post, processing, errors, reset } = useForm({
        workspace_id: '',
        max_players: 8,
        ttl_minutes: 60,
    });

    const handleCreateSession = (e: React.FormEvent) => {
        e.preventDefault();
        post('/api/multiplayer/start', {
            onSuccess: () => {
                setIsCreateDialogOpen(false);
                reset();
                router.reload({ only: ['activeSessions', 'stats'] });
            },
        });
    };

    const handleStopSession = async (sessionId: string) => {
        setIsLoading(sessionId);
        try {
            await router.post(`/api/multiplayer/${sessionId}/stop`, {}, {
                onSuccess: () => {
                    router.reload({ only: ['activeSessions', 'recentSessions', 'stats'] });
                },
                onFinish: () => setIsLoading(null),
            });
        } catch (error) {
            setIsLoading(null);
        }
    };

    const formatDuration = (minutes: number) => {
        if (minutes < 60) return `${Math.round(minutes)}m`;
        const hours = Math.floor(minutes / 60);
        const remainingMinutes = Math.round(minutes % 60);
        return `${hours}h ${remainingMinutes}m`;
    };

    const formatTimeRemaining = (expiresAt: string) => {
        const now = new Date();
        const expires = new Date(expiresAt);
        const diffInMinutes = Math.max(0, (expires.getTime() - now.getTime()) / (1000 * 60));
        
        if (diffInMinutes < 60) {
            return `${Math.round(diffInMinutes)}m remaining`;
        }
        const hours = Math.floor(diffInMinutes / 60);
        const minutes = Math.round(diffInMinutes % 60);
        return `${hours}h ${minutes}m remaining`;
    };

    return (
        <MainLayout>
            <Head title="Multiplayer Sessions" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex justify-between items-start">
                    <div>
                        <h1 className="text-3xl font-bold">Multiplayer Sessions</h1>
                        <p className="text-muted-foreground mt-2">
                            Manage and monitor your multiplayer game sessions
                        </p>
                    </div>
                    <Dialog open={isCreateDialogOpen} onOpenChange={setIsCreateDialogOpen}>
                        <DialogTrigger asChild>
                            <Button>
                                <Plus className="h-4 w-4 mr-2" />
                                Start Session
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Start Multiplayer Session</DialogTitle>
                                <DialogDescription>
                                    Create a new multiplayer session for your PlayCanvas workspace
                                </DialogDescription>
                            </DialogHeader>
                            <form onSubmit={handleCreateSession} className="space-y-4">
                                <div>
                                    <Label htmlFor="workspace">Workspace</Label>
                                    <Select
                                        value={data.workspace_id}
                                        onValueChange={(value) => setData('workspace_id', value)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select a workspace" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {workspaces.map((workspace) => (
                                                <SelectItem key={workspace.id} value={workspace.id.toString()}>
                                                    {workspace.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.workspace_id && (
                                        <p className="text-sm text-red-600 mt-1">{errors.workspace_id}</p>
                                    )}
                                </div>
                                <div>
                                    <Label htmlFor="max_players">Max Players</Label>
                                    <Input
                                        id="max_players"
                                        type="number"
                                        min="2"
                                        max="16"
                                        value={data.max_players}
                                        onChange={(e) => setData('max_players', parseInt(e.target.value))}
                                    />
                                    {errors.max_players && (
                                        <p className="text-sm text-red-600 mt-1">{errors.max_players}</p>
                                    )}
                                </div>
                                <div>
                                    <Label htmlFor="ttl_minutes">Session Duration (minutes)</Label>
                                    <Input
                                        id="ttl_minutes"
                                        type="number"
                                        min="10"
                                        max="120"
                                        value={data.ttl_minutes}
                                        onChange={(e) => setData('ttl_minutes', parseInt(e.target.value))}
                                    />
                                    {errors.ttl_minutes && (
                                        <p className="text-sm text-red-600 mt-1">{errors.ttl_minutes}</p>
                                    )}
                                </div>
                                <div className="flex justify-end gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => setIsCreateDialogOpen(false)}
                                    >
                                        Cancel
                                    </Button>
                                    <Button type="submit" disabled={processing}>
                                        {processing ? 'Starting...' : 'Start Session'}
                                    </Button>
                                </div>
                            </form>
                        </DialogContent>
                    </Dialog>
                </div>

                {/* Stats */}
                <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center space-x-2">
                                <Server className="h-5 w-5 text-green-600" />
                                <div>
                                    <p className="text-2xl font-bold">{stats.active_sessions}</p>
                                    <p className="text-sm text-muted-foreground">Active Sessions</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center space-x-2">
                                <Activity className="h-5 w-5 text-blue-600" />
                                <div>
                                    <p className="text-2xl font-bold">{stats.total_sessions_this_month}</p>
                                    <p className="text-sm text-muted-foreground">Sessions This Month</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center space-x-2">
                                <UserCheck className="h-5 w-5 text-purple-600" />
                                <div>
                                    <p className="text-2xl font-bold">{stats.total_players_this_month}</p>
                                    <p className="text-sm text-muted-foreground">Total Players</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center space-x-2">
                                <Timer className="h-5 w-5 text-orange-600" />
                                <div>
                                    <p className="text-2xl font-bold">
                                        {formatDuration(stats.average_session_duration)}
                                    </p>
                                    <p className="text-sm text-muted-foreground">Avg Duration</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Active Sessions */}
                <div>
                    <h2 className="text-xl font-semibold mb-4">Active Sessions</h2>
                    {activeSessions.length === 0 ? (
                        <Card>
                            <CardContent className="p-12 text-center">
                                <Server className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
                                <h3 className="text-lg font-semibold mb-2">No active sessions</h3>
                                <p className="text-muted-foreground mb-4">
                                    Start a new multiplayer session to begin collaborating
                                </p>
                                <Button onClick={() => setIsCreateDialogOpen(true)}>
                                    <Plus className="h-4 w-4 mr-2" />
                                    Start Session
                                </Button>
                            </CardContent>
                        </Card>
                    ) : (
                        <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                            {activeSessions.map((session) => (
                                <Card key={session.id}>
                                    <CardHeader>
                                        <div className="flex items-start justify-between">
                                            <div>
                                                <CardTitle className="text-lg">
                                                    {session.workspace.name}
                                                </CardTitle>
                                                <CardDescription>
                                                    Session ID: {session.id}
                                                </CardDescription>
                                            </div>
                                            <Badge className={statusColors[session.status]}>
                                                {session.status}
                                            </Badge>
                                        </div>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="space-y-4">
                                            <div className="flex items-center justify-between text-sm">
                                                <span className="text-muted-foreground">Players:</span>
                                                <div className="flex items-center gap-1">
                                                    <Users className="h-4 w-4" />
                                                    <span>{session.current_players}/{session.max_players}</span>
                                                </div>
                                            </div>
                                            <div className="flex items-center justify-between text-sm">
                                                <span className="text-muted-foreground">Time remaining:</span>
                                                <div className="flex items-center gap-1">
                                                    <Clock className="h-4 w-4" />
                                                    <span>{formatTimeRemaining(session.expires_at)}</span>
                                                </div>
                                            </div>
                                            <div className="flex gap-2">
                                                {session.session_url && (
                                                    <Button variant="outline" size="sm" asChild className="flex-1">
                                                        <a
                                                            href={session.session_url}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                        >
                                                            <Play className="h-4 w-4 mr-2" />
                                                            Join Game
                                                        </a>
                                                    </Button>
                                                )}
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => handleStopSession(session.id)}
                                                    disabled={isLoading === session.id}
                                                >
                                                    <Square className="h-4 w-4 mr-2" />
                                                    {isLoading === session.id ? 'Stopping...' : 'Stop'}
                                                </Button>
                                                <Button variant="outline" size="sm" asChild>
                                                    <Link href={`/multiplayer/${session.id}`}>
                                                        <Users className="h-4 w-4 mr-1" />
                                                        Manage
                                                    </Link>
                                                </Button>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    )}
                </div>

                {/* Recent Sessions */}
                <div>
                    <h2 className="text-xl font-semibold mb-4">Recent Sessions</h2>
                    {recentSessions.length === 0 ? (
                        <Card>
                            <CardContent className="p-8 text-center">
                                <p className="text-muted-foreground">No recent sessions found</p>
                            </CardContent>
                        </Card>
                    ) : (
                        <div className="space-y-2">
                            {recentSessions.map((session) => (
                                <Card key={session.id}>
                                    <CardContent className="p-4">
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center space-x-4">
                                                <div>
                                                    <h4 className="font-medium">{session.workspace.name}</h4>
                                                    <p className="text-sm text-muted-foreground">
                                                        {new Date(session.created_at).toLocaleDateString()} at{' '}
                                                        {new Date(session.created_at).toLocaleTimeString()}
                                                    </p>
                                                </div>
                                            </div>
                                            <div className="flex items-center space-x-4">
                                                <div className="text-sm text-muted-foreground">
                                                    <Users className="h-4 w-4 inline mr-1" />
                                                    {session.current_players}/{session.max_players}
                                                </div>
                                                <Badge className={statusColors[session.status]}>
                                                    {session.status}
                                                </Badge>
                                                <Button variant="ghost" size="sm" asChild>
                                                    <Link href={`/multiplayer/${session.id}`}>
                                                        <ExternalLink className="h-4 w-4" />
                                                    </Link>
                                                </Button>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </MainLayout>
    );
}