import React, { useState, useEffect, useRef, useCallback } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { useToast } from '@/components/ui/use-toast';
import { 
    Play, 
    Pause, 
    RefreshCw, 
    Maximize, 
    Volume2, 
    VolumeX,
    Loader2,
    AlertCircle,
    Monitor,
    Smartphone,
    ExternalLink,
    Wifi,
    WifiOff,
    Activity
} from 'lucide-react';

interface GDevelopPreviewProps {
    gameData: {
        sessionId: string;
        previewUrl?: string;
        lastModified: string;
        version: number;
    };
    previewUrl: string;
    onExport?: () => void;
    onRefresh?: () => void;
    className?: string;
}

interface PreviewState {
    loading: boolean;
    error: string | null;
    gameLoaded: boolean;
    isPlaying: boolean;
    isMuted: boolean;
    viewMode: 'desktop' | 'mobile';
    connectionStatus: 'connected' | 'disconnected' | 'connecting';
    loadTimeout: boolean;
    performance: {
        loadTime: number;
        fps: number;
        memoryUsage: number;
        lastUpdate: number;
    };
}

export default function GDevelopPreview({
    gameData,
    previewUrl,
    onExport,
    onRefresh,
    className = ''
}: GDevelopPreviewProps) {
    const [previewState, setPreviewState] = useState<PreviewState>({
        loading: true,
        error: null,
        gameLoaded: false,
        isPlaying: true,
        isMuted: false,
        viewMode: 'desktop',
        connectionStatus: 'connecting',
        loadTimeout: false,
        performance: { loadTime: 0, fps: 0, memoryUsage: 0, lastUpdate: 0 }
    });

    const { toast } = useToast();
    const iframeRef = useRef<HTMLIFrameElement>(null);
    const loadStartTime = useRef<number>(0);
    const loadTimeoutRef = useRef<NodeJS.Timeout | null>(null);
    const performanceIntervalRef = useRef<NodeJS.Timeout | null>(null);
    const messageListenerRef = useRef<((event: MessageEvent) => void) | null>(null);

    // Load preview when URL changes
    useEffect(() => {
        if (previewUrl) {
            loadPreview();
        }
        return () => {
            // Cleanup on unmount
            if (loadTimeoutRef.current) {
                clearTimeout(loadTimeoutRef.current);
            }
            if (performanceIntervalRef.current) {
                clearInterval(performanceIntervalRef.current);
            }
            if (messageListenerRef.current) {
                window.removeEventListener('message', messageListenerRef.current);
            }
        };
    }, [previewUrl]);

    // Setup iframe communication
    useEffect(() => {
        const handleMessage = (event: MessageEvent) => {
            // Only accept messages from the preview iframe
            if (iframeRef.current && event.source === iframeRef.current.contentWindow) {
                switch (event.data.type) {
                    case 'gameLoaded':
                        setPreviewState(prev => ({
                            ...prev,
                            gameLoaded: true,
                            connectionStatus: 'connected'
                        }));
                        break;
                    case 'gameError':
                        setPreviewState(prev => ({
                            ...prev,
                            error: event.data.message || 'Game runtime error',
                            connectionStatus: 'disconnected'
                        }));
                        break;
                    case 'performance':
                        setPreviewState(prev => ({
                            ...prev,
                            performance: {
                                ...prev.performance,
                                fps: event.data.fps || 0,
                                memoryUsage: event.data.memoryUsage || 0,
                                lastUpdate: Date.now()
                            }
                        }));
                        break;
                    case 'gameStateChanged':
                        setPreviewState(prev => ({
                            ...prev,
                            isPlaying: event.data.isPlaying
                        }));
                        break;
                }
            }
        };

        messageListenerRef.current = handleMessage;
        window.addEventListener('message', handleMessage);

        return () => {
            if (messageListenerRef.current) {
                window.removeEventListener('message', messageListenerRef.current);
            }
        };
    }, []);

    const loadPreview = useCallback(() => {
        setPreviewState(prev => ({ 
            ...prev, 
            loading: true, 
            error: null, 
            gameLoaded: false,
            loadTimeout: false,
            connectionStatus: 'connecting'
        }));
        loadStartTime.current = Date.now();

        // Clear any existing timeout
        if (loadTimeoutRef.current) {
            clearTimeout(loadTimeoutRef.current);
        }

        // Set timeout for slow-loading games (5 seconds as per requirement 2.7)
        loadTimeoutRef.current = setTimeout(() => {
            setPreviewState(prev => {
                if (prev.loading) {
                    return {
                        ...prev,
                        loading: false,
                        loadTimeout: true,
                        error: 'Game preview is taking longer than expected to load',
                        connectionStatus: 'disconnected'
                    };
                }
                return prev;
            });

            toast({
                title: "Slow Loading",
                description: "The game preview is taking longer than expected. You can continue waiting or try refreshing.",
                variant: "destructive",
            });
        }, 5000);
    }, [toast]);

    const handleIframeLoad = useCallback(() => {
        const loadTime = Date.now() - loadStartTime.current;
        
        // Clear the timeout since loading completed
        if (loadTimeoutRef.current) {
            clearTimeout(loadTimeoutRef.current);
            loadTimeoutRef.current = null;
        }
        
        setPreviewState(prev => ({
            ...prev,
            loading: false,
            gameLoaded: true,
            loadTimeout: false,
            connectionStatus: 'connected',
            performance: { ...prev.performance, loadTime }
        }));

        // Start performance monitoring
        if (performanceIntervalRef.current) {
            clearInterval(performanceIntervalRef.current);
        }
        
        performanceIntervalRef.current = setInterval(() => {
            // Request performance data from the game iframe
            if (iframeRef.current?.contentWindow) {
                try {
                    iframeRef.current.contentWindow.postMessage({
                        type: 'requestPerformance'
                    }, '*');
                } catch (error) {
                    console.warn('Could not request performance data:', error);
                }
            }
        }, 2000);

        toast({
            title: "Game Loaded",
            description: `Preview loaded in ${loadTime}ms`,
        });
    }, [toast]);

    const handleIframeError = useCallback(() => {
        // Clear the timeout
        if (loadTimeoutRef.current) {
            clearTimeout(loadTimeoutRef.current);
            loadTimeoutRef.current = null;
        }

        setPreviewState(prev => ({
            ...prev,
            loading: false,
            error: 'Failed to load game preview',
            connectionStatus: 'disconnected'
        }));

        toast({
            title: "Preview Error",
            description: "Failed to load game preview. Please try refreshing.",
            variant: "destructive",
        });
    }, [toast]);

    const handleRefresh = useCallback(() => {
        if (iframeRef.current && previewUrl) {
            // Stop performance monitoring
            if (performanceIntervalRef.current) {
                clearInterval(performanceIntervalRef.current);
                performanceIntervalRef.current = null;
            }

            setPreviewState(prev => ({ 
                ...prev, 
                loading: true, 
                error: null, 
                loadTimeout: false,
                connectionStatus: 'connecting'
            }));
            
            iframeRef.current.src = previewUrl + '?t=' + Date.now();
            loadStartTime.current = Date.now();

            // Set new timeout
            if (loadTimeoutRef.current) {
                clearTimeout(loadTimeoutRef.current);
            }
            loadTimeoutRef.current = setTimeout(() => {
                setPreviewState(prev => {
                    if (prev.loading) {
                        return {
                            ...prev,
                            loading: false,
                            loadTimeout: true,
                            error: 'Game preview refresh is taking longer than expected',
                            connectionStatus: 'disconnected'
                        };
                    }
                    return prev;
                });
            }, 5000);
        }
        onRefresh?.();
    }, [previewUrl, onRefresh, toast]);

    const handleTogglePlay = () => {
        // In a real implementation, this would communicate with the game iframe
        setPreviewState(prev => ({ ...prev, isPlaying: !prev.isPlaying }));
        
        if (iframeRef.current) {
            try {
                // Send message to iframe to pause/resume game
                iframeRef.current.contentWindow?.postMessage({
                    type: previewState.isPlaying ? 'pause' : 'play'
                }, '*');
            } catch (error) {
                console.warn('Could not communicate with game iframe:', error);
            }
        }
    };

    const handleToggleMute = () => {
        setPreviewState(prev => ({ ...prev, isMuted: !prev.isMuted }));
        
        if (iframeRef.current) {
            try {
                // Send message to iframe to mute/unmute game
                iframeRef.current.contentWindow?.postMessage({
                    type: 'mute',
                    muted: !previewState.isMuted
                }, '*');
            } catch (error) {
                console.warn('Could not communicate with game iframe:', error);
            }
        }
    };

    const handleToggleViewMode = () => {
        setPreviewState(prev => ({
            ...prev,
            viewMode: prev.viewMode === 'desktop' ? 'mobile' : 'desktop'
        }));
    };

    const handleOpenFullscreen = () => {
        if (previewUrl) {
            window.open(previewUrl, '_blank');
        }
    };

    const getPreviewDimensions = () => {
        if (previewState.viewMode === 'mobile') {
            // Responsive mobile dimensions
            const isMobileDevice = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
            
            if (isMobileDevice) {
                // On actual mobile devices, use more of the screen
                return { 
                    width: '100%', 
                    height: '60vh',
                    maxWidth: '100%',
                    minHeight: '300px'
                };
            } else {
                // On desktop, simulate mobile dimensions
                return { 
                    width: '375px', 
                    height: '667px',
                    maxWidth: '100%',
                    aspectRatio: '9/16'
                };
            }
        }
        return { 
            width: '100%', 
            height: '400px',
            minHeight: '300px'
        };
    };

    const getPreviewClasses = () => {
        const baseClasses = "border rounded-lg overflow-hidden bg-black transition-all duration-200";
        const mobileClasses = previewState.viewMode === 'mobile' 
            ? "mx-auto shadow-lg" 
            : "";
        const loadingClasses = previewState.loading 
            ? "opacity-75" 
            : "";
        
        return `${baseClasses} ${mobileClasses} ${loadingClasses}`;
    };

    if (!previewUrl) {
        return (
            <Card className={className}>
                <CardContent className="flex items-center justify-center p-8">
                    <div className="text-center space-y-2">
                        <Monitor className="w-12 h-12 mx-auto text-muted-foreground" />
                        <h3 className="font-medium">No Preview Available</h3>
                        <p className="text-sm text-muted-foreground">
                            Generate a preview to see your game in action
                        </p>
                    </div>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card className={className}>
            <CardHeader className="pb-3">
                <CardTitle className="flex items-center justify-between">
                    <div className="flex items-center space-x-2">
                        <Monitor className="w-5 h-5" />
                        <span>Game Preview</span>
                        <Badge variant="outline" className="text-xs">
                            v{gameData.version}
                        </Badge>
                        {/* Connection Status */}
                        <div className="flex items-center space-x-1">
                            {previewState.connectionStatus === 'connected' && (
                                <Wifi className="w-3 h-3 text-green-500" title="Connected" />
                            )}
                            {previewState.connectionStatus === 'disconnected' && (
                                <WifiOff className="w-3 h-3 text-red-500" title="Disconnected" />
                            )}
                            {previewState.connectionStatus === 'connecting' && (
                                <Loader2 className="w-3 h-3 text-yellow-500 animate-spin" title="Connecting" />
                            )}
                        </div>
                    </div>
                    <div className="flex items-center space-x-1">
                        {/* View Mode Toggle */}
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={handleToggleViewMode}
                            title={`Switch to ${previewState.viewMode === 'desktop' ? 'mobile' : 'desktop'} view`}
                        >
                            {previewState.viewMode === 'desktop' ? (
                                <Monitor className="w-4 h-4" />
                            ) : (
                                <Smartphone className="w-4 h-4" />
                            )}
                        </Button>

                        {/* Refresh Button */}
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={handleRefresh}
                            disabled={previewState.loading}
                            title="Refresh preview"
                        >
                            <RefreshCw className={`w-4 h-4 ${previewState.loading ? 'animate-spin' : ''}`} />
                        </Button>

                        {/* Fullscreen Button */}
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={handleOpenFullscreen}
                            title="Open in new tab"
                            disabled={!previewState.gameLoaded}
                        >
                            <ExternalLink className="w-4 h-4" />
                        </Button>
                    </div>
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                {/* Game Controls */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center space-x-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={handleTogglePlay}
                            disabled={!previewState.gameLoaded}
                        >
                            {previewState.isPlaying ? (
                                <Pause className="w-3 h-3 mr-1" />
                            ) : (
                                <Play className="w-3 h-3 mr-1" />
                            )}
                            {previewState.isPlaying ? 'Pause' : 'Play'}
                        </Button>

                        <Button
                            variant="outline"
                            size="sm"
                            onClick={handleToggleMute}
                            disabled={!previewState.gameLoaded}
                        >
                            {previewState.isMuted ? (
                                <VolumeX className="w-3 h-3 mr-1" />
                            ) : (
                                <Volume2 className="w-3 h-3 mr-1" />
                            )}
                            {previewState.isMuted ? 'Unmute' : 'Mute'}
                        </Button>
                    </div>

                    {onExport && (
                        <Button
                            variant="default"
                            size="sm"
                            onClick={onExport}
                            disabled={!previewState.gameLoaded}
                        >
                            Export Game
                        </Button>
                    )}
                </div>

                {/* Preview Container */}
                <div className="relative">
                    {previewState.loading && (
                        <div className="absolute inset-0 bg-background/80 backdrop-blur-sm z-10 flex items-center justify-center">
                            <div className="flex items-center space-x-2">
                                <Loader2 className="w-4 h-4 animate-spin" />
                                <span className="text-sm">Loading game...</span>
                            </div>
                        </div>
                    )}

                    {previewState.error ? (
                        <div className="flex items-center justify-center p-8 border-2 border-dashed border-red-200 rounded-lg">
                            <div className="text-center space-y-3">
                                <AlertCircle className="w-8 h-8 mx-auto text-red-500" />
                                <h3 className="font-medium text-red-700">
                                    {previewState.loadTimeout ? "Loading Timeout" : "Preview Error"}
                                </h3>
                                <p className="text-sm text-red-600">{previewState.error}</p>
                                {previewState.loadTimeout && (
                                    <p className="text-xs text-muted-foreground">
                                        Complex games may take longer to load. You can continue waiting or try refreshing.
                                    </p>
                                )}
                                <div className="flex space-x-2 justify-center">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={handleRefresh}
                                        disabled={previewState.loading}
                                    >
                                        <RefreshCw className="w-3 h-3 mr-1" />
                                        Try Again
                                    </Button>
                                    {previewState.loadTimeout && (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => setPreviewState(prev => ({ 
                                                ...prev, 
                                                error: null, 
                                                loadTimeout: false,
                                                loading: true 
                                            }))}
                                        >
                                            Continue Waiting
                                        </Button>
                                    )}
                                </div>
                            </div>
                        </div>
                    ) : (
                        <div 
                            className={getPreviewClasses()}
                            style={getPreviewDimensions()}
                        >
                            <iframe
                                ref={iframeRef}
                                src={previewUrl}
                                className="w-full h-full border-0"
                                onLoad={handleIframeLoad}
                                onError={handleIframeError}
                                title="GDevelop Game Preview"
                                sandbox="allow-scripts allow-same-origin allow-pointer-lock allow-fullscreen allow-modals"
                                allow="gamepad; fullscreen; accelerometer; gyroscope; magnetometer; microphone; camera"
                                style={{ 
                                    touchAction: previewState.viewMode === 'mobile' ? 'manipulation' : 'auto'
                                }}
                            />
                        </div>
                    )}
                </div>

                {/* Performance Info */}
                {previewState.gameLoaded && previewState.performance.loadTime > 0 && (
                    <div className="text-xs text-muted-foreground space-y-1">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center space-x-1">
                                <Activity className="w-3 h-3" />
                                <span>Performance</span>
                            </div>
                            <Badge 
                                variant={previewState.performance.loadTime < 2000 ? "default" : "secondary"}
                                className="text-xs"
                            >
                                {previewState.performance.loadTime < 2000 ? "Fast" : "Slow"}
                            </Badge>
                        </div>
                        <div className="flex justify-between">
                            <span>Load Time:</span>
                            <span className={previewState.performance.loadTime > 5000 ? "text-red-500" : ""}>
                                {previewState.performance.loadTime}ms
                            </span>
                        </div>
                        {previewState.performance.fps > 0 && (
                            <div className="flex justify-between">
                                <span>FPS:</span>
                                <span className={previewState.performance.fps < 30 ? "text-yellow-500" : "text-green-500"}>
                                    {previewState.performance.fps}
                                </span>
                            </div>
                        )}
                        {previewState.performance.memoryUsage > 0 && (
                            <div className="flex justify-between">
                                <span>Memory:</span>
                                <span>{Math.round(previewState.performance.memoryUsage / 1024 / 1024)}MB</span>
                            </div>
                        )}
                        <div className="flex justify-between">
                            <span>View Mode:</span>
                            <span className="capitalize">{previewState.viewMode}</span>
                        </div>
                        <div className="flex justify-between">
                            <span>Connection:</span>
                            <span className={
                                previewState.connectionStatus === 'connected' ? "text-green-500" :
                                previewState.connectionStatus === 'disconnected' ? "text-red-500" :
                                "text-yellow-500"
                            }>
                                {previewState.connectionStatus}
                            </span>
                        </div>
                        <div className="flex justify-between">
                            <span>Last Updated:</span>
                            <span>{new Date(gameData.lastModified).toLocaleTimeString()}</span>
                        </div>
                    </div>
                )}

                {/* Mobile Instructions */}
                {previewState.viewMode === 'mobile' && previewState.gameLoaded && (
                    <div className="text-xs text-muted-foreground bg-muted p-3 rounded-lg space-y-1">
                        <div className="flex items-center space-x-1">
                            <Smartphone className="w-3 h-3" />
                            <strong>Mobile Preview Mode</strong>
                        </div>
                        <ul className="space-y-1 ml-4 list-disc list-inside">
                            <li>Touch controls are enabled for mobile interaction</li>
                            <li>Tap and drag to interact with game elements</li>
                            <li>Pinch to zoom may be available depending on game settings</li>
                            <li>Rotate your device to test different orientations</li>
                        </ul>
                        <div className="text-xs text-muted-foreground/70 mt-2">
                            Preview simulates mobile experience on {window.innerWidth}x{window.innerHeight} viewport
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}