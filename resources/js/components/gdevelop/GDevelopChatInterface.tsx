import React, { useState, useEffect, useRef } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { useToast } from '@/components/ui/use-toast';
import { 
    Play, 
    Download, 
    RefreshCw, 
    Gamepad2, 
    Loader2,
    AlertCircle,
    ExternalLink,
    Smartphone,
    Monitor,
    Settings
} from 'lucide-react';
import axios from 'axios';
import GDevelopPreview from './GDevelopPreview';
import GDevelopExport from './GDevelopExport';
import MobileGameControls from './MobileGameControls';

interface GDevelopGameData {
    sessionId: string;
    gameJson: any;
    assets: GameAsset[];
    previewUrl?: string;
    lastModified: string;
    version: number;
}

interface GameAsset {
    name: string;
    type: 'sprite' | 'sound' | 'font' | 'texture';
    path: string;
    size: number;
}

interface GDevelopChatInterfaceProps {
    workspaceId: string;
    sessionId: string;
    onGameGenerated?: (gameData: GDevelopGameData) => void;
    onPreviewReady?: (previewUrl: string) => void;
}

interface PreviewState {
    loading: boolean;
    error: string | null;
    gameLoaded: boolean;
    performance: {
        loadTime: number;
        fps: number;
        memoryUsage: number;
    };
}

interface MobileOptions {
    mobile_optimized: boolean;
    target_device: 'desktop' | 'mobile' | 'tablet';
    control_scheme: 'virtual_dpad' | 'touch_direct' | 'drag_drop' | 'touch_gesture';
    orientation: 'portrait' | 'landscape' | 'default';
    touch_controls: boolean;
    responsive_ui: boolean;
}



export default function GDevelopChatInterface({
    workspaceId,
    sessionId,
    onGameGenerated,
    onPreviewReady
}: GDevelopChatInterfaceProps) {
    const [gameData, setGameData] = useState<GDevelopGameData | null>(null);
    const [previewState, setPreviewState] = useState<PreviewState>({
        loading: false,
        error: null,
        gameLoaded: false,
        performance: { loadTime: 0, fps: 0, memoryUsage: 0 }
    });

    const [isInitialized, setIsInitialized] = useState(false);
    const [mobileOptions, setMobileOptions] = useState<MobileOptions>({
        mobile_optimized: false,
        target_device: 'desktop',
        control_scheme: 'touch_direct',
        orientation: 'default',
        touch_controls: false,
        responsive_ui: false
    });
    const [showMobileControls, setShowMobileControls] = useState(false);
    const [showMobileSettings, setShowMobileSettings] = useState(false);

    const { toast } = useToast();
    const previewIframeRef = useRef<HTMLIFrameElement>(null);

    // Initialize GDevelop session
    useEffect(() => {
        initializeSession();
        detectMobileDevice();
    }, [workspaceId, sessionId]);

    // Detect mobile device and auto-enable mobile optimizations
    const detectMobileDevice = () => {
        const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        const isTablet = /iPad|Android(?=.*\bMobile\b)(?=.*\bSafari\b)|Android(?=.*\bTablet\b)/i.test(navigator.userAgent);
        const isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
        
        if (isMobile || isTablet || isTouchDevice) {
            setMobileOptions(prev => ({
                ...prev,
                mobile_optimized: true,
                target_device: isTablet ? 'tablet' : isMobile ? 'mobile' : 'desktop',
                touch_controls: true,
                responsive_ui: true,
                orientation: window.innerHeight > window.innerWidth ? 'portrait' : 'landscape'
            }));
            setShowMobileControls(true);
        }
    };

    const initializeSession = async () => {
        try {
            const response = await axios.get(`/api/gdevelop/session/${sessionId}`);
            if (response.data.success && response.data.gameData) {
                setGameData(response.data.gameData);
                setIsInitialized(true);
                onGameGenerated?.(response.data.gameData);
            } else {
                setIsInitialized(true);
            }
        } catch (error) {
            console.error('Failed to initialize GDevelop session:', error);
            setIsInitialized(true);
        }
    };

    const handlePreviewGame = async () => {
        if (!gameData) {
            toast({
                title: "No Game Data",
                description: "Please create a game first before previewing.",
                variant: "destructive",
            });
            return;
        }

        setPreviewState(prev => ({ ...prev, loading: true, error: null }));
        const startTime = Date.now();

        try {
            const response = await axios.get(`/api/gdevelop/preview/${sessionId}`);
            
            if (response.data.success) {
                const loadTime = Date.now() - startTime;
                const previewUrl = response.data.previewUrl;
                
                setPreviewState(prev => ({
                    ...prev,
                    loading: false,
                    gameLoaded: true,
                    performance: { ...prev.performance, loadTime }
                }));

                // Update game data with preview URL
                setGameData(prev => prev ? { ...prev, previewUrl } : null);
                onPreviewReady?.(previewUrl);

                toast({
                    title: "Preview Ready",
                    description: `Game preview generated in ${loadTime}ms`,
                });
            } else {
                throw new Error(response.data.message || 'Failed to generate preview');
            }
        } catch (error: any) {
            setPreviewState(prev => ({
                ...prev,
                loading: false,
                error: error.response?.data?.message || error.message || 'Failed to generate preview'
            }));

            toast({
                title: "Preview Failed",
                description: "Failed to generate game preview. Please try again.",
                variant: "destructive",
            });
        }
    };



    const handleRefreshPreview = async () => {
        if (gameData?.previewUrl) {
            setPreviewState(prev => ({ ...prev, loading: true }));
            
            // Reload the iframe
            if (previewIframeRef.current) {
                previewIframeRef.current.src = gameData.previewUrl + '?t=' + Date.now();
            }
            
            setTimeout(() => {
                setPreviewState(prev => ({ ...prev, loading: false }));
            }, 1000);
        }
    };

    const handleOpenFullscreen = () => {
        if (gameData?.previewUrl) {
            window.open(gameData.previewUrl, '_blank');
        }
    };

    const handleMobileControlInput = (input: any) => {
        // Forward mobile control input to the game iframe
        if (gameData?.previewUrl && previewIframeRef.current?.contentWindow) {
            try {
                previewIframeRef.current.contentWindow.postMessage({
                    type: 'mobileInput',
                    input: input
                }, '*');
            } catch (error) {
                console.warn('Could not send mobile input to game:', error);
            }
        }
    };

    const handleMobileOptionsChange = (newOptions: Partial<MobileOptions>) => {
        setMobileOptions(prev => ({ ...prev, ...newOptions }));
        
        // If mobile optimization is enabled/disabled, show/hide controls
        if (newOptions.mobile_optimized !== undefined) {
            setShowMobileControls(newOptions.mobile_optimized);
        }
    };

    const renderMobileSettings = () => (
        <Card className="mb-4">
            <CardHeader className="pb-3">
                <CardTitle className="flex items-center justify-between">
                    <div className="flex items-center space-x-2">
                        <Smartphone className="w-4 h-4" />
                        <span className="text-sm">Mobile Optimization</span>
                    </div>
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => setShowMobileSettings(!showMobileSettings)}
                    >
                        <Settings className="w-3 h-3" />
                    </Button>
                </CardTitle>
            </CardHeader>
            {showMobileSettings && (
                <CardContent className="space-y-3">
                    <div className="flex items-center justify-between">
                        <span className="text-sm">Mobile Optimized</span>
                        <Button
                            variant={mobileOptions.mobile_optimized ? "default" : "outline"}
                            size="sm"
                            onClick={() => handleMobileOptionsChange({ 
                                mobile_optimized: !mobileOptions.mobile_optimized 
                            })}
                        >
                            {mobileOptions.mobile_optimized ? 'Enabled' : 'Disabled'}
                        </Button>
                    </div>
                    
                    {mobileOptions.mobile_optimized && (
                        <>
                            <div className="flex items-center justify-between">
                                <span className="text-sm">Target Device</span>
                                <select
                                    value={mobileOptions.target_device}
                                    onChange={(e) => handleMobileOptionsChange({ 
                                        target_device: e.target.value as 'desktop' | 'mobile' | 'tablet'
                                    })}
                                    className="text-sm border rounded px-2 py-1"
                                >
                                    <option value="desktop">Desktop</option>
                                    <option value="mobile">Mobile</option>
                                    <option value="tablet">Tablet</option>
                                </select>
                            </div>
                            
                            <div className="flex items-center justify-between">
                                <span className="text-sm">Control Scheme</span>
                                <select
                                    value={mobileOptions.control_scheme}
                                    onChange={(e) => handleMobileOptionsChange({ 
                                        control_scheme: e.target.value as any
                                    })}
                                    className="text-sm border rounded px-2 py-1"
                                >
                                    <option value="virtual_dpad">Virtual D-Pad</option>
                                    <option value="touch_direct">Touch Direct</option>
                                    <option value="drag_drop">Drag & Drop</option>
                                    <option value="touch_gesture">Touch Gesture</option>
                                </select>
                            </div>
                            
                            <div className="flex items-center justify-between">
                                <span className="text-sm">Orientation</span>
                                <select
                                    value={mobileOptions.orientation}
                                    onChange={(e) => handleMobileOptionsChange({ 
                                        orientation: e.target.value as 'portrait' | 'landscape' | 'default'
                                    })}
                                    className="text-sm border rounded px-2 py-1"
                                >
                                    <option value="default">Default</option>
                                    <option value="portrait">Portrait</option>
                                    <option value="landscape">Landscape</option>
                                </select>
                            </div>
                            
                            <div className="flex items-center justify-between">
                                <span className="text-sm">Touch Controls</span>
                                <Button
                                    variant={mobileOptions.touch_controls ? "default" : "outline"}
                                    size="sm"
                                    onClick={() => handleMobileOptionsChange({ 
                                        touch_controls: !mobileOptions.touch_controls 
                                    })}
                                >
                                    {mobileOptions.touch_controls ? 'On' : 'Off'}
                                </Button>
                            </div>
                            
                            <div className="flex items-center justify-between">
                                <span className="text-sm">Responsive UI</span>
                                <Button
                                    variant={mobileOptions.responsive_ui ? "default" : "outline"}
                                    size="sm"
                                    onClick={() => handleMobileOptionsChange({ 
                                        responsive_ui: !mobileOptions.responsive_ui 
                                    })}
                                >
                                    {mobileOptions.responsive_ui ? 'On' : 'Off'}
                                </Button>
                            </div>
                        </>
                    )}
                </CardContent>
            )}
        </Card>
    );

    if (!isInitialized) {
        return (
            <Card>
                <CardContent className="flex items-center justify-center p-6">
                    <div className="flex items-center space-x-2">
                        <Loader2 className="w-4 h-4 animate-spin" />
                        <span>Initializing GDevelop session...</span>
                    </div>
                </CardContent>
            </Card>
        );
    }

    return (
        <div className="space-y-4">
            {/* Mobile Settings */}
            {renderMobileSettings()}
            
            {/* Game Status Card */}
            <Card>
                <CardHeader className="pb-3">
                    <CardTitle className="flex items-center space-x-2">
                        <Gamepad2 className="w-5 h-5" />
                        <span>GDevelop Game</span>
                        {gameData && (
                            <Badge variant="outline" className="ml-auto">
                                v{gameData.version}
                            </Badge>
                        )}
                    </CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    {gameData ? (
                        <div className="space-y-3">
                            <div className="flex items-center justify-between text-sm">
                                <span className="text-muted-foreground">Session ID:</span>
                                <span className="font-mono text-xs">{gameData.sessionId}</span>
                            </div>
                            <div className="flex items-center justify-between text-sm">
                                <span className="text-muted-foreground">Last Modified:</span>
                                <span>{new Date(gameData.lastModified).toLocaleString()}</span>
                            </div>
                            <div className="flex items-center justify-between text-sm">
                                <span className="text-muted-foreground">Assets:</span>
                                <span>{gameData.assets.length} files</span>
                            </div>
                        </div>
                    ) : (
                        <div className="text-center text-muted-foreground py-4">
                            <Gamepad2 className="w-8 h-8 mx-auto mb-2 opacity-50" />
                            <p>No game created yet</p>
                            <p className="text-xs">Start chatting to create your first GDevelop game!</p>
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* Game Actions Card */}
            {gameData && (
                <Card>
                    <CardHeader className="pb-3">
                        <CardTitle>Game Actions</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {/* Preview Button */}
                        <Button
                            onClick={handlePreviewGame}
                            disabled={previewState.loading}
                            className="w-full"
                            variant="outline"
                        >
                            {previewState.loading ? (
                                <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                            ) : (
                                <Play className="w-4 h-4 mr-2" />
                            )}
                            {previewState.loading ? 'Generating Preview...' : 'Preview Game'}
                        </Button>

                        {/* Export Component */}
                        <GDevelopExport
                            sessionId={sessionId}
                            gameData={gameData}
                            onExportComplete={(downloadUrl) => {
                                toast({
                                    title: "Export Complete",
                                    description: "Your game has been exported successfully!",
                                });
                            }}
                            onExportError={(error) => {
                                toast({
                                    title: "Export Failed",
                                    description: error,
                                    variant: "destructive",
                                });
                            }}
                        />

                        {/* Preview Controls */}
                        {gameData.previewUrl && (
                            <div className="flex space-x-2">
                                <Button
                                    onClick={handleRefreshPreview}
                                    disabled={previewState.loading}
                                    variant="outline"
                                    size="sm"
                                    className="flex-1"
                                >
                                    <RefreshCw className="w-3 h-3 mr-1" />
                                    Refresh
                                </Button>
                                <Button
                                    onClick={handleOpenFullscreen}
                                    variant="outline"
                                    size="sm"
                                    className="flex-1"
                                >
                                    <ExternalLink className="w-3 h-3 mr-1" />
                                    Fullscreen
                                </Button>
                            </div>
                        )}
                    </CardContent>
                </Card>
            )}

            {/* Preview Status */}
            {previewState.error && (
                <Card className="border-red-200">
                    <CardContent className="pt-4">
                        <div className="flex items-center space-x-2 text-red-600">
                            <AlertCircle className="w-4 h-4" />
                            <span className="text-sm">{previewState.error}</span>
                        </div>
                    </CardContent>
                </Card>
            )}



            {/* Game Preview */}
            {gameData?.previewUrl && (
                <GDevelopPreview
                    gameData={gameData}
                    previewUrl={gameData.previewUrl}
                    onRefresh={handleRefreshPreview}
                />
            )}

            {/* Performance Info */}
            {previewState.gameLoaded && previewState.performance.loadTime > 0 && (
                <Card>
                    <CardHeader className="pb-3">
                        <CardTitle className="text-sm">Performance</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-2 text-xs">
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Load Time:</span>
                                <span>{previewState.performance.loadTime}ms</span>
                            </div>
                            {previewState.performance.fps > 0 && (
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">FPS:</span>
                                    <span>{previewState.performance.fps}</span>
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* Mobile Game Controls */}
            {showMobileControls && gameData && mobileOptions.mobile_optimized && (
                <MobileGameControls
                    gameType={gameData.gameJson?.properties?.gameType || 'basic'}
                    controlScheme={mobileOptions.control_scheme}
                    onControlInput={handleMobileControlInput}
                    isVisible={mobileOptions.touch_controls}
                />
            )}
        </div>
    );
}