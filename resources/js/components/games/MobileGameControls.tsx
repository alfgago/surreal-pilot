import React, { useState, useEffect, useCallback } from 'react';
import { Button } from '@/components/ui/button';
import { 
  Play, 
  Pause, 
  Volume2, 
  VolumeX, 
  RotateCcw,
  Maximize,
  Share,
  Settings,
  ChevronUp,
  ChevronDown
} from 'lucide-react';
import { cn } from '@/lib/utils';

interface MobileGameControlsProps {
  isPlaying: boolean;
  isMuted: boolean;
  isFullscreen: boolean;
  onPlayPause: () => void;
  onMuteToggle: () => void;
  onFullscreenToggle: () => void;
  onRefresh: () => void;
  onShare: () => void;
  onSettings?: () => void;
  className?: string;
}

export function MobileGameControls({
  isPlaying,
  isMuted,
  isFullscreen,
  onPlayPause,
  onMuteToggle,
  onFullscreenToggle,
  onRefresh,
  onShare,
  onSettings,
  className
}: MobileGameControlsProps) {
  const [isExpanded, setIsExpanded] = useState(false);
  const [orientation, setOrientation] = useState<'portrait' | 'landscape'>('portrait');

  // Handle orientation changes
  useEffect(() => {
    const handleOrientationChange = () => {
      const isLandscape = window.innerWidth > window.innerHeight;
      setOrientation(isLandscape ? 'landscape' : 'portrait');
    };

    handleOrientationChange();
    window.addEventListener('resize', handleOrientationChange);
    window.addEventListener('orientationchange', handleOrientationChange);

    return () => {
      window.removeEventListener('resize', handleOrientationChange);
      window.removeEventListener('orientationchange', handleOrientationChange);
    };
  }, []);

  // Auto-collapse controls after inactivity
  useEffect(() => {
    if (!isExpanded) return;

    const timer = setTimeout(() => {
      setIsExpanded(false);
    }, 3000); // Auto-collapse after 3 seconds

    return () => clearTimeout(timer);
  }, [isExpanded]);

  // Handle touch gestures
  const handleTouchStart = useCallback((e: React.TouchEvent) => {
    // Prevent default to avoid scrolling issues
    if (isFullscreen) {
      e.preventDefault();
    }
  }, [isFullscreen]);

  const handleTouchEnd = useCallback((e: React.TouchEvent) => {
    // Show controls on tap when in fullscreen
    if (isFullscreen && !isExpanded) {
      setIsExpanded(true);
    }
  }, [isFullscreen, isExpanded]);

  const controlsClasses = cn(
    'fixed bottom-0 left-0 right-0 bg-background/90 backdrop-blur-sm border-t border-border transition-all duration-300 z-50',
    isExpanded ? 'translate-y-0' : 'translate-y-full',
    !isFullscreen && 'relative translate-y-0 bg-transparent backdrop-blur-none border-t-0',
    orientation === 'landscape' && isFullscreen && 'bottom-2 left-2 right-2 rounded-lg border',
    className
  );

  const buttonSize = orientation === 'landscape' ? 'sm' : 'default';
  const iconSize = orientation === 'landscape' ? 'w-4 h-4' : 'w-5 h-5';

  return (
    <>
      {/* Touch area for showing controls in fullscreen */}
      {isFullscreen && !isExpanded && (
        <div
          className="fixed inset-0 z-40"
          onTouchStart={handleTouchStart}
          onTouchEnd={handleTouchEnd}
          onClick={() => setIsExpanded(true)}
        />
      )}

      {/* Control toggle button (only in fullscreen) */}
      {isFullscreen && (
        <Button
          variant="secondary"
          size="sm"
          className={cn(
            'fixed bottom-4 right-4 z-50 rounded-full h-12 w-12 p-0 transition-all duration-300',
            isExpanded && 'opacity-0 pointer-events-none'
          )}
          onClick={() => setIsExpanded(true)}
        >
          <ChevronUp className="w-5 h-5" />
        </Button>
      )}

      {/* Main controls */}
      <div className={controlsClasses}>
        <div className="p-4">
          {/* Collapse button (only in fullscreen) */}
          {isFullscreen && (
            <div className="flex justify-center mb-2">
              <Button
                variant="ghost"
                size="sm"
                onClick={() => setIsExpanded(false)}
                className="h-6 w-12 p-0"
              >
                <ChevronDown className="w-4 h-4" />
              </Button>
            </div>
          )}

          {/* Primary controls */}
          <div className="flex items-center justify-center space-x-4 mb-3">
            <Button
              variant="outline"
              size={buttonSize}
              onClick={onPlayPause}
              className={cn(
                'h-12 w-12 p-0 rounded-full',
                orientation === 'landscape' && 'h-10 w-10'
              )}
            >
              {isPlaying ? (
                <Pause className={iconSize} />
              ) : (
                <Play className={iconSize} />
              )}
            </Button>

            <Button
              variant="outline"
              size={buttonSize}
              onClick={onMuteToggle}
              className={cn(
                'h-12 w-12 p-0 rounded-full',
                orientation === 'landscape' && 'h-10 w-10'
              )}
            >
              {isMuted ? (
                <VolumeX className={iconSize} />
              ) : (
                <Volume2 className={iconSize} />
              )}
            </Button>

            <Button
              variant="outline"
              size={buttonSize}
              onClick={onRefresh}
              className={cn(
                'h-12 w-12 p-0 rounded-full',
                orientation === 'landscape' && 'h-10 w-10'
              )}
            >
              <RotateCcw className={iconSize} />
            </Button>
          </div>

          {/* Secondary controls */}
          <div className="flex items-center justify-center space-x-3">
            <Button
              variant="outline"
              size={buttonSize}
              onClick={onFullscreenToggle}
              className={cn(
                'flex-1 max-w-24',
                orientation === 'landscape' && 'max-w-20'
              )}
            >
              <Maximize className={cn(iconSize, 'mr-1')} />
              {orientation === 'portrait' && (
                <span className="text-xs">
                  {isFullscreen ? 'Exit' : 'Full'}
                </span>
              )}
            </Button>

            <Button
              variant="outline"
              size={buttonSize}
              onClick={onShare}
              className={cn(
                'flex-1 max-w-24',
                orientation === 'landscape' && 'max-w-20'
              )}
            >
              <Share className={cn(iconSize, 'mr-1')} />
              {orientation === 'portrait' && (
                <span className="text-xs">Share</span>
              )}
            </Button>

            {onSettings && (
              <Button
                variant="outline"
                size={buttonSize}
                onClick={onSettings}
                className={cn(
                  'flex-1 max-w-24',
                  orientation === 'landscape' && 'max-w-20'
                )}
              >
                <Settings className={cn(iconSize, 'mr-1')} />
                {orientation === 'portrait' && (
                  <span className="text-xs">Settings</span>
                )}
              </Button>
            )}
          </div>
        </div>
      </div>
    </>
  );
}

export default MobileGameControls;