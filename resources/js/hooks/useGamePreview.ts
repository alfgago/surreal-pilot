import { useState, useEffect, useCallback, useRef } from 'react';

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

interface UseGamePreviewOptions {
  autoRefresh?: boolean;
  refreshInterval?: number;
  onGameUpdate?: (gameData: GameData) => void;
  onError?: (error: string) => void;
}

interface UseGamePreviewReturn {
  gameData: GameData | null;
  isVisible: boolean;
  isFullscreen: boolean;
  loading: boolean;
  error: string | null;
  setGameData: (gameData: GameData | null) => void;
  setIsVisible: (visible: boolean) => void;
  toggleFullscreen: () => void;
  refreshPreview: () => void;
  clearError: () => void;
}

export function useGamePreview(options: UseGamePreviewOptions = {}): UseGamePreviewReturn {
  const {
    autoRefresh = true,
    refreshInterval = 2000,
    onGameUpdate,
    onError
  } = options;

  const [gameData, setGameData] = useState<GameData | null>(null);
  const [isVisible, setIsVisible] = useState(false);
  const [isFullscreen, setIsFullscreen] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  
  const refreshTimeoutRef = useRef<NodeJS.Timeout>();
  const lastInteractionCountRef = useRef<number>(0);

  // Clear error function
  const clearError = useCallback(() => {
    setError(null);
  }, []);

  // Refresh preview function
  const refreshPreview = useCallback(() => {
    if (!gameData?.preview_url) return;

    setLoading(true);
    setError(null);

    // Simulate refresh delay (in real implementation, this would trigger actual refresh)
    setTimeout(() => {
      setLoading(false);
      if (onGameUpdate && gameData) {
        onGameUpdate(gameData);
      }
    }, 500);
  }, [gameData, onGameUpdate]);

  // Toggle fullscreen
  const toggleFullscreen = useCallback(() => {
    setIsFullscreen(prev => {
      const newFullscreen = !prev;
      
      // Handle browser fullscreen API
      if (newFullscreen) {
        if (document.documentElement.requestFullscreen) {
          document.documentElement.requestFullscreen().catch(err => {
            console.warn('Could not enter fullscreen mode:', err);
          });
        }
      } else {
        if (document.exitFullscreen && document.fullscreenElement) {
          document.exitFullscreen().catch(err => {
            console.warn('Could not exit fullscreen mode:', err);
          });
        }
      }
      
      return newFullscreen;
    });
  }, []);

  // Handle browser fullscreen changes
  useEffect(() => {
    const handleFullscreenChange = () => {
      const isCurrentlyFullscreen = !!document.fullscreenElement;
      setIsFullscreen(isCurrentlyFullscreen);
    };

    document.addEventListener('fullscreenchange', handleFullscreenChange);
    document.addEventListener('webkitfullscreenchange', handleFullscreenChange);
    document.addEventListener('mozfullscreenchange', handleFullscreenChange);
    document.addEventListener('MSFullscreenChange', handleFullscreenChange);

    return () => {
      document.removeEventListener('fullscreenchange', handleFullscreenChange);
      document.removeEventListener('webkitfullscreenchange', handleFullscreenChange);
      document.removeEventListener('mozfullscreenchange', handleFullscreenChange);
      document.removeEventListener('MSFullscreenChange', handleFullscreenChange);
    };
  }, []);

  // Auto-refresh when interaction count changes
  useEffect(() => {
    if (!autoRefresh || !gameData) return;

    const currentInteractionCount = gameData.interaction_count || 0;
    
    // Check if interaction count has changed
    if (currentInteractionCount !== lastInteractionCountRef.current) {
      lastInteractionCountRef.current = currentInteractionCount;
      
      // Clear existing timeout
      if (refreshTimeoutRef.current) {
        clearTimeout(refreshTimeoutRef.current);
      }
      
      // Set new refresh timeout (< 2 seconds as per requirements)
      refreshTimeoutRef.current = setTimeout(() => {
        refreshPreview();
      }, Math.min(refreshInterval, 1800)); // Ensure < 2 seconds
    }
  }, [gameData, autoRefresh, refreshInterval, refreshPreview]);

  // Cleanup timeouts
  useEffect(() => {
    return () => {
      if (refreshTimeoutRef.current) {
        clearTimeout(refreshTimeoutRef.current);
      }
    };
  }, []);

  // Handle keyboard shortcuts
  useEffect(() => {
    const handleKeyDown = (event: KeyboardEvent) => {
      if (!isVisible) return;

      switch (event.key) {
        case 'Escape':
          if (isFullscreen) {
            toggleFullscreen();
          } else {
            setIsVisible(false);
          }
          break;
        case 'F11':
          event.preventDefault();
          toggleFullscreen();
          break;
        case 'r':
        case 'R':
          if (event.ctrlKey || event.metaKey) {
            event.preventDefault();
            refreshPreview();
          }
          break;
      }
    };

    if (isVisible) {
      document.addEventListener('keydown', handleKeyDown);
    }

    return () => {
      document.removeEventListener('keydown', handleKeyDown);
    };
  }, [isVisible, isFullscreen, toggleFullscreen, refreshPreview]);

  // Error handling
  useEffect(() => {
    if (error && onError) {
      onError(error);
    }
  }, [error, onError]);

  return {
    gameData,
    isVisible,
    isFullscreen,
    loading,
    error,
    setGameData,
    setIsVisible,
    toggleFullscreen,
    refreshPreview,
    clearError
  };
}

export default useGamePreview;