import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import MainLayout from '@/Layouts/MainLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { 
    ArrowLeft, 
    Users, 
    Play, 
    Square, 
    ExternalLink, 
    Clock, 
    Server,
    Copy,
    CheckCircle
} from 'lucide-react';
import CollaborationStatus from '@/components/multiplayer/CollaborationStatus';

interface MultiplayerSession {
    id: string;
    workspace: {
        id: number;
        name: string;
        engine_type: string;
    };
    fargate_task_arn?: string;
    ngrok_url?: string;
    session_url?: string;
    status: 'starting' | 'active' | 'stopping' | 'stopped' | 'failed';
    max_players: number;
    current_players: number;
    expires_at: string;
    created_at: string;
    updated_at: string;
}

interface Props {
    session: MultiplayerSession;
}

const statusColors = {
    starting: 'bg-yellow-100 text-yellow-800',
    active: 'bg-green-100 text-green-800',
    stopping: 'bg-orange-100 text-orange-800',
    stopped: 'bg-gray-100 text-gray-800',
    failed: 'bg-red-100 text-red-800',
};

export default function MultiplayerShow({ session }: Props) {
    const [isLoading, setIsLoading] = useState(false);
    const [copiedUrl, setCopiedUrl] = useState(false);

    const handleStopSession = async () => {
        setIsLoading(true);
        try {
            await router.post(`/api/multiplayer/${session.id}/stop`, {}, {
                onSuccess: () => {
                    router.reload({ only: ['session'] });
                },
                onFinish: () => setIsLoading(false),
            });
        } catch (error) {
            setIsLoading(false);
        }
    };

    const handleCopyUrl = async () => {
        if (session.session_url) {
            await navigator.clipboard.writeText(session.session_url);
            setCopiedUrl(true);
            setTimeout(() => setCopiedUrl(false), 2000);
        }
    };

    const formatTimeRemaining = (expiresAt: string) => {
        const now = new Date();
        const expires = new Date(expiresAt);
        const diffInMinutes = Math.max(0, (expires.getTime() - now.getTime()) / (1000 * 60));
        
        if (diffInMinutes < 60) {
            return `${Math.round(diffInMinutes)} minutes`;
        }
        const hours = Math.floor(diffInMinutes / 60);
        const minutes = Math.round(diffInMinutes % 60);
        return `${hours} hours ${minutes} minutes`;
    };

    const formatDuration = (createdAt: string) => {
        const now = new Date();
        const created = new Date(createdAt);
        const diffInMinutes = (now.getTime() - created.getTime()) / (1000 * 60);
        
        if (diffInMinutes < 60) {
            return `${Math.round(diffInMinutes)} minutes`;
        }
        const hours = Math.floor(diffInMinutes / 60);
        const minutes = Math.round(diffInMinutes % 60);
        return `${hours} hours ${minutes} minutes`;
    };

    return (
        <MainLayout>
            <Head title={`Multiplayer Session - ${session.workspace.name}`} />

            <div className="space-y-6">
                {/* Back Button */}
                <Button variant="ghost" asChild>
                    <Link href="/multiplayer">
                        <ArrowLeft className="h-4 w-4 mr-2" />
                        Back to Sessions
                    </Link>
                </Button>

                {/* Header */}
                <div className="flex justify-between items-start">
                    <div>
                        <h1 className="text-3xl font-bold">{session.workspace.name}</h1>
                        <p className="text-muted-foreground mt-2">
                            Multiplayer Session: {session.id}
                        </p>
                    </div>
                    <Badge className={statusColors[session.status]} size="lg">
                        {session.status}
                    </Badge>
                </div>

                {/* Session Overview */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Users className="h-5 w-5" />
                                Players
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-3xl font-bold">
                                {session.current_players}/{session.max_players}
                            </div>
                            <p className="text-sm text-muted-foreground mt-1">
                                {session.max_players - session.current_players} slots available
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Clock className="h-5 w-5" />
                                Time Remaining
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-3xl font-bold">
                                {session.status === 'active' ? formatTimeRemaining(session.expires_at) : 'N/A'}
                            </div>
                            <p className="text-sm text-muted-foreground mt-1">
                                Expires at {new Date(session.expires_at).toLocaleString()}
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Server className="h-5 w-5" />
                                Duration
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-3xl font-bold">
                                {formatDuration(session.created_at)}
                            </div>
                            <p className="text-sm text-muted-foreground mt-1">
                                Started {new Date(session.created_at).toLocaleString()}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Session Actions */}
                {session.status === 'active' && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Session Actions</CardTitle>
                            <CardDescription>
                                Manage your multiplayer session
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="flex flex-col sm:flex-row gap-4">
                                {session.session_url && (
                                    <div className="flex-1">
                                        <div className="flex gap-2">
                                            <Button asChild className="flex-1">
                                                <a
                                                    href={session.session_url}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                >
                                                    <Play className="h-4 w-4 mr-2" />
                                                    Join Game
                                                </a>
                                            </Button>
                                            <Button
                                                variant="outline"
                                                onClick={handleCopyUrl}
                                                className="px-3"
                                            >
                                                {copiedUrl ? (
                                                    <CheckCircle className="h-4 w-4" />
                                                ) : (
                                                    <Copy className="h-4 w-4" />
                                                )}
                                            </Button>
                                        </div>
                                        {copiedUrl && (
                                            <p className="text-sm text-green-600 mt-1">
                                                URL copied to clipboard!
                                            </p>
                                        )}
                                    </div>
                                )}
                                <Button
                                    variant="destructive"
                                    onClick={handleStopSession}
                                    disabled={isLoading}
                                >
                                    <Square className="h-4 w-4 mr-2" />
                                    {isLoading ? 'Stopping...' : 'Stop Session'}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Real-time Collaboration */}
                {session.status === 'active' && (
                    <CollaborationStatus 
                        workspaceId={session.workspace.id}
                        sessionId={session.id}
                        onUserClick={(user) => {
                            // Could open a direct message or focus on user's activity
                            console.log('User clicked:', user);
                        }}
                    />
                )}

                {/* Session Details */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Session Information</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Session ID:</span>
                                <code className="text-sm bg-muted px-2 py-1 rounded">
                                    {session.id}
                                </code>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Workspace:</span>
                                <Link 
                                    href={`/workspaces/${session.workspace.id}`}
                                    className="text-blue-600 hover:underline"
                                >
                                    {session.workspace.name}
                                </Link>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Engine:</span>
                                <span className="capitalize">{session.workspace.engine_type}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Status:</span>
                                <Badge className={statusColors[session.status]}>
                                    {session.status}
                                </Badge>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Created:</span>
                                <span>{new Date(session.created_at).toLocaleString()}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Last Updated:</span>
                                <span>{new Date(session.updated_at).toLocaleString()}</span>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Technical Details</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {session.fargate_task_arn && (
                                <div>
                                    <span className="text-muted-foreground block mb-1">Fargate Task ARN:</span>
                                    <code className="text-xs bg-muted px-2 py-1 rounded block break-all">
                                        {session.fargate_task_arn}
                                    </code>
                                </div>
                            )}
                            {session.ngrok_url && (
                                <div>
                                    <span className="text-muted-foreground block mb-1">Ngrok URL:</span>
                                    <code className="text-sm bg-muted px-2 py-1 rounded block break-all">
                                        {session.ngrok_url}
                                    </code>
                                </div>
                            )}
                            {session.session_url && (
                                <div>
                                    <span className="text-muted-foreground block mb-1">Session URL:</span>
                                    <div className="flex gap-2">
                                        <code className="text-sm bg-muted px-2 py-1 rounded flex-1 break-all">
                                            {session.session_url}
                                        </code>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
                                            <a
                                                href={session.session_url}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                            >
                                                <ExternalLink className="h-4 w-4" />
                                            </a>
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Instructions */}
                <Card>
                    <CardHeader>
                        <CardTitle>How to Use This Session</CardTitle>
                        <CardDescription>
                            Share the session URL with other players to collaborate
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="space-y-2">
                            <h4 className="font-medium">1. Share the Session URL</h4>
                            <p className="text-sm text-muted-foreground">
                                Copy the session URL and share it with other players who want to join your game
                            </p>
                        </div>
                        <div className="space-y-2">
                            <h4 className="font-medium">2. Collaborate in Real-time</h4>
                            <p className="text-sm text-muted-foreground">
                                Multiple players can join the same game session and play together
                            </p>
                        </div>
                        <div className="space-y-2">
                            <h4 className="font-medium">3. Monitor Session Status</h4>
                            <p className="text-sm text-muted-foreground">
                                Keep track of active players and session duration from this dashboard
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </MainLayout>
    );
}