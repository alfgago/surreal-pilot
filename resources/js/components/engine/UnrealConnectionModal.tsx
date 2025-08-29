import React, { useState, useEffect } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { 
    CheckCircle, 
    AlertCircle, 
    RefreshCw, 
    Download, 
    ExternalLink,
    Copy,
    Gamepad2
} from 'lucide-react';
import { useToast } from '@/components/ui/use-toast';

interface UnrealConnectionStatus {
    connected: boolean;
    version?: string;
    plugin_version?: string;
    project_name?: string;
    last_ping?: string;
    error?: string;
}

interface UnrealConnectionModalProps {
    isOpen: boolean;
    onClose: () => void;
    workspaceId: number;
    connectionStatus: UnrealConnectionStatus;
    onTestConnection: () => Promise<void>;
    onRefreshStatus: () => Promise<void>;
}

export default function UnrealConnectionModal({
    isOpen,
    onClose,
    workspaceId,
    connectionStatus,
    onTestConnection,
    onRefreshStatus
}: UnrealConnectionModalProps) {
    const [isLoading, setIsLoading] = useState(false);
    const [connectionSettings, setConnectionSettings] = useState({
        host: 'localhost',
        port: '8080',
        api_key: ''
    });
    const { toast } = useToast();

    const handleTestConnection = async () => {
        setIsLoading(true);
        try {
            await onTestConnection();
            toast({
                title: "Connection Test",
                description: "Testing connection to Unreal Engine...",
            });
        } catch (error) {
            toast({
                title: "Connection Failed",
                description: "Failed to connect to Unreal Engine. Please check your settings.",
                variant: "destructive",
            });
        } finally {
            setIsLoading(false);
        }
    };

    const handleRefreshStatus = async () => {
        setIsLoading(true);
        try {
            await onRefreshStatus();
        } catch (error) {
            toast({
                title: "Refresh Failed",
                description: "Failed to refresh connection status.",
                variant: "destructive",
            });
        } finally {
            setIsLoading(false);
        }
    };

    const copyToClipboard = (text: string) => {
        navigator.clipboard.writeText(text);
        toast({
            title: "Copied",
            description: "Copied to clipboard",
        });
    };

    const getStatusBadge = () => {
        if (connectionStatus.connected) {
            return (
                <Badge className="bg-green-100 text-green-800 border-green-200">
                    <CheckCircle className="w-3 h-3 mr-1" />
                    Connected
                </Badge>
            );
        } else if (connectionStatus.error) {
            return (
                <Badge className="bg-red-100 text-red-800 border-red-200">
                    <AlertCircle className="w-3 h-3 mr-1" />
                    Error
                </Badge>
            );
        } else {
            return (
                <Badge className="bg-gray-100 text-gray-800 border-gray-200">
                    <AlertCircle className="w-3 h-3 mr-1" />
                    Disconnected
                </Badge>
            );
        }
    };

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-w-2xl">
                <DialogHeader>
                    <DialogTitle className="flex items-center space-x-2">
                        <Gamepad2 className="w-5 h-5" />
                        <span>Unreal Engine Connection</span>
                        {getStatusBadge()}
                    </DialogTitle>
                    <DialogDescription>
                        Configure and manage your connection to Unreal Engine
                    </DialogDescription>
                </DialogHeader>

                <Tabs defaultValue="status" className="w-full">
                    <TabsList className="grid w-full grid-cols-3">
                        <TabsTrigger value="status">Status</TabsTrigger>
                        <TabsTrigger value="settings">Settings</TabsTrigger>
                        <TabsTrigger value="setup">Setup Guide</TabsTrigger>
                    </TabsList>

                    <TabsContent value="status" className="space-y-4">
                        <div className="space-y-4">
                            <div className="flex items-center justify-between">
                                <h3 className="text-lg font-medium">Connection Status</h3>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={handleRefreshStatus}
                                    disabled={isLoading}
                                >
                                    <RefreshCw className={`w-4 h-4 mr-2 ${isLoading ? 'animate-spin' : ''}`} />
                                    Refresh
                                </Button>
                            </div>

                            {connectionStatus.connected ? (
                                <div className="space-y-3 p-4 bg-green-50 border border-green-200 rounded-lg">
                                    <div className="flex items-center space-x-2">
                                        <CheckCircle className="w-5 h-5 text-green-600" />
                                        <span className="font-medium text-green-800">Connected to Unreal Engine</span>
                                    </div>
                                    
                                    {connectionStatus.project_name && (
                                        <div className="text-sm text-green-700">
                                            <strong>Project:</strong> {connectionStatus.project_name}
                                        </div>
                                    )}
                                    
                                    {connectionStatus.version && (
                                        <div className="text-sm text-green-700">
                                            <strong>Engine Version:</strong> {connectionStatus.version}
                                        </div>
                                    )}
                                    
                                    {connectionStatus.plugin_version && (
                                        <div className="text-sm text-green-700">
                                            <strong>Plugin Version:</strong> {connectionStatus.plugin_version}
                                        </div>
                                    )}
                                    
                                    {connectionStatus.last_ping && (
                                        <div className="text-sm text-green-700">
                                            <strong>Last Ping:</strong> {new Date(connectionStatus.last_ping).toLocaleString()}
                                        </div>
                                    )}
                                </div>
                            ) : (
                                <div className="space-y-3 p-4 bg-red-50 border border-red-200 rounded-lg">
                                    <div className="flex items-center space-x-2">
                                        <AlertCircle className="w-5 h-5 text-red-600" />
                                        <span className="font-medium text-red-800">Not Connected</span>
                                    </div>
                                    
                                    {connectionStatus.error && (
                                        <div className="text-sm text-red-700">
                                            <strong>Error:</strong> {connectionStatus.error}
                                        </div>
                                    )}
                                    
                                    <div className="text-sm text-red-700">
                                        Make sure Unreal Engine is running with the SurrealPilot plugin enabled.
                                    </div>
                                </div>
                            )}
                        </div>
                    </TabsContent>

                    <TabsContent value="settings" className="space-y-4">
                        <div className="space-y-4">
                            <h3 className="text-lg font-medium">Connection Settings</h3>
                            
                            <div className="grid grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label htmlFor="host">Host</Label>
                                    <Input
                                        id="host"
                                        value={connectionSettings.host}
                                        onChange={(e) => setConnectionSettings(prev => ({ ...prev, host: e.target.value }))}
                                        placeholder="localhost"
                                    />
                                </div>
                                
                                <div className="space-y-2">
                                    <Label htmlFor="port">Port</Label>
                                    <Input
                                        id="port"
                                        value={connectionSettings.port}
                                        onChange={(e) => setConnectionSettings(prev => ({ ...prev, port: e.target.value }))}
                                        placeholder="8080"
                                    />
                                </div>
                            </div>
                            
                            <div className="space-y-2">
                                <Label htmlFor="api_key">API Key (Optional)</Label>
                                <Input
                                    id="api_key"
                                    type="password"
                                    value={connectionSettings.api_key}
                                    onChange={(e) => setConnectionSettings(prev => ({ ...prev, api_key: e.target.value }))}
                                    placeholder="Enter API key if required"
                                />
                            </div>
                            
                            <div className="flex space-x-2">
                                <Button
                                    onClick={handleTestConnection}
                                    disabled={isLoading}
                                    className="flex-1"
                                >
                                    {isLoading ? (
                                        <RefreshCw className="w-4 h-4 mr-2 animate-spin" />
                                    ) : (
                                        <CheckCircle className="w-4 h-4 mr-2" />
                                    )}
                                    Test Connection
                                </Button>
                                
                                <Button variant="outline" onClick={handleRefreshStatus} disabled={isLoading}>
                                    <RefreshCw className={`w-4 h-4 ${isLoading ? 'animate-spin' : ''}`} />
                                </Button>
                            </div>
                        </div>
                    </TabsContent>

                    <TabsContent value="setup" className="space-y-4">
                        <div className="space-y-6">
                            <h3 className="text-lg font-medium">Setup Guide</h3>
                            
                            <div className="space-y-4">
                                <div className="p-4 border rounded-lg">
                                    <h4 className="font-medium mb-2">1. Download SurrealPilot Plugin</h4>
                                    <p className="text-sm text-muted-foreground mb-3">
                                        Download the latest SurrealPilot plugin for Unreal Engine 5.0+
                                    </p>
                                    <Button variant="outline" size="sm">
                                        <Download className="w-4 h-4 mr-2" />
                                        Download Plugin
                                    </Button>
                                </div>
                                
                                <div className="p-4 border rounded-lg">
                                    <h4 className="font-medium mb-2">2. Install Plugin</h4>
                                    <p className="text-sm text-muted-foreground mb-3">
                                        Copy the plugin to your project's Plugins folder:
                                    </p>
                                    <div className="bg-muted p-2 rounded font-mono text-sm flex items-center justify-between">
                                        <span>YourProject/Plugins/SurrealPilot/</span>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => copyToClipboard('YourProject/Plugins/SurrealPilot/')}
                                        >
                                            <Copy className="w-3 h-3" />
                                        </Button>
                                    </div>
                                </div>
                                
                                <div className="p-4 border rounded-lg">
                                    <h4 className="font-medium mb-2">3. Enable Plugin</h4>
                                    <p className="text-sm text-muted-foreground">
                                        In Unreal Engine, go to Edit → Plugins → Search for "SurrealPilot" → Enable
                                    </p>
                                </div>
                                
                                <div className="p-4 border rounded-lg">
                                    <h4 className="font-medium mb-2">4. Configure Connection</h4>
                                    <p className="text-sm text-muted-foreground">
                                        The plugin will automatically start the HTTP server on port 8080. 
                                        Use the Settings tab to configure custom connection parameters.
                                    </p>
                                </div>
                            </div>
                            
                            <div className="flex space-x-2">
                                <Button variant="outline" size="sm">
                                    <ExternalLink className="w-4 h-4 mr-2" />
                                    Documentation
                                </Button>
                                <Button variant="outline" size="sm">
                                    <ExternalLink className="w-4 h-4 mr-2" />
                                    Video Tutorial
                                </Button>
                            </div>
                        </div>
                    </TabsContent>
                </Tabs>

                <div className="flex justify-end space-x-2 pt-4 border-t">
                    <Button variant="outline" onClick={onClose}>
                        Close
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}