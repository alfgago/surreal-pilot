import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { ChevronDown, Gamepad2, Globe, Code, Plus } from 'lucide-react';
import { Link } from '@inertiajs/react';
import { Workspace } from '@/types';

interface WorkspaceSwitcherProps {
    currentWorkspace?: Workspace;
    workspaces?: Workspace[];
}

export function WorkspaceSwitcher({
    currentWorkspace,
    workspaces = []
}: WorkspaceSwitcherProps) {
    const getEngineIcon = (engine: string) => {
        switch (engine) {
            case 'playcanvas': return Globe;
            case 'gdevelop': return Gamepad2;
            case 'godot': return Code;
            default: return Code;
        }
    };

    const getEngineColor = (engine: string) => {
        switch (engine) {
            case 'playcanvas': return 'text-blue-500';
            case 'gdevelop': return 'text-green-500';
            case 'godot': return 'text-orange-500';
            default: return 'text-purple-500';
        }
    };

    const getEngineDisplayName = (engine: string) => {
        switch (engine) {
            case 'playcanvas': return 'PlayCanvas';
            case 'gdevelop': return 'No-Code Games';
            case 'godot': return 'Godot';
            case 'unreal': return 'Unreal Engine';
            default: return engine;
        }
    };

    if (!currentWorkspace) {
        return (
            <Link href="/workspaces">
                <Button variant="outline" size="sm">
                    <Plus className="w-4 h-4 mr-2" />
                    Select Workspace
                </Button>
            </Link>
        );
    }

    const CurrentEngineIcon = getEngineIcon(currentWorkspace.engine_type);

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" className="h-auto p-2 justify-start space-x-2 max-w-64">
                    <div className="flex items-center space-x-2 min-w-0">
                        <CurrentEngineIcon className={`w-4 h-4 ${getEngineColor(currentWorkspace.engine_type)} flex-shrink-0`} />
                        <div className="min-w-0 text-left">
                            <div className="font-medium text-sm truncate">{currentWorkspace.name}</div>
                            <div className="text-xs text-muted-foreground">
                                {getEngineDisplayName(currentWorkspace.engine_type)}
                            </div>
                        </div>
                    </div>
                    <ChevronDown className="w-4 h-4 text-muted-foreground flex-shrink-0" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" className="w-64">
                <DropdownMenuLabel>Switch Workspace</DropdownMenuLabel>
                <DropdownMenuSeparator />
                {workspaces.map((workspace) => {
                    const EngineIcon = getEngineIcon(workspace.engine_type);
                    const isActive = workspace.id === currentWorkspace.id;
                    return (
                        <DropdownMenuItem 
                            key={workspace.id} 
                            className={`cursor-pointer ${isActive ? 'bg-accent' : ''}`} 
                            asChild
                        >
                            <Link href={`/workspaces/${workspace.id}`}>
                                <div className="flex items-center space-x-2 w-full">
                                    <EngineIcon className={`w-4 h-4 ${getEngineColor(workspace.engine_type)}`} />
                                    <div className="flex-1 min-w-0">
                                        <div className="font-medium text-sm truncate">{workspace.name}</div>
                                        <Badge variant="secondary" className="text-xs">
                                            {getEngineDisplayName(workspace.engine_type)}
                                        </Badge>
                                    </div>
                                </div>
                            </Link>
                        </DropdownMenuItem>
                    );
                })}
                <DropdownMenuSeparator />
                <DropdownMenuItem asChild>
                    <Link href="/workspaces/create" className="cursor-pointer">
                        <Plus className="w-4 h-4 mr-2" />
                        Create New Workspace
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <Link href="/workspaces" className="cursor-pointer">
                        <Gamepad2 className="w-4 h-4 mr-2" />
                        Manage Workspaces
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}