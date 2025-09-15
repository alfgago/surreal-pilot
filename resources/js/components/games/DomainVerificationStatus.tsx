import React, { useState, useEffect } from 'react';
import { 
  CheckCircleIcon, 
  XCircleIcon, 
  ClockIcon, 
  ExclamationTriangleIcon,
  ArrowPathIcon 
} from '@heroicons/react/24/outline';

interface DomainVerificationStatusProps {
  gameId: number;
  domain: string;
  status: 'pending' | 'active' | 'failed' | null;
  lastCheck?: string;
  statusMessage?: string;
  expectedIp?: string;
  resolvedIp?: string;
  onVerificationComplete?: (result: any) => void;
  className?: string;
}

interface VerificationResult {
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

const DomainVerificationStatus: React.FC<DomainVerificationStatusProps> = ({
  gameId,
  domain,
  status,
  lastCheck,
  statusMessage,
  expectedIp,
  resolvedIp,
  onVerificationComplete,
  className = ''
}) => {
  const [isVerifying, setIsVerifying] = useState(false);
  const [verificationResult, setVerificationResult] = useState<VerificationResult | null>(null);
  const [autoCheckInterval, setAutoCheckInterval] = useState<NodeJS.Timeout | null>(null);

  useEffect(() => {
    // Auto-check every 30 seconds if status is pending
    if (status === 'pending') {
      const interval = setInterval(() => {
        handleVerifyDomain(true); // Silent verification
      }, 30000);
      setAutoCheckInterval(interval);

      return () => {
        if (interval) clearInterval(interval);
      };
    } else {
      if (autoCheckInterval) {
        clearInterval(autoCheckInterval);
        setAutoCheckInterval(null);
      }
    }
  }, [status]);

  useEffect(() => {
    return () => {
      if (autoCheckInterval) {
        clearInterval(autoCheckInterval);
      }
    };
  }, []);

  const handleVerifyDomain = async (silent = false) => {
    if (!silent) setIsVerifying(true);

    try {
      const response = await fetch(`/api/games/${gameId}/domain/verify`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
      });

      const result: VerificationResult = await response.json();
      setVerificationResult(result);

      if (result.success && onVerificationComplete) {
        onVerificationComplete(result);
      }
    } catch (err) {
      if (!silent) {
        setVerificationResult({
          success: false,
          error: 'Network error occurred during verification.'
        });
      }
    } finally {
      if (!silent) setIsVerifying(false);
    }
  };

  const getStatusIcon = () => {
    switch (status) {
      case 'active':
        return <CheckCircleIcon className="w-8 h-8 text-green-500" />;
      case 'failed':
        return <XCircleIcon className="w-8 h-8 text-red-500" />;
      case 'pending':
        return <ClockIcon className="w-8 h-8 text-yellow-500" />;
      default:
        return <ExclamationTriangleIcon className="w-8 h-8 text-gray-400" />;
    }
  };

  const getStatusColor = () => {
    switch (status) {
      case 'active':
        return 'bg-green-50 border-green-200';
      case 'failed':
        return 'bg-red-50 border-red-200';
      case 'pending':
        return 'bg-yellow-50 border-yellow-200';
      default:
        return 'bg-gray-50 border-gray-200';
    }
  };

  const getStatusText = () => {
    switch (status) {
      case 'active':
        return 'Domain Active';
      case 'failed':
        return 'Verification Failed';
      case 'pending':
        return 'Verification Pending';
      default:
        return 'Not Configured';
    }
  };

  const getStatusDescription = () => {
    switch (status) {
      case 'active':
        return 'Your domain is successfully configured and pointing to our servers.';
      case 'failed':
        return 'Domain verification failed. Please check your DNS configuration.';
      case 'pending':
        return 'Waiting for DNS propagation. This usually takes 5-30 minutes.';
      default:
        return 'Domain verification has not been started.';
    }
  };

  return (
    <div className={`space-y-4 ${className}`}>
      {/* Main Status Display */}
      <div className={`border rounded-lg p-6 ${getStatusColor()}`}>
        <div className="flex items-start space-x-4">
          <div className="flex-shrink-0">
            {getStatusIcon()}
          </div>
          <div className="flex-1 min-w-0">
            <div className="flex items-center justify-between">
              <h3 className="text-lg font-medium text-gray-900">
                {getStatusText()}
              </h3>
              {status === 'pending' && (
                <div className="flex items-center text-sm text-yellow-600">
                  <ArrowPathIcon className="w-4 h-4 mr-1 animate-spin" />
                  Auto-checking...
                </div>
              )}
            </div>
            <p className="mt-1 text-sm text-gray-600">
              {getStatusDescription()}
            </p>
            
            {statusMessage && (
              <p className="mt-2 text-sm font-medium text-gray-800">
                {statusMessage}
              </p>
            )}

            {lastCheck && (
              <p className="mt-2 text-xs text-gray-500">
                Last checked: {new Date(lastCheck).toLocaleString()}
              </p>
            )}

            {/* Domain URL for active status */}
            {status === 'active' && (
              <div className="mt-3">
                <a
                  href={`http://${domain}`}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="inline-flex items-center text-sm font-medium text-blue-600 hover:text-blue-800"
                >
                  Visit your game at {domain}
                  <svg className="ml-1 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                  </svg>
                </a>
              </div>
            )}
          </div>
        </div>

        {/* Manual Verification Button */}
        {(status === 'pending' || status === 'failed') && (
          <div className="mt-4 flex justify-end">
            <button
              onClick={() => handleVerifyDomain(false)}
              disabled={isVerifying}
              className="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {isVerifying ? (
                <>
                  <ArrowPathIcon className="w-4 h-4 mr-2 animate-spin" />
                  Verifying...
                </>
              ) : (
                'Verify Now'
              )}
            </button>
          </div>
        )}
      </div>

      {/* DNS Information Display */}
      {(expectedIp || resolvedIp) && (
        <div className="bg-white border border-gray-200 rounded-lg p-4">
          <h4 className="text-sm font-medium text-gray-900 mb-3">DNS Resolution Details</h4>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            {expectedIp && (
              <div>
                <span className="font-medium text-gray-700">Expected IP:</span>
                <span className="ml-2 font-mono bg-gray-100 px-2 py-1 rounded">
                  {expectedIp}
                </span>
              </div>
            )}
            {resolvedIp && (
              <div>
                <span className="font-medium text-gray-700">Resolved IP:</span>
                <span className={`ml-2 font-mono px-2 py-1 rounded ${
                  resolvedIp === expectedIp 
                    ? 'bg-green-100 text-green-800' 
                    : 'bg-red-100 text-red-800'
                }`}>
                  {resolvedIp}
                </span>
              </div>
            )}
          </div>
        </div>
      )}

      {/* Latest Verification Result */}
      {verificationResult && (
        <div className={`border rounded-lg p-4 ${
          verificationResult.success 
            ? 'bg-green-50 border-green-200' 
            : 'bg-red-50 border-red-200'
        }`}>
          <div className="flex items-start space-x-3">
            {verificationResult.success ? (
              <CheckCircleIcon className="w-5 h-5 text-green-500 mt-0.5 flex-shrink-0" />
            ) : (
              <XCircleIcon className="w-5 h-5 text-red-500 mt-0.5 flex-shrink-0" />
            )}
            <div className="flex-1 min-w-0">
              <p className={`text-sm font-medium ${
                verificationResult.success ? 'text-green-800' : 'text-red-800'
              }`}>
                {verificationResult.success ? 'Verification Successful!' : 'Verification Failed'}
              </p>
              <p className={`mt-1 text-sm ${
                verificationResult.success ? 'text-green-700' : 'text-red-700'
              }`}>
                {verificationResult.message || verificationResult.error}
              </p>
              
              {verificationResult.expected_ip && verificationResult.resolved_ip && (
                <div className="mt-2 text-xs space-y-1">
                  <div>Expected: {verificationResult.expected_ip}</div>
                  <div>Resolved: {verificationResult.resolved_ip}</div>
                </div>
              )}

              {verificationResult.verified_at && (
                <p className="mt-2 text-xs text-green-600">
                  Verified at: {new Date(verificationResult.verified_at).toLocaleString()}
                </p>
              )}
            </div>
          </div>
        </div>
      )}

      {/* Troubleshooting Tips for Failed Status */}
      {status === 'failed' && (
        <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
          <div className="flex items-start space-x-3">
            <ExclamationTriangleIcon className="w-5 h-5 text-yellow-500 mt-0.5 flex-shrink-0" />
            <div className="flex-1 min-w-0">
              <h4 className="text-sm font-medium text-yellow-800 mb-2">
                Troubleshooting Tips
              </h4>
              <ul className="text-sm text-yellow-700 space-y-1 list-disc list-inside">
                <li>Verify the DNS A record points to the correct IP address</li>
                <li>Check if DNS changes have propagated using whatsmydns.net</li>
                <li>Ensure you're using "@" or blank for the record name</li>
                <li>Wait up to 48 hours for full DNS propagation</li>
                <li>Clear your local DNS cache and try again</li>
              </ul>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default DomainVerificationStatus;