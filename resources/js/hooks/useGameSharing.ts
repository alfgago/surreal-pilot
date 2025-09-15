import { useState, useCallback } from 'react';
import axios from 'axios';

interface SharingSettings {
  allowEmbedding?: boolean;
  showControls?: boolean;
  showInfo?: boolean;
  expirationDays?: number;
}

interface ShareResult {
  success: boolean;
  share_token?: string;
  share_url?: string;
  embed_url?: string;
  expires_at?: string;
  options?: SharingSettings;
  snapshot_path?: string;
  created_at?: string;
  message?: string;
  error?: string;
}

interface SharingStats {
  total_plays: number;
  last_played?: string;
  is_public: boolean;
  has_share_token: boolean;
  sharing_settings: SharingSettings;
  created_at: string;
  updated_at: string;
}

interface UseGameSharingReturn {
  loading: boolean;
  error: string | null;
  shareGame: (gameId: number, options: SharingSettings) => Promise<ShareResult>;
  updateSharingSettings: (gameId: number, settings: SharingSettings) => Promise<boolean>;
  revokeShareLink: (gameId: number) => Promise<boolean>;
  getSharingStats: (gameId: number) => Promise<SharingStats | null>;
  clearError: () => void;
}

export function useGameSharing(): UseGameSharingReturn {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const clearError = useCallback(() => {
    setError(null);
  }, []);

  const shareGame = useCallback(async (gameId: number, options: SharingSettings): Promise<ShareResult> => {
    setLoading(true);
    setError(null);

    try {
      const response = await axios.post(`/api/games/${gameId}/share`, options);
      
      if (response.data.success) {
        return response.data.sharing;
      } else {
        const errorMsg = response.data.message || 'Failed to create shareable link';
        setError(errorMsg);
        return {
          success: false,
          message: errorMsg,
        };
      }
    } catch (err: any) {
      const errorMsg = err.response?.data?.message || 'An unexpected error occurred while creating the share link';
      setError(errorMsg);
      return {
        success: false,
        message: errorMsg,
        error: err.message,
      };
    } finally {
      setLoading(false);
    }
  }, []);

  const updateSharingSettings = useCallback(async (gameId: number, settings: SharingSettings): Promise<boolean> => {
    setLoading(true);
    setError(null);

    try {
      const response = await axios.put(`/api/games/${gameId}/sharing/settings`, settings);
      
      if (response.data.success) {
        return true;
      } else {
        setError(response.data.message || 'Failed to update sharing settings');
        return false;
      }
    } catch (err: any) {
      const errorMsg = err.response?.data?.message || 'An unexpected error occurred while updating settings';
      setError(errorMsg);
      return false;
    } finally {
      setLoading(false);
    }
  }, []);

  const revokeShareLink = useCallback(async (gameId: number): Promise<boolean> => {
    setLoading(true);
    setError(null);

    try {
      const response = await axios.delete(`/api/games/${gameId}/sharing/revoke`);
      
      if (response.data.success) {
        return true;
      } else {
        setError(response.data.message || 'Failed to revoke share link');
        return false;
      }
    } catch (err: any) {
      const errorMsg = err.response?.data?.message || 'An unexpected error occurred while revoking the link';
      setError(errorMsg);
      return false;
    } finally {
      setLoading(false);
    }
  }, []);

  const getSharingStats = useCallback(async (gameId: number): Promise<SharingStats | null> => {
    setLoading(true);
    setError(null);

    try {
      const response = await axios.get(`/api/games/${gameId}/sharing/stats`);
      
      if (response.data.success) {
        return response.data.stats;
      } else {
        setError(response.data.message || 'Failed to get sharing stats');
        return null;
      }
    } catch (err: any) {
      // Don't set error for stats loading failure as it's not critical
      console.error('Failed to load sharing stats:', err);
      return null;
    } finally {
      setLoading(false);
    }
  }, []);

  return {
    loading,
    error,
    shareGame,
    updateSharingSettings,
    revokeShareLink,
    getSharingStats,
    clearError,
  };
}

export default useGameSharing;