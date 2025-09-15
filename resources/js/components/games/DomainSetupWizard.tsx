import React, { useState, useEffect } from 'react';
import { CheckCircleIcon, ExclamationTriangleIcon, ClockIcon, XCircleIcon } from '@heroicons/react/24/outline';
import { usePage } from '@inertiajs/react';

interface DomainSetupWizardProps {
  game: {
    id: number;
    title: string;
    custom_domain?: string;
    domain_status?: 'pending' | 'active' | 'failed';
    domain_config?: {
      server_ip?: string;
      status_message?: string;
      last_check?: string;
      ssl_enabled?: boolean;
    };
  };
  onClose: () => void;
  onDomainConfigured: (result: any) => void;
}

interface DNSInstructions {
  type: string;
  name: string;
  value: string;
  ttl: number;
  instructions: string[];
  common_providers: Record<string, string>;
}

interface SetupResult {
  success: boolean;
  domain?: string;
  status?: string;
  dns_instructions?: DNSInstructions;
  verification_url?: string;
  estimated_propagation_time?: string;
  error?: string;
  troubleshooting?: Record<string, string[]>;
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

const DomainSetupWizard: React.FC<DomainSetupWizardProps> = ({
  game,
  onClose,
  onDomainConfigured
}) => {
  const [currentStep, setCurrentStep] = useState(1);
  const [domain, setDomain] = useState(game.custom_domain || '');
  const [isLoading, setIsLoading] = useState(false);
  const [setupResult, setSetupResult] = useState<SetupResult | null>(null);
  const [verificationResult, setVerificationResult] = useState<VerificationResult | null>(null);
  const [isVerifying, setIsVerifying] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Initialize step based on existing domain configuration
  useEffect(() => {
    if (game.custom_domain) {
      if (game.domain_status === 'active') {
        setCurrentStep(4); // Domain is active
      } else if (game.domain_status === 'pending') {
        setCurrentStep(3); // Waiting for verification
      } else if (game.domain_status === 'failed') {
        setCurrentStep(3); // Show verification with error
      }
    }
  }, [game]);

  const handleDomainSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!domain.trim()) return;

    setIsLoading(true);
    setError(null);

    try {
      const response = await fetch(`/api/games/${game.id}/domain`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
        body: JSON.stringify({ domain: domain.trim() }),
      });

      const result: SetupResult = await response.json();

      if (result.success) {
        setSetupResult(result);
        setCurrentStep(2);
      } else {
        setError(result.error || 'Failed to setup domain');
      }
    } catch (err) {
      setError('Network error occurred. Please try again.');
    } finally {
      setIsLoading(false);
    }
  };

  const handleVerifyDomain = async () => {
    setIsVerifying(true);
    setError(null);

    try {
      const response = await fetch(`/api/games/${game.id}/domain/verify`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
      });

      const result: VerificationResult = await response.json();
      setVerificationResult(result);

      if (result.success) {
        setCurrentStep(4);
        onDomainConfigured(result);
      }
    } catch (err) {
      setError('Network error occurred during verification.');
    } finally {
      setIsVerifying(false);
    }
  };

  const handleRemoveDomain = async () => {
    setIsLoading(true);
    setError(null);

    try {
      const response = await fetch(`/api/games/${game.id}/domain`, {
        method: 'DELETE',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
      });

      const result = await response.json();

      if (result.success) {
        onDomainConfigured({ success: true, removed: true });
        onClose();
      } else {
        setError(result.error || 'Failed to remove domain');
      }
    } catch (err) {
      setError('Network error occurred. Please try again.');
    } finally {
      setIsLoading(false);
    }
  };

  const getStepStatus = (step: number) => {
    if (step < currentStep) return 'completed';
    if (step === currentStep) return 'current';
    return 'upcoming';
  };

  const getStatusIcon = (status: string) => {
    switch (status) {
      case 'completed':
        return <CheckCircleIcon className="w-5 h-5 text-green-500" />;
      case 'current':
        return <ClockIcon className="w-5 h-5 text-blue-500" />;
      case 'failed':
        return <XCircleIcon className="w-5 h-5 text-red-500" />;
      default:
        return <div className="w-5 h-5 rounded-full border-2 border-gray-300" />;
    }
  };

  const renderStepIndicator = () => (
    <div className="flex items-center justify-between mb-8">
      {[1, 2, 3, 4].map((step, index) => (
        <React.Fragment key={step}>
          <div className="flex flex-col items-center">
            <div className={`flex items-center justify-center w-10 h-10 rounded-full border-2 ${
              getStepStatus(step) === 'completed' ? 'bg-green-500 border-green-500' :
              getStepStatus(step) === 'current' ? 'bg-blue-500 border-blue-500' :
              'bg-gray-100 border-gray-300'
            }`}>
              {getStepStatus(step) === 'completed' ? (
                <CheckCircleIcon className="w-6 h-6 text-white" />
              ) : (
                <span className={`text-sm font-medium ${
                  getStepStatus(step) === 'current' ? 'text-white' : 'text-gray-500'
                }`}>
                  {step}
                </span>
              )}
            </div>
            <span className="mt-2 text-xs text-gray-600">
              {step === 1 && 'Domain'}
              {step === 2 && 'DNS Setup'}
              {step === 3 && 'Verification'}
              {step === 4 && 'Complete'}
            </span>
          </div>
          {index < 3 && (
            <div className={`flex-1 h-0.5 mx-4 ${
              getStepStatus(step + 1) === 'completed' || getStepStatus(step + 1) === 'current'
                ? 'bg-blue-500'
                : 'bg-gray-300'
            }`} />
          )}
        </React.Fragment>
      ))}
    </div>
  );

  const renderStep1 = () => (
    <div className="space-y-6">
      <div>
        <h3 className="text-lg font-medium text-gray-900 mb-2">
          Enter Your Custom Domain
        </h3>
        <p className="text-sm text-gray-600 mb-4">
          Enter the domain where you want your game "{game.title}" to be accessible.
        </p>
      </div>

      <form onSubmit={handleDomainSubmit} className="space-y-4">
        <div>
          <label htmlFor="domain" className="block text-sm font-medium text-gray-700 mb-2">
            Domain Name
          </label>
          <input
            type="text"
            id="domain"
            value={domain}
            onChange={(e) => setDomain(e.target.value)}
            placeholder="example.com"
            className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
            required
          />
          <p className="mt-1 text-xs text-gray-500">
            Enter your domain without "http://" or "www" (e.g., "mygame.com")
          </p>
        </div>

        <div className="bg-blue-50 border border-blue-200 rounded-md p-4">
          <div className="flex">
            <ExclamationTriangleIcon className="w-5 h-5 text-blue-400 mt-0.5 mr-3 flex-shrink-0" />
            <div className="text-sm text-blue-800">
              <p className="font-medium mb-1">Before proceeding:</p>
              <ul className="list-disc list-inside space-y-1">
                <li>Make sure you own this domain</li>
                <li>Have access to your domain's DNS settings</li>
                <li>The domain is not currently in use by another service</li>
              </ul>
            </div>
          </div>
        </div>

        {error && (
          <div className="bg-red-50 border border-red-200 rounded-md p-4">
            <div className="flex">
              <XCircleIcon className="w-5 h-5 text-red-400 mt-0.5 mr-3 flex-shrink-0" />
              <p className="text-sm text-red-800">{error}</p>
            </div>
          </div>
        )}

        <div className="flex justify-end space-x-3">
          <button
            type="button"
            onClick={onClose}
            className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
          >
            Cancel
          </button>
          <button
            type="submit"
            disabled={isLoading || !domain.trim()}
            className="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {isLoading ? 'Setting up...' : 'Continue'}
          </button>
        </div>
      </form>
    </div>
  );

  const renderStep2 = () => {
    if (!setupResult?.dns_instructions) return null;

    const { dns_instructions } = setupResult;

    return (
      <div className="space-y-6">
        <div>
          <h3 className="text-lg font-medium text-gray-900 mb-2">
            Configure DNS Settings
          </h3>
          <p className="text-sm text-gray-600 mb-4">
            Add the following DNS record to your domain registrar to point your domain to our servers.
          </p>
        </div>

        <div className="bg-gray-50 border border-gray-200 rounded-lg p-6">
          <h4 className="font-medium text-gray-900 mb-4">DNS Record Details</h4>
          <div className="grid grid-cols-2 gap-4 text-sm">
            <div>
              <span className="font-medium text-gray-700">Type:</span>
              <span className="ml-2 font-mono bg-white px-2 py-1 rounded border">
                {dns_instructions.type}
              </span>
            </div>
            <div>
              <span className="font-medium text-gray-700">Name:</span>
              <span className="ml-2 font-mono bg-white px-2 py-1 rounded border">
                {dns_instructions.name}
              </span>
            </div>
            <div>
              <span className="font-medium text-gray-700">Value:</span>
              <span className="ml-2 font-mono bg-white px-2 py-1 rounded border">
                {dns_instructions.value}
              </span>
            </div>
            <div>
              <span className="font-medium text-gray-700">TTL:</span>
              <span className="ml-2 font-mono bg-white px-2 py-1 rounded border">
                {dns_instructions.ttl}
              </span>
            </div>
          </div>
        </div>

        <div className="space-y-4">
          <h4 className="font-medium text-gray-900">Step-by-Step Instructions</h4>
          <ol className="list-decimal list-inside space-y-2 text-sm text-gray-700">
            {dns_instructions.instructions.map((instruction, index) => (
              <li key={index}>{instruction}</li>
            ))}
          </ol>
        </div>

        <div className="space-y-3">
          <h4 className="font-medium text-gray-900">Common DNS Providers</h4>
          <div className="grid grid-cols-2 gap-3 text-sm">
            {Object.entries(dns_instructions.common_providers).map(([provider, path]) => (
              <div key={provider} className="flex justify-between items-center p-3 bg-gray-50 rounded-md">
                <span className="font-medium">{provider}</span>
                <span className="text-gray-600">{path}</span>
              </div>
            ))}
          </div>
        </div>

        <div className="bg-yellow-50 border border-yellow-200 rounded-md p-4">
          <div className="flex">
            <ClockIcon className="w-5 h-5 text-yellow-400 mt-0.5 mr-3 flex-shrink-0" />
            <div className="text-sm text-yellow-800">
              <p className="font-medium mb-1">DNS Propagation Time</p>
              <p>
                DNS changes typically take {setupResult.estimated_propagation_time} to propagate globally.
                You can proceed to verification once you've added the DNS record.
              </p>
            </div>
          </div>
        </div>

        <div className="flex justify-end space-x-3">
          <button
            type="button"
            onClick={() => setCurrentStep(1)}
            className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
          >
            Back
          </button>
          <button
            type="button"
            onClick={() => setCurrentStep(3)}
            className="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
          >
            Continue to Verification
          </button>
        </div>
      </div>
    );
  };

  const renderStep3 = () => (
    <div className="space-y-6">
      <div>
        <h3 className="text-lg font-medium text-gray-900 mb-2">
          Verify Domain Configuration
        </h3>
        <p className="text-sm text-gray-600 mb-4">
          Click "Verify Domain" to check if your DNS configuration is working correctly.
        </p>
      </div>

      {verificationResult && (
        <div className={`border rounded-md p-4 ${
          verificationResult.success 
            ? 'bg-green-50 border-green-200' 
            : 'bg-red-50 border-red-200'
        }`}>
          <div className="flex">
            {verificationResult.success ? (
              <CheckCircleIcon className="w-5 h-5 text-green-400 mt-0.5 mr-3 flex-shrink-0" />
            ) : (
              <XCircleIcon className="w-5 h-5 text-red-400 mt-0.5 mr-3 flex-shrink-0" />
            )}
            <div className="text-sm">
              <p className={`font-medium mb-1 ${
                verificationResult.success ? 'text-green-800' : 'text-red-800'
              }`}>
                {verificationResult.success ? 'Domain Verified Successfully!' : 'Verification Failed'}
              </p>
              <p className={verificationResult.success ? 'text-green-700' : 'text-red-700'}>
                {verificationResult.message}
              </p>
              {verificationResult.expected_ip && verificationResult.resolved_ip && (
                <div className="mt-2 text-xs">
                  <p>Expected IP: {verificationResult.expected_ip}</p>
                  <p>Resolved IP: {verificationResult.resolved_ip}</p>
                </div>
              )}
            </div>
          </div>
        </div>
      )}

      {game.domain_status === 'failed' && game.domain_config?.status_message && (
        <div className="bg-red-50 border border-red-200 rounded-md p-4">
          <div className="flex">
            <XCircleIcon className="w-5 h-5 text-red-400 mt-0.5 mr-3 flex-shrink-0" />
            <div className="text-sm text-red-800">
              <p className="font-medium mb-1">Previous Verification Failed</p>
              <p>{game.domain_config.status_message}</p>
              {game.domain_config.last_check && (
                <p className="text-xs mt-1">
                  Last checked: {new Date(game.domain_config.last_check).toLocaleString()}
                </p>
              )}
            </div>
          </div>
        </div>
      )}

      {error && (
        <div className="bg-red-50 border border-red-200 rounded-md p-4">
          <div className="flex">
            <XCircleIcon className="w-5 h-5 text-red-400 mt-0.5 mr-3 flex-shrink-0" />
            <p className="text-sm text-red-800">{error}</p>
          </div>
        </div>
      )}

      <div className="flex justify-end space-x-3">
        <button
          type="button"
          onClick={() => setCurrentStep(2)}
          className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
        >
          Back to DNS Setup
        </button>
        <button
          type="button"
          onClick={handleVerifyDomain}
          disabled={isVerifying}
          className="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {isVerifying ? 'Verifying...' : 'Verify Domain'}
        </button>
      </div>
    </div>
  );

  const renderStep4 = () => (
    <div className="space-y-6">
      <div className="text-center">
        <CheckCircleIcon className="w-16 h-16 text-green-500 mx-auto mb-4" />
        <h3 className="text-lg font-medium text-gray-900 mb-2">
          Domain Successfully Configured!
        </h3>
        <p className="text-sm text-gray-600 mb-4">
          Your game "{game.title}" is now accessible at your custom domain.
        </p>
      </div>

      <div className="bg-green-50 border border-green-200 rounded-lg p-6">
        <h4 className="font-medium text-green-900 mb-3">Your Game is Live!</h4>
        <div className="space-y-2">
          <div className="flex items-center justify-between">
            <span className="text-sm text-green-800">Domain:</span>
            <a
              href={game.custom_domain ? `http://${game.custom_domain}` : '#'}
              target="_blank"
              rel="noopener noreferrer"
              className="text-sm font-mono text-blue-600 hover:text-blue-800 underline"
            >
              {game.custom_domain}
            </a>
          </div>
          {verificationResult?.verified_at && (
            <div className="flex items-center justify-between">
              <span className="text-sm text-green-800">Verified:</span>
              <span className="text-sm text-green-700">
                {new Date(verificationResult.verified_at).toLocaleString()}
              </span>
            </div>
          )}
        </div>
      </div>

      <div className="bg-blue-50 border border-blue-200 rounded-md p-4">
        <div className="flex">
          <ExclamationTriangleIcon className="w-5 h-5 text-blue-400 mt-0.5 mr-3 flex-shrink-0" />
          <div className="text-sm text-blue-800">
            <p className="font-medium mb-1">Next Steps</p>
            <ul className="list-disc list-inside space-y-1">
              <li>Test your game at the custom domain</li>
              <li>Consider enabling SSL/HTTPS for security</li>
              <li>Share your custom domain with others</li>
            </ul>
          </div>
        </div>
      </div>

      <div className="flex justify-end space-x-3">
        <button
          type="button"
          onClick={handleRemoveDomain}
          disabled={isLoading}
          className="px-4 py-2 text-sm font-medium text-red-700 bg-white border border-red-300 rounded-md hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {isLoading ? 'Removing...' : 'Remove Domain'}
        </button>
        <button
          type="button"
          onClick={onClose}
          className="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
        >
          Done
        </button>
      </div>
    </div>
  );

  return (
    <div className="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
      <div className="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white">
        <div className="mt-3">
          <div className="flex items-center justify-between mb-6">
            <h2 className="text-xl font-semibold text-gray-900">
              Custom Domain Setup
            </h2>
            <button
              onClick={onClose}
              className="text-gray-400 hover:text-gray-600 focus:outline-none"
            >
              <XCircleIcon className="w-6 h-6" />
            </button>
          </div>

          {renderStepIndicator()}

          <div className="mt-6">
            {currentStep === 1 && renderStep1()}
            {currentStep === 2 && renderStep2()}
            {currentStep === 3 && renderStep3()}
            {currentStep === 4 && renderStep4()}
          </div>
        </div>
      </div>
    </div>
  );
};

export default DomainSetupWizard;