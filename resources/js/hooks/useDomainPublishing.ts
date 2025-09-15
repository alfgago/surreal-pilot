import { useState, useCallback } from 'react';

interface DomainSetupOptions {
  domain: string;
}

interface DomainSetupResult {
  success: boolean;
  domain?: string;
  status?: string;
  dns_instructions?: {
    type: string;
    name: string;
    value: string;
    ttl: number;
    instructions: string[];
    common_providers: Record<string, string>;
  };
  verification_url?: string;
  estimated_propagation_time?: string;
  error?: string;
  troubleshooting?: Record<string, string[]>;
}

interface DomainVerificationResult {
  success: boolean;
  status?: string;
  message?: string;
  domain_url?: string;
  verified_at?: string;
  expected_ip?: string;
  resolved_ip?: string;
  current_status?: string;
  troubleshooting?: Record<string, string[]>;
  error?: string;
}

interface DomainStatusResult {
  success: boolean;
  domain?: {
    has_custom_domain: boolean;
    custom_domain?: string;
    domain_status?: 'pending' | 'active' | 'failed';
    domain_config?: {
      server_ip?: string;
      status_message?: string;
      last_check?: string;
      ssl_enabled?: boolean;
    };
    is_domain_active: boolean;
    is_domain_pending: boolean;
    is_domain_failed: boolean;
    custom_domain_url?: string;
    primary_url?: string;
  };
  error?: string;
}

interface UseDomainPublishingReturn {
  setupDomain: (gameId: number, options: DomainSetupOptions) => Promise<DomainSetupResult>;
  verifyDomain: (gameId: number) => Promise<DomainVerificationResult>;
  removeDomain: (gameId: number) => Promise<{ success: boolean; message?: string; error?: string }>;
  getDomainStatus: (gameId: number) => Promise<DomainStatusResult>;
  isLoading: boolean;
  error: string | null;
}

export const useDomainPublishing = (): UseDomainPublishingReturn => {
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const getCSRFToken = () => {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  };

  const setupDomain = useCallback(async (gameId: number, options: DomainSetupOptions): Promise<DomainSetupResult> => {
    setIsLoading(true);
    setError(null);

    try {
      const response = await fetch(`/api/games/${gameId}/domain`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': getCSRFToken(),
        },
        body: JSON.stringify(options),
      });

      const result: DomainSetupResult = await response.json();

      if (!response.ok) {
        setError(result.error || 'Failed to setup domain');
      }

      return result;
    } catch (err) {
      const errorMessage = 'Network error occurred while setting up domain';
      setError(errorMessage);
      return {
        success: false,
        error: errorMessage,
      };
    } finally {
      setIsLoading(false);
    }
  }, []);

  const verifyDomain = useCallback(async (gameId: number): Promise<DomainVerificationResult> => {
    setIsLoading(true);
    setError(null);

    try {
      const response = await fetch(`/api/games/${gameId}/domain/verify`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': getCSRFToken(),
        },
      });

      const result: DomainVerificationResult = await response.json();

      if (!response.ok && !result.success) {
        setError(result.error || 'Failed to verify domain');
      }

      return result;
    } catch (err) {
      const errorMessage = 'Network error occurred during domain verification';
      setError(errorMessage);
      return {
        success: false,
        error: errorMessage,
      };
    } finally {
      setIsLoading(false);
    }
  }, []);

  const removeDomain = useCallback(async (gameId: number) => {
    setIsLoading(true);
    setError(null);

    try {
      const response = await fetch(`/api/games/${gameId}/domain`, {
        method: 'DELETE',
        headers: {
          'X-CSRF-TOKEN': getCSRFToken(),
        },
      });

      const result = await response.json();

      if (!response.ok) {
        setError(result.error || 'Failed to remove domain');
      }

      return result;
    } catch (err) {
      const errorMessage = 'Network error occurred while removing domain';
      setError(errorMessage);
      return {
        success: false,
        error: errorMessage,
      };
    } finally {
      setIsLoading(false);
    }
  }, []);

  const getDomainStatus = useCallback(async (gameId: number): Promise<DomainStatusResult> => {
    setIsLoading(true);
    setError(null);

    try {
      const response = await fetch(`/api/games/${gameId}/domain/status`, {
        method: 'GET',
        headers: {
          'Content-Type': 'application/json',
        },
      });

      const result: DomainStatusResult = await response.json();

      if (!response.ok) {
        setError(result.error || 'Failed to get domain status');
      }

      return result;
    } catch (err) {
      const errorMessage = 'Network error occurred while getting domain status';
      setError(errorMessage);
      return {
        success: false,
        error: errorMessage,
      };
    } finally {
      setIsLoading(false);
    }
  }, []);

  return {
    setupDomain,
    verifyDomain,
    removeDomain,
    getDomainStatus,
    isLoading,
    error,
  };
};