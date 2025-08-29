import React from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { 
    Monitor, 
    Gamepad2, 
    Wifi, 
    WifiOff, 
    Settings, 
    ExternalLink,
    RefreshCw,
    AlertCircle,
    CheckCircle
} from 'lucide-react';

interface Workspace {
    id: number;
    name: string;
    engine_type: 'playcanvas' | 'unreal';
    status: string;
    preview_url?: string;
    published_url?: string;
    mcp_port?: number;
    mcp_pid?: number;
}

interface EngineStatus {
    status: 'connected' | 'disconnected' | 'error' | 'connecting';
    message?: string;
    details?: any;
}

interface EngineContextProps {
    workspace: Workspace;
    engineStatus: EngineStatus;
    onRefreshStatus: () => void;
    onOpenConnection: () => void;
    onOpenPreview: () => void;
}

export default function EngineContext({ 
    workspace, 
    engineStatus, 
    onRefreshStatus, 
    onOpenConnection, 
    onOpenPreview 
}: EngineContextProps) {
    const getEngineIcon = () => {
        return workspace.engine_type === 'playcanvas' ? <Monitor className="w-5 h-5" /> : <Gamepad2 className="w-5 h-5" />;
    };

    const getEngineDisplayName = () => {
        return workspace.engine_type === 'playcanvas' ? 'PlayCanvas' : 'Unreal Engine';
    };

    const getStatusIcon = () => {
        switch (engineStatus.status) {
            case 'connected':
                return <CheckCircle className="w-4 h-4 text-green-500" />;
            case 'connecting':
                return <RefreshCw className="w-4 h-4 text-blue-500 animate-spin" />;
            case 'error':
                return <AlertCircle className="w-4 h-4 text-red-500" />;
            default:
                return <WifiOff className="w-4 h-4 text-gray-500" />;
        }
    };

    const getStatusColor = () => {
        switch (engineStatus.status) {
            case 'connected':
                return 'bg-green-100 text-green-800 border-green-200';
            case 'connecting':
                return 'bg-blue-100 text-blue-800 border-blue-200';
            case 'error':
                return 'bg-red-100 text-red-800 border-red-200';
            default:
                return 'bg-gray-100 text-gray-800 border-gray-200';
        }
    };

    return (
        <Card className="w-full">
            <CardHeader className="pb-3">
                <CardTitle className="flex items-center justify-between">
                    <div className="flex items-center space-x-2">
                        {getEngineIcon()}
                        <span>Engine Context</span>
                    </div>
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={onRefreshStatus}
                        disabled={engineStatus.status === 'connecting'}
                    >
                        <RefreshCw className={`w-4 h-4 ${engineStatus.status === 'connecting' ? 'animate-spin' : ''}`} />
                    </Button>
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                {/* Workspace Info */}
                <div className="flex items-center justify-between">
                    <div>
                        <h3 className="font-medium text-sm">{workspace.name}</h3>
                        <p className="text-xs text-muted-foreground">{getEngineDisplayName()}</p>
                    </div>
                    <Badge variant="outline" className={getStatusColor()}>
                        <div className="flex items-center space-x-1">
                            {getStatusIcon()}
                            <span className="capitalize">{engineStatus.status}</span>
                        </div>
                    </Badge>
                </div>

                {/* Status Message */}
                {engineStatus.message && (
                    <div className="text-xs text-muted-foreground bg-muted p-2 rounded">
                        {engineStatus.message}
                    </div>
                )}

                {/* Engine-specific Details */}
                {workspace.engine_type === 'playcanvas' && (
                    <div className="space-y-2">
                        {workspace.mcp_port && (
                            <div className="flex justify-between text-xs">
                                <span className="text-muted-foreground">MCP Port:</span>
                                <span className="font-mono">{workspace.mcp_port}</span>
                            </div>
                        )}
                        {workspace.preview_url && (
                            <Button
                                variant="outline"
                                size="sm"
                                className="w-full"
                                onClick={onOpenPreview}
                            >
                                <ExternalLink className="w-3 h-3 mr-2" />
                                Open Preview
                            </Button>
                        )}
                    </div>
                )}

                {workspace.engine_type === 'unreal' && (
                    <div className="space-y-2">
                        <Button
                            variant="outline"
                            size="sm"
                            className="w-full"
                            onClick={onOpenConnection}
                        >
                            <Settings className="w-3 h-3 mr-2" />
                            Connection Settings
                        </Button>
                    </div>
                )}

                {/* Connection Actions */}
                <div className="flex space-x-2">
                    {engineStatus.status === 'disconnected' && (
                        <Button
                            variant="default"
                            size="sm"
                            className="flex-1"
                            onClick={onOpenConnection}
                        >
                            <Wifi className="w-3 h-3 mr-2" />
                            Connect
                        </Button>
                    )}
                    
                    {engineStatus.status === 'error' && (
                        <Button
                            variant="destructive"
                            size="sm"
                            className="flex-1"
                            onClick={onRefreshStatus}
                        >
                            <RefreshCw className="w-3 h-3 mr-2" />
                            Retry
                        </Button>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}