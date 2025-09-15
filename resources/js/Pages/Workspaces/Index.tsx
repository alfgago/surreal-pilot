import { FormEventHandler, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Plus, Folder, Calendar, ArrowRight, Gamepad2, Monitor } from 'lucide-react';
import MainLayout from '@/Layouts/MainLayout';

interface Workspace {
    id: number;
    name: string;
    engine_type: string;
    created_at: string;
    updated_at: string;
}

interface Engine {
    type: string;
    name: string;
    description: string;
    available: boolean;
}

interface WorkspacesIndexProps {
    workspaces: Record<string, Workspace[]>;
    engines: Record<string, Engine>;
}

export default function WorkspacesIndex({ workspaces, engines }: WorkspacesIndexProps) {
    const selectWorkspace = (workspaceId: number) => {
        router.post('/workspaces/select', { workspace_id: workspaceId });
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    };

    const getEngineIcon = (engineType: string) => {
        switch (engineType) {
            case 'playcanvas':
                return <Monitor className="w-5 h-5 text-primary" />;
            case 'gdevelop':
                return <Gamepad2 className="w-5 h-5 text-primary" />;
            case 'unreal':
                return <Gamepad2 className="w-5 h-5 text-primary" />;
            default:
                return <Gamepad2 className="w-5 h-5 text-primary" />;
        }
    };

    const getEngineColor = (engineType: string) => {
        switch (engineType) {
            case 'playcanvas':
                return 'bg-blue-100 text-blue-800 border-blue-200';
            case 'gdevelop':
                return 'bg-green-100 text-green-800 border-green-200';
            case 'unreal':
                return 'bg-purple-100 text-purple-800 border-purple-200';
            default:
                return 'bg-gray-100 text-gray-800 border-gray-200';
        }
    };

    const totalWorkspaces = Object.values(workspaces).flat().length;

    return (
        <MainLayout>
            <Head title="Workspaces" />

            <div className="max-w-7xl mx-auto px-4 py-8">
                {/* Header */}
                <div className="flex items-center justify-between mb-8">
                    <div>
                        <h1 className="text-3xl font-serif font-black text-foreground mb-2">
                            Your Workspaces
                        </h1>
                        <p className="text-muted-foreground">
                            Manage your game development projects across different engines
                        </p>
                    </div>
                    <Link href="/workspaces/create">
                        <Button size="lg">
                            <Plus className="w-5 h-5 mr-2" />
                            New Workspace
                        </Button>
                    </Link>
                </div>

                {totalWorkspaces > 0 ? (
                    <div className="space-y-8">
                        {Object.entries(workspaces).map(([engineType, engineWorkspaces]) => {
                            const engine = engines[engineType];
                            if (!engine || engineWorkspaces.length === 0) return null;

                            return (
                                <div key={engineType}>
                                    <div className="flex items-center space-x-3 mb-4">
                                        <div className="w-8 h-8 bg-primary/10 rounded-lg flex items-center justify-center">
                                            {getEngineIcon(engineType)}
                                        </div>
                                        <h2 className="text-xl font-semibold text-foreground">
                                            {engine.name}
                                        </h2>
                                        <Badge variant="secondary">
                                            {engineWorkspaces.length} workspace{engineWorkspaces.length !== 1 ? 's' : ''}
                                        </Badge>
                                    </div>

                                    <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
                                        {engineWorkspaces.map((workspace) => (
                                            <Card 
                                                key={workspace.id} 
                                                className="border-border bg-card hover:bg-card/80 transition-colors cursor-pointer group"
                                                onClick={() => selectWorkspace(workspace.id)}
                                            >
                                                <CardHeader className="pb-3">
                                                    <div className="flex items-start justify-between">
                                                        <div className="flex items-center space-x-3 flex-1">
                                                            <div className="w-10 h-10 bg-primary/10 rounded-lg flex items-center justify-center">
                                                                <Folder className="w-5 h-5 text-primary" />
                                                            </div>
                                                            <div className="flex-1 min-w-0">
                                                                <CardTitle className="font-serif font-bold text-lg truncate">
                                                                    {workspace.name}
                                                                </CardTitle>
                                                                <div className="flex items-center text-sm text-muted-foreground mt-1">
                                                                    <Calendar className="w-3 h-3 mr-1 flex-shrink-0" />
                                                                    <span className="truncate">
                                                                        {formatDate(workspace.updated_at)}
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <ArrowRight className="w-5 h-5 text-muted-foreground group-hover:text-primary transition-colors" />
                                                    </div>
                                                </CardHeader>
                                                <CardContent className="pt-0">
                                                    <Badge 
                                                        variant="outline" 
                                                        className={`text-xs ${getEngineColor(workspace.engine_type)}`}
                                                    >
                                                        {engine.name}
                                                    </Badge>
                                                </CardContent>
                                            </Card>
                                        ))}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                ) : (
                    <Card className="border-dashed border-2 border-border bg-card/50">
                        <CardContent className="flex flex-col items-center justify-center py-16">
                            <div className="w-20 h-20 bg-muted rounded-full flex items-center justify-center mb-6">
                                <Folder className="w-10 h-10 text-muted-foreground" />
                            </div>
                            <h3 className="text-xl font-semibold text-foreground mb-2">
                                No workspaces yet
                            </h3>
                            <p className="text-muted-foreground text-center mb-8 max-w-md">
                                Create your first workspace to start building games with AI assistance. 
                                Choose from GDevelop for no-code games, PlayCanvas for web/mobile games, or Unreal Engine for advanced 3D projects.
                            </p>
                            <Link href="/workspaces/create">
                                <Button size="lg">
                                    <Plus className="w-5 h-5 mr-2" />
                                    Create Your First Workspace
                                </Button>
                            </Link>
                        </CardContent>
                    </Card>
                )}
            </div>
        </MainLayout>
    );
}