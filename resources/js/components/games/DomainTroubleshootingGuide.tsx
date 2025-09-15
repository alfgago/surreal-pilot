import React, { useState } from 'react';
import { 
  ChevronDownIcon, 
  ChevronRightIcon,
  ExclamationTriangleIcon,
  InformationCircleIcon,
  CheckCircleIcon,
  ClockIcon
} from '@heroicons/react/24/outline';

interface TroubleshootingStep {
  title: string;
  description: string;
  steps: string[];
  tools?: Array<{
    name: string;
    url: string;
    description: string;
  }>;
}

interface DomainTroubleshootingGuideProps {
  domain?: string;
  serverIp?: string;
  currentIssue?: string;
  troubleshootingData?: Record<string, string[]>;
  className?: string;
}

const DomainTroubleshootingGuide: React.FC<DomainTroubleshootingGuideProps> = ({
  domain,
  serverIp = '127.0.0.1',
  currentIssue,
  troubleshootingData,
  className = ''
}) => {
  const [expandedSections, setExpandedSections] = useState<Set<string>>(new Set());

  const toggleSection = (sectionId: string) => {
    const newExpanded = new Set(expandedSections);
    if (newExpanded.has(sectionId)) {
      newExpanded.delete(sectionId);
    } else {
      newExpanded.add(sectionId);
    }
    setExpandedSections(newExpanded);
  };

  const defaultTroubleshootingSteps: TroubleshootingStep[] = [
    {
      title: 'DNS Propagation Issues',
      description: 'DNS changes can take time to propagate globally. This is the most common cause of verification failures.',
      steps: [
        'Wait 5-30 minutes after making DNS changes',
        'Check DNS propagation status using online tools',
        'Clear your local DNS cache',
        'Try accessing from a different network or device',
        'Wait up to 48 hours for full global propagation'
      ],
      tools: [
        {
          name: 'whatsmydns.net',
          url: 'https://www.whatsmydns.net',
          description: 'Check DNS propagation globally'
        },
        {
          name: 'dnschecker.org',
          url: 'https://dnschecker.org',
          description: 'Verify DNS records worldwide'
        }
      ]
    },
    {
      title: 'Incorrect DNS Configuration',
      description: 'The DNS record may not be configured correctly or may be pointing to the wrong IP address.',
      steps: [
        `Verify the A record points to ${serverIp}`,
        'Ensure the record name is "@" or blank (not "www")',
        'Check that the record type is "A" (not CNAME or other)',
        'Verify TTL is set to 300 seconds or lower',
        'Remove any conflicting DNS records for the same domain',
        'Save changes and wait for propagation'
      ]
    },
    {
      title: 'Domain Registrar Issues',
      description: 'Some domain registrars have specific requirements or delays for DNS changes.',
      steps: [
        'Confirm you have administrative access to modify DNS records',
        'Check if your registrar requires additional verification',
        'Look for any pending changes or approval processes',
        'Verify that DNS management is not locked or restricted',
        'Contact your registrar support if changes are not saving',
        'Consider using a third-party DNS service like Cloudflare'
      ]
    },
    {
      title: 'Network and Firewall Issues',
      description: 'Network configuration or firewall settings may be blocking access to your domain.',
      steps: [
        'Ensure port 80 (HTTP) is open on the server',
        'Check if your ISP blocks certain domains or ports',
        'Verify there are no firewall rules blocking the domain',
        'Test from different networks (mobile data, different ISP)',
        'Check if the domain is blacklisted or filtered',
        'Try using a VPN to test from different locations'
      ]
    },
    {
      title: 'Domain Name Issues',
      description: 'Problems with the domain name itself or its configuration.',
      steps: [
        'Verify the domain name is spelled correctly',
        'Check that the domain is not expired or suspended',
        'Ensure the domain is not in a redemption or pending delete status',
        'Verify domain ownership and registration details',
        'Check if the domain has any legal or administrative holds',
        'Confirm the domain supports A record configuration'
      ]
    },
    {
      title: 'Cache and Browser Issues',
      description: 'Local caching may prevent you from seeing DNS changes immediately.',
      steps: [
        'Clear your browser cache and cookies',
        'Flush your local DNS cache (ipconfig /flushdns on Windows)',
        'Try accessing the domain in an incognito/private browser window',
        'Test from a different device or browser',
        'Restart your router/modem to refresh DNS cache',
        'Use a different DNS server (8.8.8.8 or 1.1.1.1)'
      ]
    }
  ];

  // Merge custom troubleshooting data with defaults
  const troubleshootingSteps = defaultTroubleshootingSteps.map(step => {
    if (troubleshootingData && troubleshootingData[step.title]) {
      return {
        ...step,
        steps: troubleshootingData[step.title]
      };
    }
    return step;
  });

  const getSectionIcon = (title: string) => {
    if (currentIssue && title.toLowerCase().includes(currentIssue.toLowerCase())) {
      return <ExclamationTriangleIcon className="w-5 h-5 text-red-500" />;
    }
    return <InformationCircleIcon className="w-5 h-5 text-blue-500" />;
  };

  const getSectionBorder = (title: string) => {
    if (currentIssue && title.toLowerCase().includes(currentIssue.toLowerCase())) {
      return 'border-red-200 bg-red-50';
    }
    return 'border-gray-200 bg-white';
  };

  return (
    <div className={`space-y-4 ${className}`}>
      <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div className="flex items-start space-x-3">
          <InformationCircleIcon className="w-5 h-5 text-blue-500 mt-0.5 flex-shrink-0" />
          <div className="flex-1 min-w-0">
            <h3 className="text-sm font-medium text-blue-900 mb-1">
              Domain Setup Troubleshooting
            </h3>
            <p className="text-sm text-blue-800">
              If your domain verification is failing, try the solutions below. Most issues are resolved within 30 minutes.
            </p>
            {domain && (
              <p className="text-sm text-blue-700 mt-2">
                <strong>Domain:</strong> {domain} → <strong>IP:</strong> {serverIp}
              </p>
            )}
          </div>
        </div>
      </div>

      <div className="space-y-3">
        {troubleshootingSteps.map((step, index) => (
          <div
            key={index}
            className={`border rounded-lg ${getSectionBorder(step.title)}`}
          >
            <button
              onClick={() => toggleSection(`step-${index}`)}
              className="w-full px-4 py-3 text-left flex items-center justify-between hover:bg-gray-50 focus:outline-none focus:bg-gray-50"
            >
              <div className="flex items-center space-x-3">
                {getSectionIcon(step.title)}
                <div>
                  <h4 className="text-sm font-medium text-gray-900">
                    {step.title}
                  </h4>
                  <p className="text-xs text-gray-600 mt-1">
                    {step.description}
                  </p>
                </div>
              </div>
              {expandedSections.has(`step-${index}`) ? (
                <ChevronDownIcon className="w-5 h-5 text-gray-400" />
              ) : (
                <ChevronRightIcon className="w-5 h-5 text-gray-400" />
              )}
            </button>

            {expandedSections.has(`step-${index}`) && (
              <div className="px-4 pb-4 border-t border-gray-200">
                <div className="mt-3 space-y-3">
                  <div>
                    <h5 className="text-sm font-medium text-gray-900 mb-2">
                      Steps to resolve:
                    </h5>
                    <ol className="list-decimal list-inside space-y-1 text-sm text-gray-700">
                      {step.steps.map((stepItem, stepIndex) => (
                        <li key={stepIndex} className="leading-relaxed">
                          {stepItem}
                        </li>
                      ))}
                    </ol>
                  </div>

                  {step.tools && step.tools.length > 0 && (
                    <div>
                      <h5 className="text-sm font-medium text-gray-900 mb-2">
                        Helpful tools:
                      </h5>
                      <div className="space-y-2">
                        {step.tools.map((tool, toolIndex) => (
                          <div key={toolIndex} className="flex items-center justify-between p-2 bg-gray-50 rounded-md">
                            <div>
                              <a
                                href={tool.url}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="text-sm font-medium text-blue-600 hover:text-blue-800"
                              >
                                {tool.name}
                              </a>
                              <p className="text-xs text-gray-600">{tool.description}</p>
                            </div>
                            <svg className="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                          </div>
                        ))}
                      </div>
                    </div>
                  )}
                </div>
              </div>
            )}
          </div>
        ))}
      </div>

      {/* Quick Actions */}
      <div className="bg-gray-50 border border-gray-200 rounded-lg p-4">
        <h3 className="text-sm font-medium text-gray-900 mb-3">Quick Actions</h3>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
          <a
            href="https://www.whatsmydns.net"
            target="_blank"
            rel="noopener noreferrer"
            className="flex items-center justify-between p-3 bg-white border border-gray-200 rounded-md hover:bg-gray-50"
          >
            <div>
              <span className="text-sm font-medium text-gray-900">Check DNS Propagation</span>
              <p className="text-xs text-gray-600">See if your DNS changes have propagated</p>
            </div>
            <svg className="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
            </svg>
          </a>
          
          <a
            href="https://dnschecker.org"
            target="_blank"
            rel="noopener noreferrer"
            className="flex items-center justify-between p-3 bg-white border border-gray-200 rounded-md hover:bg-gray-50"
          >
            <div>
              <span className="text-sm font-medium text-gray-900">DNS Checker</span>
              <p className="text-xs text-gray-600">Verify DNS records worldwide</p>
            </div>
            <svg className="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
            </svg>
          </a>
        </div>
      </div>

      {/* Timeline Expectations */}
      <div className="bg-green-50 border border-green-200 rounded-lg p-4">
        <div className="flex items-start space-x-3">
          <ClockIcon className="w-5 h-5 text-green-500 mt-0.5 flex-shrink-0" />
          <div className="flex-1 min-w-0">
            <h3 className="text-sm font-medium text-green-900 mb-2">
              Expected Timeline
            </h3>
            <div className="space-y-2 text-sm text-green-800">
              <div className="flex items-center space-x-2">
                <CheckCircleIcon className="w-4 h-4 text-green-600" />
                <span><strong>0-5 minutes:</strong> DNS record saved at registrar</span>
              </div>
              <div className="flex items-center space-x-2">
                <CheckCircleIcon className="w-4 h-4 text-green-600" />
                <span><strong>5-30 minutes:</strong> DNS propagation begins</span>
              </div>
              <div className="flex items-center space-x-2">
                <CheckCircleIcon className="w-4 h-4 text-green-600" />
                <span><strong>30 minutes - 2 hours:</strong> Most locations updated</span>
              </div>
              <div className="flex items-center space-x-2">
                <ClockIcon className="w-4 h-4 text-green-600" />
                <span><strong>Up to 48 hours:</strong> Full global propagation</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default DomainTroubleshootingGuide;