<?php

namespace App\Services;

use App\Models\Game;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class DomainPublishingService
{
    public function __construct(
        private GameStorageService $gameStorageService
    ) {}

    /**
     * Setup custom domain for a game.
     */
    public function setupCustomDomain(Game $game, string $domain): array
    {
        try {
            // Validate domain format
            $this->validateDomain($domain);

            // Check if domain is already in use
            $existingGame = Game::where('custom_domain', $domain)
                ->where('id', '!=', $game->id)
                ->first();

            if ($existingGame) {
                throw new InvalidArgumentException("Domain {$domain} is already in use by another game.");
            }

            // Generate virtual host configuration
            $vhostConfig = $this->generateVirtualHostConfig($game, $domain);

            // Update game with domain information
            $game->update([
                'custom_domain' => $domain,
                'domain_status' => 'pending',
                'domain_config' => [
                    'ssl_enabled' => false,
                    'vhost_config' => $vhostConfig,
                    'setup_date' => now()->toISOString(),
                    'server_ip' => $this->getServerIp(),
                ]
            ]);

            // Generate DNS instructions
            $dnsInstructions = $this->generateDNSInstructions($domain);

            Log::info("Custom domain setup initiated", [
                'game_id' => $game->id,
                'domain' => $domain,
                'server_ip' => $this->getServerIp()
            ]);

            return [
                'success' => true,
                'domain' => $domain,
                'status' => 'pending',
                'dns_instructions' => $dnsInstructions,
                'vhost_config' => $vhostConfig,
                'verification_url' => $this->getVerificationUrl($domain),
                'estimated_propagation_time' => '5-30 minutes'
            ];

        } catch (\Exception $e) {
            Log::error("Domain setup failed", [
                'game_id' => $game->id,
                'domain' => $domain,
                'error' => $e->getMessage()
            ]);

            $game->setDomainStatus('failed', $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'troubleshooting' => $this->getTroubleshootingSteps()
            ];
        }
    }

    /**
     * Generate DNS configuration instructions.
     */
    public function generateDNSInstructions(string $domain): array
    {
        $serverIp = $this->getServerIp();
        
        return [
            'type' => 'A Record',
            'name' => '@',
            'value' => $serverIp,
            'ttl' => 300,
            'instructions' => [
                "1. Log into your domain registrar's control panel",
                "2. Navigate to DNS management or DNS settings",
                "3. Create a new A record with the following details:",
                "   - Type: A",
                "   - Name: @ (or leave blank for root domain)",
                "   - Value/Points to: {$serverIp}",
                "   - TTL: 300 seconds (5 minutes)",
                "4. Save the DNS record",
                "5. Wait 5-30 minutes for DNS propagation",
                "6. Click 'Verify Domain' to check if setup is complete"
            ],
            'common_providers' => [
                'Cloudflare' => 'DNS > Records > Add record',
                'GoDaddy' => 'DNS Management > Add Record',
                'Namecheap' => 'Advanced DNS > Add New Record',
                'Google Domains' => 'DNS > Custom records'
            ]
        ];
    }

    /**
     * Verify domain configuration and DNS propagation.
     */
    public function verifyDomain(Game $game): array
    {
        if (!$game->hasCustomDomain()) {
            return [
                'success' => false,
                'error' => 'No custom domain configured for this game'
            ];
        }

        try {
            $domain = $game->custom_domain;
            $expectedIp = $this->getServerIp();

            // Check DNS resolution
            $resolvedIp = gethostbyname($domain);
            
            if ($resolvedIp === $domain) {
                // DNS not resolved
                $game->setDomainStatus('pending', 'DNS not yet propagated');
                
                return [
                    'success' => false,
                    'status' => 'pending',
                    'message' => 'DNS propagation in progress. Please wait and try again.',
                    'expected_ip' => $expectedIp,
                    'current_status' => 'DNS not resolved'
                ];
            }

            if ($resolvedIp !== $expectedIp) {
                // DNS points to wrong IP
                $game->setDomainStatus('failed', "DNS points to {$resolvedIp}, expected {$expectedIp}");
                
                return [
                    'success' => false,
                    'status' => 'failed',
                    'message' => 'DNS configuration incorrect',
                    'expected_ip' => $expectedIp,
                    'resolved_ip' => $resolvedIp,
                    'troubleshooting' => $this->getTroubleshootingSteps()
                ];
            }

            // DNS is correct, activate domain
            $game->setDomainStatus('active', 'Domain verified and active');
            
            Log::info("Domain verification successful", [
                'game_id' => $game->id,
                'domain' => $domain,
                'resolved_ip' => $resolvedIp
            ]);

            return [
                'success' => true,
                'status' => 'active',
                'message' => 'Domain successfully configured and active',
                'domain_url' => $game->getCustomDomainUrl(),
                'verified_at' => now()->toISOString()
            ];

        } catch (\Exception $e) {
            Log::error("Domain verification failed", [
                'game_id' => $game->id,
                'domain' => $game->custom_domain,
                'error' => $e->getMessage()
            ]);

            $game->setDomainStatus('failed', $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'troubleshooting' => $this->getTroubleshootingSteps()
            ];
        }
    }

    /**
     * Remove custom domain configuration.
     */
    public function removeDomain(Game $game): array
    {
        try {
            $domain = $game->custom_domain;

            $game->update([
                'custom_domain' => null,
                'domain_status' => null,
                'domain_config' => null
            ]);

            Log::info("Custom domain removed", [
                'game_id' => $game->id,
                'domain' => $domain
            ]);

            return [
                'success' => true,
                'message' => 'Custom domain configuration removed successfully'
            ];

        } catch (\Exception $e) {
            Log::error("Failed to remove domain", [
                'game_id' => $game->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Validate domain format.
     */
    private function validateDomain(string $domain): void
    {
        // Remove protocol if present
        $domain = preg_replace('/^https?:\/\//', '', $domain);
        
        // Remove trailing slash
        $domain = rtrim($domain, '/');

        // Basic domain validation
        if (!filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            throw new InvalidArgumentException("Invalid domain format: {$domain}");
        }

        // Check for localhost or IP addresses (not allowed for custom domains)
        if (in_array($domain, ['localhost', '127.0.0.1']) || filter_var($domain, FILTER_VALIDATE_IP)) {
            throw new InvalidArgumentException("Localhost and IP addresses are not allowed as custom domains");
        }

        // Check minimum length
        if (strlen($domain) < 4) {
            throw new InvalidArgumentException("Domain must be at least 4 characters long");
        }
    }

    /**
     * Generate virtual host configuration for web server.
     */
    private function generateVirtualHostConfig(Game $game, string $domain): string
    {
        $gamePath = $this->getGameStoragePath($game);
        $serverIp = $this->getServerIp();

        return <<<VHOST
<VirtualHost {$serverIp}:80>
    ServerName {$domain}
    DocumentRoot {$gamePath}
    
    <Directory {$gamePath}>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    # Game-specific headers
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-Content-Type-Options "nosniff"
    
    # Cache static assets
    <LocationMatch "\.(css|js|png|jpg|jpeg|gif|ico|svg)$">
        ExpiresActive On
        ExpiresDefault "access plus 1 month"
    </LocationMatch>
    
    ErrorLog \${APACHE_LOG_DIR}/{$domain}_error.log
    CustomLog \${APACHE_LOG_DIR}/{$domain}_access.log combined
</VirtualHost>
VHOST;
    }

    /**
     * Get server IP address from environment or detect automatically.
     */
    private function getServerIp(): string
    {
        // First try environment variable
        $serverIp = env('SERVER_IP');
        
        if ($serverIp) {
            return $serverIp;
        }

        // For local development, use localhost IP
        if (app()->environment('local')) {
            return '127.0.0.1';
        }

        // Try to detect server IP automatically
        try {
            $serverIp = file_get_contents('https://api.ipify.org');
            if ($serverIp && filter_var($serverIp, FILTER_VALIDATE_IP)) {
                return $serverIp;
            }
        } catch (\Exception $e) {
            Log::warning("Could not auto-detect server IP", ['error' => $e->getMessage()]);
        }

        // Fallback to localhost for development
        return '127.0.0.1';
    }

    /**
     * Get verification URL for domain checking.
     */
    private function getVerificationUrl(string $domain): string
    {
        return "http://{$domain}";
    }

    /**
     * Get troubleshooting steps for common domain issues.
     */
    private function getTroubleshootingSteps(): array
    {
        return [
            'DNS Propagation' => [
                'Check if DNS has propagated using online tools like whatsmydns.net',
                'Wait up to 48 hours for full global propagation',
                'Clear your local DNS cache (ipconfig /flushdns on Windows)'
            ],
            'Incorrect DNS Configuration' => [
                'Verify the A record points to the correct IP address',
                'Ensure you\'re using @ or blank for the record name (not www)',
                'Check TTL is set to 300 seconds or lower for faster updates'
            ],
            'Domain Registrar Issues' => [
                'Confirm you have admin access to modify DNS records',
                'Some registrars have a delay before changes take effect',
                'Contact your registrar support if DNS changes aren\'t saving'
            ],
            'Firewall or Network Issues' => [
                'Ensure port 80 (HTTP) is open on the server',
                'Check if your ISP blocks certain domains or ports',
                'Try accessing from a different network or device'
            ]
        ];
    }

    /**
     * Get the full storage path for a game.
     */
    private function getGameStoragePath(Game $game): string
    {
        $storagePath = storage_path('app');
        $gameDirectory = "workspaces/{$game->workspace_id}/games/{$game->id}";
        
        return "{$storagePath}/{$gameDirectory}";
    }
}