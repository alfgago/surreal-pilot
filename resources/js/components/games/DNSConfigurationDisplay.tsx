import React, { useState } from 'react';
import { ClipboardDocumentIcon, CheckIcon, ExclamationTriangleIcon } from '@heroicons/react/24/outline';

interface DNSRecord {
  type: string;
  name: string;
  value: string;
  ttl: number;
}

interface DNSConfigurationDisplayProps {
  serverIp: string;
  domain: string;
  dnsRecord?: DNSRecord;
  instructions?: string[];
  commonProviders?: Record<string, string>;
  className?: string;
}

const DNSConfigurationDisplay: React.FC<DNSConfigurationDisplayProps> = ({
  serverIp,
  domain,
  dnsRecord,
  instructions,
  commonProviders,
  className = ''
}) => {
  const [copiedField, setCopiedField] = useState<string | null>(null);

  const defaultRecord: DNSRecord = {
    type: 'A',
    name: '@',
    value: serverIp,
    ttl: 300
  };

  const record = dnsRecord || defaultRecord;

  const defaultInstructions = [
    "1. Log into your domain registrar's control panel",
    "2. Navigate to DNS management or DNS settings",
    "3. Create a new A record with the following details:",
    `   - Type: ${record.type}`,
    `   - Name: ${record.name} (or leave blank for root domain)`,
    `   - Value/Points to: ${record.value}`,
    `   - TTL: ${record.ttl} seconds (5 minutes)`,
    "4. Save the DNS record",
    "5. Wait 5-30 minutes for DNS propagation",
    "6. Click 'Verify Domain' to check if setup is complete"
  ];

  const defaultProviders = {
    'Cloudflare': 'DNS > Records > Add record',
    'GoDaddy': 'DNS Management > Add Record',
    'Namecheap': 'Advanced DNS > Add New Record',
    'Google Domains': 'DNS > Custom records',
    'Route 53': 'Hosted zones > Create record',
    'DigitalOcean': 'Networking > Domains > Add record'
  };

  const copyToClipboard = async (text: string, field: string) => {
    try {
      await navigator.clipboard.writeText(text);
      setCopiedField(field);
      setTimeout(() => setCopiedField(null), 2000);
    } catch (err) {
      console.error('Failed to copy text: ', err);
    }
  };

  const CopyButton: React.FC<{ text: string; field: string }> = ({ text, field }) => (
    <button
      onClick={() => copyToClipboard(text, field)}
      className="ml-2 p-1 text-gray-400 hover:text-gray-600 focus:outline-none focus:text-gray-600"
      title="Copy to clipboard"
    >
      {copiedField === field ? (
        <CheckIcon className="w-4 h-4 text-green-500" />
      ) : (
        <ClipboardDocumentIcon className="w-4 h-4" />
      )}
    </button>
  );

  return (
    <div className={`space-y-6 ${className}`}>
      {/* DNS Record Details */}
      <div className="bg-gray-50 border border-gray-200 rounded-lg p-6">
        <div className="flex items-center justify-between mb-4">
          <h3 className="text-lg font-medium text-gray-900">DNS Record Configuration</h3>
          <div className="text-sm text-gray-500">
            Domain: <span className="font-mono font-medium">{domain}</span>
          </div>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div className="space-y-3">
            <div className="flex items-center">
              <div className="min-w-0 flex-1">
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Record Type
                </label>
                <div className="flex items-center">
                  <code className="px-3 py-2 bg-white border border-gray-300 rounded-md text-sm font-mono">
                    {record.type}
                  </code>
                  <CopyButton text={record.type} field="type" />
                </div>
              </div>
            </div>

            <div className="flex items-center">
              <div className="min-w-0 flex-1">
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Name/Host
                </label>
                <div className="flex items-center">
                  <code className="px-3 py-2 bg-white border border-gray-300 rounded-md text-sm font-mono">
                    {record.name}
                  </code>
                  <CopyButton text={record.name} field="name" />
                </div>
                <p className="text-xs text-gray-500 mt-1">
                  Use "@" or leave blank for root domain
                </p>
              </div>
            </div>
          </div>

          <div className="space-y-3">
            <div className="flex items-center">
              <div className="min-w-0 flex-1">
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Value/Points to
                </label>
                <div className="flex items-center">
                  <code className="px-3 py-2 bg-white border border-gray-300 rounded-md text-sm font-mono">
                    {record.value}
                  </code>
                  <CopyButton text={record.value} field="value" />
                </div>
                <p className="text-xs text-gray-500 mt-1">
                  Server IP address
                </p>
              </div>
            </div>

            <div className="flex items-center">
              <div className="min-w-0 flex-1">
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  TTL (seconds)
                </label>
                <div className="flex items-center">
                  <code className="px-3 py-2 bg-white border border-gray-300 rounded-md text-sm font-mono">
                    {record.ttl}
                  </code>
                  <CopyButton text={record.ttl.toString()} field="ttl" />
                </div>
                <p className="text-xs text-gray-500 mt-1">
                  Time to live (5 minutes recommended)
                </p>
              </div>
            </div>
          </div>
        </div>

        <div className="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-md">
          <div className="flex">
            <ExclamationTriangleIcon className="w-5 h-5 text-blue-400 mt-0.5 mr-3 flex-shrink-0" />
            <div className="text-sm text-blue-800">
              <p className="font-medium mb-1">Important Notes:</p>
              <ul className="list-disc list-inside space-y-1">
                <li>Make sure to use the exact IP address: <code className="bg-white px-1 rounded">{serverIp}</code></li>
                <li>Use "@" for the name field (or leave blank) to configure the root domain</li>
                <li>Set TTL to 300 seconds for faster DNS updates during setup</li>
                <li>Remove any existing A records for the same domain to avoid conflicts</li>
              </ul>
            </div>
          </div>
        </div>
      </div>

      {/* Step-by-Step Instructions */}
      <div className="bg-white border border-gray-200 rounded-lg p-6">
        <h3 className="text-lg font-medium text-gray-900 mb-4">Setup Instructions</h3>
        <ol className="space-y-2">
          {(instructions || defaultInstructions).map((instruction, index) => (
            <li key={index} className="flex items-start">
              <span className="flex-shrink-0 w-6 h-6 bg-blue-100 text-blue-800 text-xs font-medium rounded-full flex items-center justify-center mr-3 mt-0.5">
                {index + 1}
              </span>
              <span className="text-sm text-gray-700 leading-relaxed">
                {instruction.replace(/^\d+\.\s*/, '')}
              </span>
            </li>
          ))}
        </ol>
      </div>

      {/* Common DNS Providers */}
      <div className="bg-white border border-gray-200 rounded-lg p-6">
        <h3 className="text-lg font-medium text-gray-900 mb-4">Common DNS Providers</h3>
        <p className="text-sm text-gray-600 mb-4">
          Here's where to find DNS settings for popular domain registrars and DNS providers:
        </p>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
          {Object.entries(commonProviders || defaultProviders).map(([provider, path]) => (
            <div key={provider} className="flex items-center justify-between p-3 bg-gray-50 rounded-md">
              <span className="font-medium text-gray-900">{provider}</span>
              <span className="text-sm text-gray-600">{path}</span>
            </div>
          ))}
        </div>
      </div>

      {/* Propagation Information */}
      <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-6">
        <h3 className="text-lg font-medium text-yellow-900 mb-2">DNS Propagation</h3>
        <div className="text-sm text-yellow-800 space-y-2">
          <p>
            After adding the DNS record, it may take some time for the changes to propagate globally:
          </p>
          <ul className="list-disc list-inside space-y-1 ml-4">
            <li><strong>Typical time:</strong> 5-30 minutes</li>
            <li><strong>Maximum time:</strong> Up to 48 hours (rare)</li>
            <li><strong>Check propagation:</strong> Use tools like whatsmydns.net or dnschecker.org</li>
          </ul>
          <p className="mt-3">
            You can proceed to domain verification once you've added the DNS record. 
            The system will automatically check if the configuration is working.
          </p>
        </div>
      </div>
    </div>
  );
};

export default DNSConfigurationDisplay;