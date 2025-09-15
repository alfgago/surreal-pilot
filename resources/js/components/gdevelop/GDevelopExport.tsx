import React, { useState, useEffect } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Progress } from '@/components/ui/progress';
import { useToast } from '@/components/ui/use-toast';
import { 
    Download, 
    Settings, 
    Loader2,
    CheckCircle,
    AlertCircle,
    FileArchive,
    Smartphone,
    Monitor,
    Zap
} from 'lucide-react';
import axios from 'axios';

interface ExportOptions {
    includeAssets: boolean;
    optimizeForMobile: boolean;
    compressionLevel: 'none' | 'standard' | 'maximum';
    exportFormat: 'html5' | 'cordova' | 'electron';
}

interface GDevelopExportProps {
    sessionId: string;
    gameData: {
        sessionId: string;
        gameJson: any;
        assets: Array<{
            name: string;
            type: string;
            size: number;
        }>;
        version: number;
    };
    onExportComplete?: (downloadUrl: string) => void;
    onExportError?: (error: string) => void;
    className?: string;
}

interface ExportState {
    loading: boolean;
    progress: number;
    status: 'idle' | 'preparing' | 'building' | 'compressing' | 'completed' | 'failed';
    error: string | null;
    downloadUrl: string | null;
    estimatedSize: number;
    exportId: string | null;
}

export default function GDevelopExport({
    sessionId,
    gameData,
    onExportComplete,
    onExportError,
    className = ''
}: GDevelopExportProps) {
    const [exportOptions, setExportOptions] = useState<ExportOptions>({
        includeAssets: true,
        optimizeForMobile: true,
        compressionLevel: 'standard',
        exportFormat: 'html5'
    });

    const [exportState, setExportState] = useState<ExportState>({
        loading: false,
        progress: 0,
        status: 'idle',
        error: null,
        downloadUrl: null,
        estimatedSize: 0,
        exportId: null
    });

    const { toast } = useToast();

    // Calculate estimated export size
    useEffect(() => {
        calculateEstimatedSize();
    }, [exportOptions, gameData]);

    const calculateEstimatedSize = () => {
        let baseSize = 2; // Base HTML5 runtime size in MB
        
        if (exportOptions.includeAssets) {
            const assetsSize = gameData.assets.reduce((total, asset) => total + asset.size, 0);
            baseSize += assetsSize / (1024 * 1024); // Convert bytes to MB
        }

        // Apply compression factor
        const compressionFactor = {
            'none': 1.0,
            'standard': 0.7,
            'maximum': 0.5
        }[exportOptions.compressionLevel];

        const estimatedSize = baseSize * compressionFactor;
        setExportState(prev => ({ ...prev, estimatedSize }));
    };

    const handleExport = async () => {
        setExportState(prev => ({
            ...prev,
            loading: true,
            progress: 0,
            status: 'preparing',
            error: null,
            downloadUrl: null,
            exportId: null
        }));

        try {
            // Start export process
            const response = await axios.post(`/api/gdevelop/export/${sessionId}`, exportOptions);

            if (response.data.success) {
                const exportId = response.data.exportId;
                setExportState(prev => ({ ...prev, exportId, status: 'building' }));

                // Start polling for progress
                pollExportProgress(exportId);

                toast({
                    title: "Export Started",
                    description: "Your game export has begun. This may take a few minutes.",
                });
            } else {
                throw new Error(response.data.message || 'Failed to start export');
            }
        } catch (error: any) {
            const errorMessage = error.response?.data?.message || error.message || 'Failed to start export';
            
            setExportState(prev => ({
                ...prev,
                loading: false,
                status: 'failed',
                error: errorMessage
            }));

            onExportError?.(errorMessage);
            
            toast({
                title: "Export Failed",
                description: errorMessage,
                variant: "destructive",
            });
        }
    };

    const pollExportProgress = async (exportId: string) => {
        try {
            const response = await axios.get(`/api/gdevelop/export/${sessionId}/status`);
            
            if (response.data.success) {
                const { status, progress, error } = response.data;
                
                setExportState(prev => ({
                    ...prev,
                    progress: progress || prev.progress,
                    status: status || prev.status
                }));

                if (status === 'completed') {
                    // Export completed, get download URL
                    const downloadResponse = await axios.get(`/api/gdevelop/export/${sessionId}/download`);
                    
                    if (downloadResponse.data.success) {
                        const downloadUrl = downloadResponse.data.downloadUrl;
                        
                        setExportState(prev => ({
                            ...prev,
                            loading: false,
                            progress: 100,
                            status: 'completed',
                            downloadUrl
                        }));

                        onExportComplete?.(downloadUrl);

                        toast({
                            title: "Export Complete",
                            description: "Your game has been exported successfully!",
                        });

                        // Auto-download the file
                        triggerDownload(downloadUrl);
                    }
                } else if (status === 'failed') {
                    throw new Error(error || 'Export failed');
                } else {
                    // Continue polling
                    setTimeout(() => pollExportProgress(exportId), 2000);
                }
            }
        } catch (error: any) {
            const errorMessage = error.response?.data?.message || error.message || 'Export failed';
            
            setExportState(prev => ({
                ...prev,
                loading: false,
                status: 'failed',
                error: errorMessage
            }));

            onExportError?.(errorMessage);
            
            toast({
                title: "Export Failed",
                description: errorMessage,
                variant: "destructive",
            });
        }
    };

    const triggerDownload = (downloadUrl: string) => {
        const link = document.createElement('a');
        link.href = downloadUrl;
        link.download = `${gameData.sessionId}-game.zip`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    };

    const handleRetryExport = () => {
        setExportState(prev => ({
            ...prev,
            loading: false,
            progress: 0,
            status: 'idle',
            error: null,
            downloadUrl: null,
            exportId: null
        }));
    };

    const getStatusMessage = () => {
        switch (exportState.status) {
            case 'preparing':
                return 'Preparing export...';
            case 'building':
                return 'Building game files...';
            case 'compressing':
                return 'Compressing assets...';
            case 'completed':
                return 'Export completed successfully!';
            case 'failed':
                return exportState.error || 'Export failed';
            default:
                return 'Ready to export';
        }
    };

    const getStatusIcon = () => {
        switch (exportState.status) {
            case 'completed':
                return <CheckCircle className="w-4 h-4 text-green-500" />;
            case 'failed':
                return <AlertCircle className="w-4 h-4 text-red-500" />;
            case 'preparing':
            case 'building':
            case 'compressing':
                return <Loader2 className="w-4 h-4 animate-spin text-blue-500" />;
            default:
                return <FileArchive className="w-4 h-4 text-muted-foreground" />;
        }
    };

    return (
        <Card className={className}>
            <CardHeader className="pb-3">
                <CardTitle className="flex items-center space-x-2">
                    <Download className="w-5 h-5" />
                    <span>Export Game</span>
                    {exportState.status === 'completed' && (
                        <Badge variant="default" className="ml-auto">
                            Ready
                        </Badge>
                    )}
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                {/* Export Options */}
                <div className="space-y-4">
                    <div className="space-y-3">
                        <h4 className="text-sm font-medium flex items-center space-x-2">
                            <Settings className="w-4 h-4" />
                            <span>Export Options</span>
                        </h4>

                        {/* Include Assets */}
                        <div className="flex items-center space-x-2">
                            <Checkbox
                                id="includeAssets"
                                checked={exportOptions.includeAssets}
                                onCheckedChange={(checked) =>
                                    setExportOptions(prev => ({ ...prev, includeAssets: !!checked }))
                                }
                                disabled={exportState.loading}
                            />
                            <label htmlFor="includeAssets" className="text-sm">
                                Include all game assets ({gameData.assets.length} files)
                            </label>
                        </div>

                        {/* Mobile Optimization */}
                        <div className="flex items-center space-x-2">
                            <Checkbox
                                id="optimizeForMobile"
                                checked={exportOptions.optimizeForMobile}
                                onCheckedChange={(checked) =>
                                    setExportOptions(prev => ({ ...prev, optimizeForMobile: !!checked }))
                                }
                                disabled={exportState.loading}
                            />
                            <label htmlFor="optimizeForMobile" className="text-sm flex items-center space-x-1">
                                <Smartphone className="w-3 h-3" />
                                <span>Optimize for mobile devices</span>
                            </label>
                        </div>

                        {/* Compression Level */}
                        <div className="space-y-2">
                            <label className="text-sm font-medium">Compression Level</label>
                            <Select
                                value={exportOptions.compressionLevel}
                                onValueChange={(value: 'none' | 'standard' | 'maximum') =>
                                    setExportOptions(prev => ({ ...prev, compressionLevel: value }))
                                }
                                disabled={exportState.loading}
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">None (Fastest)</SelectItem>
                                    <SelectItem value="standard">Standard (Recommended)</SelectItem>
                                    <SelectItem value="maximum">Maximum (Smallest)</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        {/* Export Format */}
                        <div className="space-y-2">
                            <label className="text-sm font-medium">Export Format</label>
                            <Select
                                value={exportOptions.exportFormat}
                                onValueChange={(value: 'html5' | 'cordova' | 'electron') =>
                                    setExportOptions(prev => ({ ...prev, exportFormat: value }))
                                }
                                disabled={exportState.loading}
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="html5">
                                        <div className="flex items-center space-x-2">
                                            <Monitor className="w-3 h-3" />
                                            <span>HTML5 (Web)</span>
                                        </div>
                                    </SelectItem>
                                    <SelectItem value="cordova" disabled>
                                        <div className="flex items-center space-x-2">
                                            <Smartphone className="w-3 h-3" />
                                            <span>Cordova (Mobile) - Coming Soon</span>
                                        </div>
                                    </SelectItem>
                                    <SelectItem value="electron" disabled>
                                        <div className="flex items-center space-x-2">
                                            <Monitor className="w-3 h-3" />
                                            <span>Electron (Desktop) - Coming Soon</span>
                                        </div>
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    {/* Estimated Size */}
                    <div className="bg-muted p-3 rounded-lg">
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-muted-foreground">Estimated Size:</span>
                            <span className="font-medium">
                                {exportState.estimatedSize.toFixed(1)} MB
                            </span>
                        </div>
                    </div>
                </div>

                {/* Export Status */}
                {exportState.status !== 'idle' && (
                    <div className="space-y-3">
                        <div className="flex items-center space-x-2">
                            {getStatusIcon()}
                            <span className="text-sm">{getStatusMessage()}</span>
                        </div>

                        {exportState.loading && (
                            <Progress value={exportState.progress} className="w-full" />
                        )}
                    </div>
                )}

                {/* Export Actions */}
                <div className="space-y-2">
                    {exportState.status === 'idle' || exportState.status === 'failed' ? (
                        <Button
                            onClick={handleExport}
                            disabled={exportState.loading}
                            className="w-full"
                        >
                            {exportState.loading ? (
                                <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                            ) : (
                                <Download className="w-4 h-4 mr-2" />
                            )}
                            {exportState.status === 'failed' ? 'Retry Export' : 'Start Export'}
                        </Button>
                    ) : exportState.status === 'completed' && exportState.downloadUrl ? (
                        <div className="space-y-2">
                            <Button
                                onClick={() => triggerDownload(exportState.downloadUrl!)}
                                className="w-full"
                                variant="default"
                            >
                                <Download className="w-4 h-4 mr-2" />
                                Download Again
                            </Button>
                            <Button
                                onClick={handleRetryExport}
                                className="w-full"
                                variant="outline"
                            >
                                Export New Version
                            </Button>
                        </div>
                    ) : (
                        <Button disabled className="w-full">
                            <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                            Exporting... {exportState.progress}%
                        </Button>
                    )}
                </div>

                {/* Export Info */}
                <div className="text-xs text-muted-foreground space-y-1">
                    <div className="flex justify-between">
                        <span>Game Version:</span>
                        <span>v{gameData.version}</span>
                    </div>
                    <div className="flex justify-between">
                        <span>Assets:</span>
                        <span>{gameData.assets.length} files</span>
                    </div>
                    {exportState.exportId && (
                        <div className="flex justify-between">
                            <span>Export ID:</span>
                            <span className="font-mono">{exportState.exportId.slice(0, 8)}...</span>
                        </div>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}