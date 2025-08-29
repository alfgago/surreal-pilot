import React, { useState, useEffect, useRef } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { 
    Play, 
    Pause, 
    RefreshCw, 
    Maximize, 
    ExternalLink, 
    Monitor,
    AlertCircle,
    CheckCircle,
    Loader2
} from 'lucide-react';
import { useToast } from '@/components/ui/use-toast';

interface PlayCanvasPreviewProps {
    workspaceId?: number;
    gameId?: string;
    previewUrl?: string;
    isLiveUpdatesEnabled?: boolean;
    onToggleLiveUpdates?: (enabled: boolean) => void;
    onRefreshPreview?: () => void;
    onOpenFullscreen?: () => void;
}

interface PreviewStatus {
    status: 'loading' | 'ready' | 'error' | 'disconnected';
    message?: string;
    lastUpdate?: string;
}

export default function PlayCanvasPreview({
    workspaceId,
    gameId,
    previewUrl,
    isLiveUpdatesEnabled = true,
    onToggleLiveUpdates,
    onRefreshPreview,
    onOpenFullscreen
}: PlayCanvasPreviewProps) {
    const [previewStatus, setPreviewStatus] = useState<PreviewStatus>({ status: 'loading' });
    const [isRefreshing, setIsRefreshing] = useState(false);
    const iframeRef = useRef<HTMLIFrameElement>(null);
    const { toast } = useToast();

    // Auto-refresh when live updates are enabled
    useEffect(() => {
        if (!isLiveUpdatesEnabled || !previewUrl) return;

        const interval = setInterval(() => {
            handleRefreshPreview();
        }, 5000); // Refresh every 5 seconds

        return () => clearInterval(interval);
    }, [isLiveUpdatesEnabled, previewUrl]);

    // Handle iframe load events
    useEffect(() => {
        const iframe = iframeRef.current;
        if (!iframe) return;

        const handleLoad = () => {
            setPreviewStatus({
                status: 'ready',
                message: 'Preview loaded successfully',
                lastUpdate: new Date().toLocaleTimeString()
            });
        };

        const handleError = () => {
            setPreviewStatus({
                status: 'error',
                message: 'Failed to load preview',
                lastUpdate: new Date().toLocaleTimeString()
            });
        };

        iframe.addEventListener('load', handleLoad);
        iframe.addEventListener('error', handleError);

        return () => {
            iframe.removeEventListener('load', handleLoad);
            iframe.removeEventListener('error', handleError);
        };
    }, [previewUrl]);

    const handleRefreshPreview = async () => {
        if (!previewUrl) return;

        setIsRefreshing(true);
        setPreviewStatus({ status: 'loading', message: 'Refreshing preview...' });

        try {
            // Force iframe reload
            if (iframeRef.current) {
                const currentSrc = iframeRef.current.src;
                iframeRef.current.src = '';
                setTimeout(() => {
                    if (iframeRef.current) {
                        iframeRef.current.src = currentSrc + '?t=' + Date.now();
                    }
                }, 100);
            }

            if (onRefreshPreview) {
                await onRefreshPreview();
            }

            toast({
                title: "Preview Refreshed",
                description: "PlayCanvas preview has been updated",
            });
        } catch (error) {
            setPreviewStatus({
                status: 'error',
                message: 'Failed to refresh preview',
                lastUpdate: new Date().toLocaleTimeString()
            });
            
            toast({
                title: "Refresh Failed",
                description: "Failed to refresh the preview",
                variant: "destructive",
            });
        } finally {
            setIsRefreshing(false);
        }
    };

    const handleToggleLiveUpdates = () => {
        const newState = !isLiveUpdatesEnabled;
        if (onToggleLiveUpdates) {
            onToggleLiveUpdates(newState);
        }
        
        toast({
            title: newState ? "Live Updates Enabled" : "Live Updates Disabled",
            description: newState 
                ? "Preview will auto-refresh every 5 seconds" 
                : "Preview will only refresh manually",
        });
    };

    const handleOpenFullscreen = () => {
        if (previewUrl) {
            if (onOpenFullscreen) {
                onOpenFullscreen();
            } else {
                window.open(previewUrl, '_blank');
            }
        }
    };

    const getStatusIcon = () => {
        switch (previewStatus.status) {
            case 'ready':
                return <CheckCircle className="w-4 h-4 text-green-500" />;
            case 'loading':
                return <Loader2 className="w-4 h-4 text-blue-500 animate-spin" />;
            case 'error':
                return <AlertCircle className="w-4 h-4 text-red-500" />;
            default:
                return <Monitor className="w-4 h-4 text-gray-500" />;
        }
    };

    const getStatusColor = () => {
        switch (previewStatus.status) {
            case 'ready':
                return 'bg-green-100 text-green-800 border-green-200';
            case 'loading':
                return 'bg-blue-100 text-blue-800 border-blue-200';
            case 'error':
                return 'bg-red-100 text-red-800 border-red-200';
            default:
                return 'bg-gray-100 text-gray-800 border-gray-200';
        }
    };

    if (!previewUrl) {
        return (
            <Card className="w-full">
                <CardContent className="flex items-center justify-center h-64 text-muted-foreground">
                    <div className="text-center space-y-2">
                        <Monitor className="w-12 h-12 mx-auto opacity-50" />
                        <p>No preview available</p>
                        <p className="text-sm">Start a conversation to generate a game preview</p>
                    </div>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card className="w-full">
            <CardHeader className="pb-3">
                <CardTitle className="flex items-center justify-between">
                    <div className="flex items-center space-x-2">
                        <Monitor className="w-5 h-5" />
                        <span>PlayCanvas Preview</span>
                    </div>
                    <div className="flex items-center space-x-2">
                        <Badge variant="outline" className={getStatusColor()}>
                            <div className="flex items-center space-x-1">
                                {getStatusIcon()}
                                <span className="capitalize">{previewStatus.status}</span>
                            </div>
                        </Badge>
                    </div>
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                {/* Status Message */}
                {previewStatus.message && (
                    <div className="text-xs text-muted-foreground bg-muted p-2 rounded">
                        {previewStatus.message}
                        {previewStatus.lastUpdate && (
                            <span className="ml-2">• {previewStatus.lastUpdate}</span>
                        )}
                    </div>
                )}

                {/* Preview Controls */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center space-x-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={handleToggleLiveUpdates}
                        >
                            {isLiveUpdatesEnabled ? (
                                <Pause className="w-3 h-3 mr-2" />
                            ) : (
                                <Play className="w-3 h-3 mr-2" />
                            )}
                            {isLiveUpdatesEnabled ? 'Pause' : 'Resume'} Live Updates
                        </Button>
                        
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={handleRefreshPreview}
                            disabled={isRefreshing}
                        >
                            <RefreshCw className={`w-3 h-3 mr-2 ${isRefreshing ? 'animate-spin' : ''}`} />
                            Refresh
                        </Button>
                    </div>
                    
                    <div className="flex items-center space-x-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={handleOpenFullscreen}
                        >
                            <ExternalLink className="w-3 h-3 mr-2" />
                            Open
                        </Button>
                        
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={handleOpenFullscreen}
                        >
                            <Maximize className="w-3 h-3" />
                        </Button>
                    </div>
                </div>

                {/* Preview Frame */}
                <div className="relative bg-black rounded-lg overflow-hidden" style={{ aspectRatio: '16/9' }}>
                    {previewStatus.status === 'loading' && (
                        <div className="absolute inset-0 flex items-center justify-center bg-muted">
                            <div className="text-center space-y-2">
                                <Loader2 className="w-8 h-8 mx-auto animate-spin text-primary" />
                                <p className="text-sm text-muted-foreground">Loading preview...</p>
                            </div>
                        </div>
                    )}
                    
                    {previewStatus.status === 'error' && (
                        <div className="absolute inset-0 flex items-center justify-center bg-red-50">
                            <div className="text-center space-y-2">
                                <AlertCircle className="w-8 h-8 mx-auto text-red-500" />
                                <p className="text-sm text-red-600">Failed to load preview</p>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={handleRefreshPreview}
                                >
                                    Try Again
                                </Button>
                            </div>
                        </div>
                    )}
                    
                    <iframe
                        ref={iframeRef}
                        src={previewUrl}
                        className="w-full h-full border-0"
                        title="PlayCanvas Preview"
                        sandbox="allow-scripts allow-same-origin allow-forms"
                        loading="lazy"
                    />
                </div>

                {/* Live Updates Indicator */}
                {isLiveUpdatesEnabled && (
                    <div className="flex items-center justify-center text-xs text-muted-foreground">
                        <div className="flex items-center space-x-1">
                            <div className="w-2 h-2 bg-green-500 rounded-full animate-pulse" />
                            <span>Live updates enabled</span>
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}