import React, { useState, useEffect } from 'react';
import { GamePreviewSidebar } from './GamePreviewSidebar';
import { MobileGameControls } from './MobileGameControls';
import { useGamePreview } from '@/hooks/useGamePreview';
import { cn } from '@/lib/utils';

interface GameData {
  id: number;
  title: string;
  description?: string;
  preview_url?: string;
  published_url?: string;
  thumbnail_url?: string;
  metadata?: any;
  engine_type: 'unreal' | 'playcanvas';
  status: string;
  version?: string;
  interaction_count?: number;
  thinking_history?: any[];
  game_mechanics?: any;
  sharing_settings?: any;
  build_status?: 'building' | 'success' | 'failed';
  last_build_at?: string;
  workspace?: {
    id: number;
    name: string;
    engine_type: string;
  };
}

interface ResponsiveGamePreviewProps {
  gameData: GameData | null;
  onShare: () => void;
  onPublish: () => void;
  onGameUpdate?: (gameData: GameData) => void;
  className?: string;
}

export function ResponsiveGamePreview({
  gameData,
  onShare,
  onPublish,
  onGameUpdate,
  className
}: ResponsiveGamePreviewProps) {
  const [isMobile, setIsMobile] = useState(false);
  const [isPlaying, setIsPlaying] = useState(true);
  const [isMuted, setIsMuted] = useState(false);

  const {
    isVisible,
    isFullscreen,
    loading,
    error,
    setIsVisible,
    toggleFullscreen,
    refreshPreview,
    clearError
  } = useGamePreview({
    autoRefresh: true,
    refreshInterval: 1800, // < 2 seconds as per requirements
    onGameUpdate,
    onError: (error) => {
      console.error('Game preview error:', error);
    }
  });

  // Detect mobile device
  useEffect(() => {
    const checkMobile = () => {
      const isMobileDevice = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
      const isMobileWidth = window.innerWidth < 768;
      setIsMobile(isMobileDevice || isMobileWidth);
    };

    checkMobile();
    window.addEventListener('resize', checkMobile);

    return () => window.removeEventListener('resize', checkMobile);
  }, []);

  // Auto-show preview when game data is available
  useEffect(() => {
    if (gameData?.preview_url && !isVisible) {
      setIsVisible(true);
    }
  }, [gameData?.preview_url, isVisible, setIsVisible]);

  // Handle play/pause
  const handlePlayPause = () => {
    setIsPlaying(!isPlaying);
    // In a real implementation, this would control the game's play state
  };

  // Handle mute toggle
  const handleMuteToggle = () => {
    setIsMuted(!isMuted);
    // In a real implementation, this would control the game's audio
  };

  // Handle settings (mobile only)
  const handleSettings = () => {
    // Open settings modal or panel
    console.log('Open game settings');
  };

  if (!gameData || !isVisible) {
    return null;
  }

  return (
    <div className={cn('relative', className)}>
      {/* Desktop Sidebar */}
      {!isMobile && (
        <GamePreviewSidebar
          gameData={gameData}
          isVisible={isVisible}
          isFullscreen={isFullscreen}
          onFullscreenToggle={toggleFullscreen}
          onShare={onShare}
          onPublish={onPublish}
          onRefresh={refreshPreview}
        />
      )}

      {/* Mobile View */}
      {isMobile && (
        <>
          {/* Mobile Game Container */}
          <div className={cn(
            'fixed inset-0 bg-background z-30',
            !isFullscreen && 'relative h-64'
          )}>
            {gameData.preview_url ? (
              <iframe
                src={gameData.preview_url}
                className="w-full h-full"
                allow="fullscreen; gamepad; microphone; camera"
                sandbox="allow-scripts allow-same-origin allow-forms allow-popups allow-pointer-lock"
                title={`${gameData.title} Preview`}
              />
            ) : (
              <div className="w-full h-full flex items-center justify-center bg-muted">
                <div className="text-center p-4">
                  <p className="text-sm text-muted-foreground">No preview available</p>
                  <p className="text-xs text-muted-foreground mt-1">
                    Game is still being generated
                  </p>
                </div>
              </div>
            )}
          </div>

          {/* Mobile Controls */}
          <MobileGameControls
            isPlaying={isPlaying}
            isMuted={isMuted}
            isFullscreen={isFullscreen}
            onPlayPause={handlePlayPause}
            onMuteToggle={handleMuteToggle}
            onFullscreenToggle={toggleFullscreen}
            onRefresh={refreshPreview}
            onShare={onShare}
            onSettings={handleSettings}
          />
        </>
      )}

      {/* Error Display */}
      {error && (
        <div className="fixed top-4 right-4 bg-destructive text-destructive-foreground p-3 rounded-md shadow-lg z-50">
          <p className="text-sm">{error}</p>
          <button
            onClick={clearError}
            className="text-xs underline mt-1"
          >
            Dismiss
          </button>
        </div>
      )}

      {/* Loading Indicator */}
      {loading && (
        <div className="fixed top-4 left-1/2 transform -translate-x-1/2 bg-background border border-border p-3 rounded-md shadow-lg z-50">
          <div className="flex items-center space-x-2">
            <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-primary"></div>
            <span className="text-sm">Refreshing preview...</span>
          </div>
        </div>
      )}
    </div>
  );
}

export default ResponsiveGamePreview;