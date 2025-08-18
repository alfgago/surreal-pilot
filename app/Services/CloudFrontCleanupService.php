<?php

namespace App\Services;

use App\Models\Workspace;
use Aws\CloudFront\CloudFrontClient;
use Aws\Exception\AwsException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CloudFrontCleanupService
{
    private CloudFrontClient $cloudFrontClient;
    private ?string $distributionId;

    public function __construct(?CloudFrontClient $cloudFrontClient = null)
    {
        if ($cloudFrontClient) {
            $this->cloudFrontClient = $cloudFrontClient;
        } else {
            $credentials = [];
            
            // Only set credentials if they are configured
            if (config('aws.access_key_id') && config('aws.secret_access_key')) {
                $credentials = [
                    'key' => config('aws.access_key_id'),
                    'secret' => config('aws.secret_access_key'),
                ];
            }

            $this->cloudFrontClient = new CloudFrontClient([
                'region' => config('aws.region', 'us-east-1'),
                'version' => 'latest',
                'credentials' => $credentials ?: false, // Use false for default provider chain
            ]);
        }

        $this->distributionId = config('services.aws.cloudfront_distribution_id');
    }

    /**
     * Clean up CloudFront paths for a workspace.
     *
     * @param Workspace $workspace
     * @return int Number of paths cleaned up
     */
    public function cleanupWorkspacePaths(Workspace $workspace): int
    {
        if (!$this->distributionId) {
            Log::info('CloudFront distribution ID not configured, skipping CloudFront cleanup');
            return 0;
        }

        $pathsCleaned = 0;

        try {
            // Generate paths that might exist for this workspace
            $pathsToInvalidate = $this->generateWorkspacePaths($workspace);

            if (empty($pathsToInvalidate)) {
                return 0;
            }

            // Create invalidation request
            $invalidationId = $this->createInvalidation($pathsToInvalidate);

            if ($invalidationId) {
                $pathsCleaned = count($pathsToInvalidate);
                
                Log::info("Created CloudFront invalidation for workspace {$workspace->id}", [
                    'invalidation_id' => $invalidationId,
                    'paths' => $pathsToInvalidate,
                    'paths_count' => $pathsCleaned,
                ]);
            }

            return $pathsCleaned;

        } catch (\Exception $e) {
            Log::error("Failed to cleanup CloudFront paths for workspace {$workspace->id}: {$e->getMessage()}");
            return 0;
        }
    }

    /**
     * Clean up CloudFront paths for expired multiplayer sessions.
     *
     * @param array $sessionIds
     * @return int Number of paths cleaned up
     */
    public function cleanupMultiplayerSessionPaths(array $sessionIds): int
    {
        if (!$this->distributionId || empty($sessionIds)) {
            return 0;
        }

        $pathsCleaned = 0;

        try {
            $pathsToInvalidate = [];

            foreach ($sessionIds as $sessionId) {
                $pathsToInvalidate[] = "/multiplayer/session/{$sessionId}/*";
                $pathsToInvalidate[] = "/api/multiplayer/session/{$sessionId}/*";
            }

            if (!empty($pathsToInvalidate)) {
                $invalidationId = $this->createInvalidation($pathsToInvalidate);

                if ($invalidationId) {
                    $pathsCleaned = count($pathsToInvalidate);
                    
                    Log::info("Created CloudFront invalidation for multiplayer sessions", [
                        'invalidation_id' => $invalidationId,
                        'session_ids' => $sessionIds,
                        'paths_count' => $pathsCleaned,
                    ]);
                }
            }

            return $pathsCleaned;

        } catch (\Exception $e) {
            Log::error("Failed to cleanup CloudFront paths for multiplayer sessions: {$e->getMessage()}");
            return 0;
        }
    }

    /**
     * Clean up all stale CloudFront cache entries.
     *
     * @return int Number of invalidations created
     */
    public function cleanupStaleCache(): int
    {
        if (!$this->distributionId) {
            return 0;
        }

        try {
            // Create a broad invalidation for common stale paths
            $stalePaths = [
                '/builds/*',
                '/workspaces/*',
                '/multiplayer/*',
                '/api/workspace/*/status',
                '/api/multiplayer/*/status',
            ];

            $invalidationId = $this->createInvalidation($stalePaths);

            if ($invalidationId) {
                Log::info("Created CloudFront invalidation for stale cache", [
                    'invalidation_id' => $invalidationId,
                    'paths' => $stalePaths,
                ]);

                return 1;
            }

            return 0;

        } catch (\Exception $e) {
            Log::error("Failed to cleanup stale CloudFront cache: {$e->getMessage()}");
            return 0;
        }
    }

    /**
     * Generate CloudFront paths that might exist for a workspace.
     *
     * @param Workspace $workspace
     * @return array
     */
    private function generateWorkspacePaths(Workspace $workspace): array
    {
        $paths = [];

        // Build paths
        $paths[] = "/builds/{$workspace->company_id}/{$workspace->id}/*";
        
        // Workspace paths
        $paths[] = "/workspaces/{$workspace->company_id}/{$workspace->id}/*";
        
        // API paths
        $paths[] = "/api/workspace/{$workspace->id}/*";
        $paths[] = "/api/workspace/{$workspace->id}/status";
        $paths[] = "/api/workspace/{$workspace->id}/preview";
        
        // Published game paths (if published)
        if ($workspace->published_url) {
            $parsedUrl = parse_url($workspace->published_url);
            if ($parsedUrl && isset($parsedUrl['path'])) {
                $paths[] = $parsedUrl['path'] . '*';
            }
        }

        // Multiplayer session paths
        foreach ($workspace->multiplayerSessions as $session) {
            $paths[] = "/multiplayer/session/{$session->id}/*";
            $paths[] = "/api/multiplayer/session/{$session->id}/*";
        }

        return array_unique($paths);
    }

    /**
     * Create a CloudFront invalidation.
     *
     * @param array $paths
     * @return string|null Invalidation ID or null on failure
     */
    private function createInvalidation(array $paths): ?string
    {
        try {
            $result = $this->cloudFrontClient->createInvalidation([
                'DistributionId' => $this->distributionId,
                'InvalidationBatch' => [
                    'Paths' => [
                        'Quantity' => count($paths),
                        'Items' => $paths,
                    ],
                    'CallerReference' => 'workspace-cleanup-' . Str::uuid(),
                ],
            ]);

            return $result['Invalidation']['Id'] ?? null;

        } catch (AwsException $e) {
            Log::error("CloudFront invalidation failed: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Get the status of a CloudFront invalidation.
     *
     * @param string $invalidationId
     * @return array|null
     */
    public function getInvalidationStatus(string $invalidationId): ?array
    {
        if (!$this->distributionId) {
            return null;
        }

        try {
            $result = $this->cloudFrontClient->getInvalidation([
                'DistributionId' => $this->distributionId,
                'Id' => $invalidationId,
            ]);

            $invalidation = $result['Invalidation'] ?? null;

            if ($invalidation) {
                return [
                    'id' => $invalidation['Id'],
                    'status' => $invalidation['Status'],
                    'create_time' => $invalidation['CreateTime'],
                    'paths_count' => $invalidation['InvalidationBatch']['Paths']['Quantity'],
                ];
            }

            return null;

        } catch (AwsException $e) {
            Log::error("Failed to get CloudFront invalidation status: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * List recent CloudFront invalidations.
     *
     * @param int $maxItems
     * @return array
     */
    public function listRecentInvalidations(int $maxItems = 10): array
    {
        if (!$this->distributionId) {
            return [];
        }

        try {
            $result = $this->cloudFrontClient->listInvalidations([
                'DistributionId' => $this->distributionId,
                'MaxItems' => $maxItems,
            ]);

            $invalidations = $result['InvalidationList']['Items'] ?? [];

            return array_map(function ($invalidation) {
                return [
                    'id' => $invalidation['Id'],
                    'status' => $invalidation['Status'],
                    'create_time' => $invalidation['CreateTime'],
                ];
            }, $invalidations);

        } catch (AwsException $e) {
            Log::error("Failed to list CloudFront invalidations: {$e->getMessage()}");
            return [];
        }
    }

    /**
     * Check if CloudFront cleanup is enabled.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return !empty($this->distributionId);
    }
}