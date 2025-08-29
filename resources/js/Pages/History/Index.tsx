import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import MainLayout from '@/Layouts/MainLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { 
    Search, 
    MessageSquare, 
    Gamepad2, 
    CreditCard, 
    FolderOpen,
    Clock,
    ExternalLink,
    Activity
} from 'lucide-react';

interface Workspace {
    id: number;
    name: string;
}

interface ActivityItem {
    id: string;
    type: 'chat' | 'game' | 'credit' | 'workspace';
    title: string;
    description: string;
    workspace?: Workspace;
    metadata: Record<string, any>;
    created_at: string;
    updated_at: string;
}

interface Props {
    activities: ActivityItem[];
    workspaces: Workspace[];
    filters: {
        type: string;
        search: string;
        workspace?: string;
    };
    stats: {
        total_conversations: number;
        total_games: number;
        total_workspaces: number;
        credits_used_this_month: number;
    };
}

const activityIcons = {
    chat: MessageSquare,
    game: Gamepad2,
    credit: CreditCard,
    workspace: FolderOpen,
};

const activityColors = {
    chat: 'bg-blue-100 text-blue-800',
    game: 'bg-green-100 text-green-800',
    credit: 'bg-yellow-100 text-yellow-800',
    workspace: 'bg-purple-100 text-purple-800',
};

export default function HistoryIndex({ activities, workspaces, filters, stats }: Props) {
    const [searchTerm, setSearchTerm] = useState(filters.search);

    const handleSearch = (value: string) => {
        setSearchTerm(value);
        router.get('/history', {
            ...filters,
            search: value,
        }, {
            preserveState: true,
            replace: true,
        });
    };

    const handleFilterChange = (key: string, value: string) => {
        router.get('/history', {
            ...filters,
            [key]: value === 'all' ? undefined : value,
        }, {
            preserveState: true,
            replace: true,
        });
    };

    const formatDate = (dateString: string) => {
        const date = new Date(dateString);
        const now = new Date();
        const diffInHours = (now.getTime() - date.getTime()) / (1000 * 60 * 60);

        if (diffInHours < 1) {
            const diffInMinutes = Math.floor(diffInHours * 60);
            return `${diffInMinutes}m ago`;
        } else if (diffInHours < 24) {
            return `${Math.floor(diffInHours)}h ago`;
        } else if (diffInHours < 168) { // 7 days
            const diffInDays = Math.floor(diffInHours / 24);
            return `${diffInDays}d ago`;
        } else {
            return date.toLocaleDateString();
        }
    };

    const getActivityLink = (activity: ActivityItem) => {
        switch (activity.type) {
            case 'chat':
                return `/chat?conversation=${activity.metadata.conversation_id}`;
            case 'game':
                return `/games/${activity.metadata.game_id}`;
            case 'workspace':
                return `/workspaces/${activity.metadata.workspace_id}`;
            default:
                return null;
        }
    };

    return (
        <MainLayout>
            <Head title="Activity History" />

            <div className="space-y-6">
                {/* Header */}
                <div>
                    <h1 className="text-3xl font-bold">Activity History</h1>
                    <p className="text-muted-foreground mt-2">
                        Track your conversations, games, and workspace activity
                    </p>
                </div>

                {/* Stats */}
                <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center space-x-2">
                                <MessageSquare className="h-5 w-5 text-blue-600" />
                                <div>
                                    <p className="text-2xl font-bold">{stats.total_conversations}</p>
                                    <p className="text-sm text-muted-foreground">Conversations</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center space-x-2">
                                <Gamepad2 className="h-5 w-5 text-green-600" />
                                <div>
                                    <p className="text-2xl font-bold">{stats.total_games}</p>
                                    <p className="text-sm text-muted-foreground">Games</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center space-x-2">
                                <FolderOpen className="h-5 w-5 text-purple-600" />
                                <div>
                                    <p className="text-2xl font-bold">{stats.total_workspaces}</p>
                                    <p className="text-sm text-muted-foreground">Workspaces</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center space-x-2">
                                <CreditCard className="h-5 w-5 text-yellow-600" />
                                <div>
                                    <p className="text-2xl font-bold">{Math.round(stats.credits_used_this_month)}</p>
                                    <p className="text-sm text-muted-foreground">Credits This Month</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters */}
                <div className="flex flex-col sm:flex-row gap-4">
                    <div className="relative flex-1">
                        <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                        <Input
                            placeholder="Search activity, descriptions, or workspace names..."
                            value={searchTerm}
                            onChange={(e) => handleSearch(e.target.value)}
                            className="pl-10"
                        />
                    </div>
                    <Select
                        value={filters.type}
                        onValueChange={(value) => handleFilterChange('type', value)}
                    >
                        <SelectTrigger className="w-full sm:w-48">
                            <SelectValue placeholder="Activity Type" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Activity</SelectItem>
                            <SelectItem value="chat">Conversations</SelectItem>
                            <SelectItem value="game">Games</SelectItem>
                            <SelectItem value="workspace">Workspaces</SelectItem>
                            <SelectItem value="credit">Credits</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select
                        value={filters.workspace || 'all'}
                        onValueChange={(value) => handleFilterChange('workspace', value)}
                    >
                        <SelectTrigger className="w-full sm:w-48">
                            <SelectValue placeholder="Workspace" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Workspaces</SelectItem>
                            {workspaces.map((workspace) => (
                                <SelectItem key={workspace.id} value={workspace.id.toString()}>
                                    {workspace.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Button 
                        variant="outline" 
                        onClick={() => router.reload({ only: ['activities', 'stats'] })}
                        className="whitespace-nowrap"
                    >
                        <Activity className="h-4 w-4 mr-2" />
                        Refresh
                    </Button>
                </div>

                {/* Activity Timeline */}
                {activities.length === 0 ? (
                    <Card>
                        <CardContent className="p-12 text-center">
                            <Activity className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
                            <h3 className="text-lg font-semibold mb-2">No activity found</h3>
                            <p className="text-muted-foreground">
                                Try adjusting your search criteria or filters
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-4">
                        {activities.map((activity) => {
                            const Icon = activityIcons[activity.type];
                            const link = getActivityLink(activity);
                            
                            return (
                                <Card key={activity.id} className="hover:shadow-md transition-shadow">
                                    <CardContent className="p-6">
                                        <div className="flex items-start space-x-4">
                                            <div className={`p-2 rounded-full ${activityColors[activity.type]}`}>
                                                <Icon className="h-4 w-4" />
                                            </div>
                                            <div className="flex-1 min-w-0">
                                                <div className="flex items-start justify-between">
                                                    <div className="flex-1">
                                                        <h3 className="font-semibold text-lg">
                                                            {link ? (
                                                                <Link 
                                                                    href={link}
                                                                    className="hover:text-blue-600 transition-colors"
                                                                >
                                                                    {activity.title}
                                                                </Link>
                                                            ) : (
                                                                activity.title
                                                            )}
                                                        </h3>
                                                        {activity.description && (
                                                            <p className="text-muted-foreground mt-1">
                                                                {activity.description}
                                                            </p>
                                                        )}
                                                        <div className="flex items-center gap-4 mt-2">
                                                            <Badge variant="secondary" className="capitalize">
                                                                {activity.type}
                                                            </Badge>
                                                            {activity.workspace && (
                                                                <span className="text-sm text-muted-foreground">
                                                                    in {activity.workspace.name}
                                                                </span>
                                                            )}
                                                            <div className="flex items-center text-sm text-muted-foreground">
                                                                <Clock className="h-3 w-3 mr-1" />
                                                                {formatDate(activity.updated_at)}
                                                            </div>
                                                        </div>
                                                    </div>
                                                    {link && (
                                                        <Button variant="ghost" size="sm" asChild>
                                                            <Link href={link}>
                                                                <ExternalLink className="h-4 w-4" />
                                                            </Link>
                                                        </Button>
                                                    )}
                                                </div>

                                                {/* Activity-specific metadata */}
                                                {activity.type === 'chat' && activity.metadata.message_count && (
                                                    <div className="mt-3 text-sm text-muted-foreground">
                                                        {activity.metadata.message_count} messages
                                                    </div>
                                                )}
                                                {activity.type === 'game' && (
                                                    <div className="mt-3 flex gap-4 text-sm text-muted-foreground">
                                                        {activity.metadata.status && (
                                                            <span>Status: {activity.metadata.status}</span>
                                                        )}
                                                        {activity.metadata.play_count > 0 && (
                                                            <span>Played {activity.metadata.play_count} times</span>
                                                        )}
                                                        {activity.metadata.is_published && (
                                                            <Badge variant="outline" className="text-xs">
                                                                Published
                                                            </Badge>
                                                        )}
                                                    </div>
                                                )}
                                                {activity.type === 'credit' && (
                                                    <div className="mt-3 text-sm">
                                                        <span className={activity.metadata.amount > 0 ? 'text-green-600' : 'text-red-600'}>
                                                            {activity.metadata.amount > 0 ? '+' : ''}{activity.metadata.amount} credits
                                                        </span>
                                                        <span className="text-muted-foreground ml-2">
                                                            ({activity.metadata.transaction_type})
                                                        </span>
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>
                )}
            </div>
        </MainLayout>
    );
}