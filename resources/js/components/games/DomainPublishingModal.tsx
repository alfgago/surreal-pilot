import React, { useState, useEffect } from 'react';
import { XMarkIcon } from '@heroicons/react/24/outline';
import DomainSetupWizard from './DomainSetupWizard';
import DNSConfigurationDisplay from './DNSConfigurationDisplay';
import DomainVerificationStatus from './DomainVerificationStatus';
import DomainTroubleshootingGuide from './DomainTroubleshootingGuide';
import { useDomainPublishing } from '../../hooks/useDomainPublishing';

interface DomainPublishingModalProps {
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
  isOpen: boolean;
  onClose: () => void;
  onDomainConfigured?: (result: any) => void;
}

type ViewMode = 'wizard' | 'status' | 'troubleshooting';

const DomainPublishingModal: React.FC<DomainPublishingModalProps> = ({
  game,
  isOpen,
  onClose,
  onDomainConfigured
}) => {
  const [currentView, setCurrentView] = useState<ViewMode>('wizard');
  const [gameData, setGameData] = useState(game);
  const { getDomainStatus } = useDomainPublishing();

  // Update view based on domain status
  useEffect(() => {
    if (gameData.custom_domain) {
      if (gameData.domain_status === 'active') {
        setCurrentView('status');
      } else if (gameData.domain_status === 'failed') {
        setCurrentView('troubleshooting');
      } else {
        setCurrentView('status'); // Show status for pending
      }
    } else {
      setCurrentView('wizard');
    }
  }, [gameData]);

  // Refresh domain status when modal opens
  useEffect(() => {
    if (isOpen && gameData.id) {
      refreshDomainStatus();
    }
  }, [isOpen]);

  const refreshDomainStatus = async () => {
    try {
      const result = await getDomainStatus(gameData.id);
      if (result.success && result.domain) {
        setGameData(prev => ({
          ...prev,
          custom_domain: result.domain?.custom_domain,
          domain_status: result.domain?.domain_status,
          domain_config: result.domain?.domain_config,
        }));
      }
    } catch (error) {
      console.error('Failed to refresh domain status:', error);
    }
  };

  const handleDomainConfigured = (result: any) => {
    // Update local game data
    if (result.domain) {
      setGameData(prev => ({
        ...prev,
        custom_domain: result.domain,
        domain_status: result.status || 'pending',
        domain_config: {
          server_ip: result.dns_instructions?.value,
          status_message: result.message,
          last_check: new Date().toISOString(),
        },
      }));
    }

    // Update view based on result
    if (result.success) {
      if (result.status === 'active') {
        setCurrentView('status');
      } else {
        setCurrentView('status'); // Show status for pending verification
      }
    }

    // Call parent callback
    if (onDomainConfigured) {
      onDomainConfigured(result);
    }
  };

  const handleVerificationComplete = (result: any) => {
    // Update local game data
    setGameData(prev => ({
      ...prev,
      domain_status: result.status,
      domain_config: {
        ...prev.domain_config,
        status_message: result.message,
        last_check: new Date().toISOString(),
      },
    }));

    // Update view based on verification result
    if (result.success) {
      setCurrentView('status');
    } else if (result.status === 'failed') {
      setCurrentView('troubleshooting');
    }

    // Call parent callback
    if (onDomainConfigured) {
      onDomainConfigured(result);
    }
  };

  const handleViewChange = (view: ViewMode) => {
    setCurrentView(view);
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
      <div className="relative top-10 mx-auto p-5 border w-full max-w-4xl shadow-lg rounded-md bg-white min-h-[600px]">
        <div className="flex items-center justify-between mb-6">
          <h2 className="text-xl font-semibold text-gray-900">
            Custom Domain Publishing
          </h2>
          <button
            onClick={onClose}
            className="text-gray-400 hover:text-gray-600 focus:outline-none"
          >
            <XMarkIcon className="w-6 h-6" />
          </button>
        </div>

        {/* Navigation Tabs */}
        <div className="border-b border-gray-200 mb-6">
          <nav className="-mb-px flex space-x-8">
            <button
              onClick={() => handleViewChange('wizard')}
              className={`py-2 px-1 border-b-2 font-medium text-sm ${
                currentView === 'wizard'
                  ? 'border-blue-500 text-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              }`}
            >
              Setup Wizard
            </button>
            <button
              onClick={() => handleViewChange('status')}
              className={`py-2 px-1 border-b-2 font-medium text-sm ${
                currentView === 'status'
                  ? 'border-blue-500 text-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              }`}
              disabled={!gameData.custom_domain}
            >
              Domain Status
            </button>
            <button
              onClick={() => handleViewChange('troubleshooting')}
              className={`py-2 px-1 border-b-2 font-medium text-sm ${
                currentView === 'troubleshooting'
                  ? 'border-blue-500 text-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              }`}
            >
              Troubleshooting
            </button>
          </nav>
        </div>

        {/* Content Area */}
        <div className="min-h-[400px]">
          {currentView === 'wizard' && (
            <DomainSetupWizard
              game={gameData}
              onClose={onClose}
              onDomainConfigured={handleDomainConfigured}
            />
          )}

          {currentView === 'status' && gameData.custom_domain && (
            <div className="space-y-6">
              <DomainVerificationStatus
                gameId={gameData.id}
                domain={gameData.custom_domain}
                status={gameData.domain_status || null}
                lastCheck={gameData.domain_config?.last_check}
                statusMessage={gameData.domain_config?.status_message}
                expectedIp={gameData.domain_config?.server_ip}
                onVerificationComplete={handleVerificationComplete}
              />

              {gameData.domain_config?.server_ip && (
                <DNSConfigurationDisplay
                  serverIp={gameData.domain_config.server_ip}
                  domain={gameData.custom_domain}
                  className="mt-6"
                />
              )}

              {(gameData.domain_status === 'failed' || gameData.domain_status === 'pending') && (
                <div className="mt-6">
                  <div className="flex items-center justify-between mb-4">
                    <h3 className="text-lg font-medium text-gray-900">
                      Need Help?
                    </h3>
                    <button
                      onClick={() => handleViewChange('troubleshooting')}
                      className="text-sm text-blue-600 hover:text-blue-800"
                    >
                      View Full Troubleshooting Guide →
                    </button>
                  </div>
                  <DomainTroubleshootingGuide
                    domain={gameData.custom_domain}
                    serverIp={gameData.domain_config?.server_ip}
                    currentIssue={gameData.domain_status === 'failed' ? 'verification failed' : undefined}
                    className="max-h-60 overflow-y-auto"
                  />
                </div>
              )}
            </div>
          )}

          {currentView === 'troubleshooting' && (
            <div className="space-y-6">
              <div className="flex items-center justify-between">
                <h3 className="text-lg font-medium text-gray-900">
                  Domain Setup Troubleshooting
                </h3>
                {gameData.custom_domain && (
                  <button
                    onClick={() => handleViewChange('status')}
                    className="text-sm text-blue-600 hover:text-blue-800"
                  >
                    ← Back to Domain Status
                  </button>
                )}
              </div>

              <DomainTroubleshootingGuide
                domain={gameData.custom_domain}
                serverIp={gameData.domain_config?.server_ip}
                currentIssue={gameData.domain_status === 'failed' ? 'verification failed' : undefined}
              />

              {gameData.custom_domain && gameData.domain_config?.server_ip && (
                <div className="mt-6">
                  <h4 className="text-md font-medium text-gray-900 mb-4">
                    Current DNS Configuration
                  </h4>
                  <DNSConfigurationDisplay
                    serverIp={gameData.domain_config.server_ip}
                    domain={gameData.custom_domain}
                  />
                </div>
              )}
            </div>
          )}
        </div>

        {/* Footer Actions */}
        <div className="mt-6 pt-4 border-t border-gray-200 flex justify-end space-x-3">
          {currentView === 'status' && gameData.custom_domain && gameData.domain_status === 'active' && (
            <a
              href={`http://${gameData.custom_domain}`}
              target="_blank"
              rel="noopener noreferrer"
              className="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-green-600 border border-transparent rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500"
            >
              Visit Your Game
              <svg className="ml-2 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
              </svg>
            </a>
          )}
          <button
            onClick={onClose}
            className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
          >
            Close
          </button>
        </div>
      </div>
    </div>
  );
};

export default DomainPublishingModal;