<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class SampleProjectStorageService
{
    private array $storageOptions;

    public function __construct()
    {
        $this->storageOptions = [
            'git' => [
                'name' => 'Git Repository',
                'cost_per_gb_month' => 0.00, // Free for public repos
                'pros' => ['Version control', 'Free for public repos', 'Easy collaboration', 'Familiar to developers'],
                'cons' => ['Not optimized for large binaries', 'Cloning can be slow', 'Limited LFS storage'],
                'recommended_for' => 'Source code and small assets',
            ],
            's3' => [
                'name' => 'Amazon S3',
                'cost_per_gb_month' => 0.023, // Standard tier
                'pros' => ['Highly scalable', 'Fast access', 'CDN integration', 'Versioning support'],
                'cons' => ['Costs can add up', 'Requires AWS setup', 'Data transfer costs'],
                'recommended_for' => 'Large assets and build artifacts',
            ],
            'cloudflare_r2' => [
                'name' => 'Cloudflare R2',
                'cost_per_gb_month' => 0.015, // No egress fees
                'pros' => ['No egress fees', 'S3 compatible', 'Global CDN', 'Competitive pricing'],
                'cons' => ['Newer service', 'Limited regions', 'Requires Cloudflare account'],
                'recommended_for' => 'Cost-effective alternative to S3',
            ],
            'supabase' => [
                'name' => 'Supabase Storage',
                'cost_per_gb_month' => 0.021,
                'pros' => ['Integrated with database', 'Real-time features', 'Good free tier', 'Easy setup'],
                'cons' => ['Smaller ecosystem', 'Less mature than AWS', 'Limited CDN options'],
                'recommended_for' => 'Projects already using Supabase',
            ],
            'github_releases' => [
                'name' => 'GitHub Releases',
                'cost_per_gb_month' => 0.00, // Free up to limits
                'pros' => ['Free up to 2GB per file', 'Integrated with Git', 'Good for versioned assets'],
                'cons' => ['File size limits', 'Not a CDN', 'Limited to releases'],
                'recommended_for' => 'Versioned sample projects and templates',
            ],
        ];
    }

    /**
     * Analyze storage options and provide recommendations.
     *
     * @param array $requirements
     * @return array
     */
    public function analyzeStorageOptions(array $requirements = []): array
    {
        $analysis = [
            'options' => $this->storageOptions,
            'recommendations' => [],
            'cost_comparison' => [],
            'hybrid_approach' => [],
        ];

        // Default requirements
        $requirements = array_merge([
            'estimated_storage_gb' => 10,
            'monthly_downloads' => 1000,
            'avg_project_size_mb' => 50,
            'needs_versioning' => true,
            'needs_cdn' => true,
            'budget_per_month' => 50,
        ], $requirements);

        // Calculate costs for each option
        foreach ($this->storageOptions as $key => $option) {
            $monthlyCost = $this->calculateMonthlyCost($option, $requirements);
            $analysis['cost_comparison'][$key] = [
                'name' => $option['name'],
                'monthly_cost' => $monthlyCost,
                'cost_per_download' => $monthlyCost / max($requirements['monthly_downloads'], 1),
            ];
        }

        // Sort by cost
        uasort($analysis['cost_comparison'], function ($a, $b) {
            return $a['monthly_cost'] <=> $b['monthly_cost'];
        });

        // Generate recommendations
        $analysis['recommendations'] = $this->generateRecommendations($requirements, $analysis['cost_comparison']);

        // Suggest hybrid approach
        $analysis['hybrid_approach'] = $this->suggestHybridApproach($requirements);

        return $analysis;
    }

    /**
     * Calculate monthly cost for a storage option.
     *
     * @param array $option
     * @param array $requirements
     * @return float
     */
    private function calculateMonthlyCost(array $option, array $requirements): float
    {
        $storageCost = $option['cost_per_gb_month'] * $requirements['estimated_storage_gb'];
        
        // Add estimated data transfer costs for paid services
        $transferCost = 0;
        if ($option['cost_per_gb_month'] > 0) {
            $transferGb = ($requirements['monthly_downloads'] * $requirements['avg_project_size_mb']) / 1024;
            $transferCost = $transferGb * 0.09; // Approximate data transfer cost
        }

        return $storageCost + $transferCost;
    }

    /**
     * Generate storage recommendations based on requirements.
     *
     * @param array $requirements
     * @param array $costComparison
     * @return array
     */
    private function generateRecommendations(array $requirements, array $costComparison): array
    {
        $recommendations = [];

        // Budget-conscious recommendation
        $cheapestOption = array_key_first($costComparison);
        $recommendations['budget'] = [
            'option' => $cheapestOption,
            'reason' => 'Most cost-effective option for your requirements',
            'monthly_cost' => $costComparison[$cheapestOption]['monthly_cost'],
        ];

        // Performance recommendation
        if ($requirements['needs_cdn'] && $requirements['monthly_downloads'] > 500) {
            $recommendations['performance'] = [
                'option' => 'cloudflare_r2',
                'reason' => 'Best performance with global CDN and no egress fees',
                'monthly_cost' => $costComparison['cloudflare_r2']['monthly_cost'] ?? 0,
            ];
        }

        // Developer-friendly recommendation
        if ($requirements['needs_versioning']) {
            $recommendations['developer_friendly'] = [
                'option' => 'git',
                'reason' => 'Best for version control and developer collaboration',
                'monthly_cost' => 0,
            ];
        }

        // Enterprise recommendation
        if ($requirements['budget_per_month'] > 20) {
            $recommendations['enterprise'] = [
                'option' => 's3',
                'reason' => 'Most mature and feature-rich option with excellent ecosystem',
                'monthly_cost' => $costComparison['s3']['monthly_cost'] ?? 0,
            ];
        }

        return $recommendations;
    }

    /**
     * Suggest a hybrid storage approach.
     *
     * @param array $requirements
     * @return array
     */
    private function suggestHybridApproach(array $requirements): array
    {
        return [
            'approach' => 'Git + CDN',
            'description' => 'Use Git for source code and small assets, CDN for large binaries',
            'implementation' => [
                'source_code' => [
                    'storage' => 'git',
                    'location' => 'GitHub repository with submodules for each sample project',
                    'cost' => 0,
                ],
                'large_assets' => [
                    'storage' => 'cloudflare_r2',
                    'location' => 'Cloudflare R2 bucket with CDN',
                    'cost' => $this->calculateMonthlyCost($this->storageOptions['cloudflare_r2'], $requirements),
                ],
                'build_artifacts' => [
                    'storage' => 'github_releases',
                    'location' => 'GitHub Releases for versioned builds',
                    'cost' => 0,
                ],
            ],
            'total_monthly_cost' => $this->calculateMonthlyCost($this->storageOptions['cloudflare_r2'], $requirements),
            'benefits' => [
                'Version control for source code',
                'Fast CDN delivery for assets',
                'Cost-effective for most use cases',
                'Familiar developer workflow',
            ],
        ];
    }

    /**
     * Implement the recommended storage solution.
     *
     * @param string $option
     * @param array $config
     * @return array
     */
    public function implementStorageSolution(string $option, array $config = []): array
    {
        $result = [
            'success' => false,
            'storage_option' => $option,
            'configuration' => [],
            'next_steps' => [],
            'error' => null,
        ];

        try {
            switch ($option) {
                case 'git':
                    $result = $this->implementGitStorage($config);
                    break;
                
                case 's3':
                    $result = $this->implementS3Storage($config);
                    break;
                
                case 'cloudflare_r2':
                    $result = $this->implementCloudflareR2Storage($config);
                    break;
                
                case 'supabase':
                    $result = $this->implementSupabaseStorage($config);
                    break;
                
                case 'hybrid':
                    $result = $this->implementHybridStorage($config);
                    break;
                
                default:
                    throw new \InvalidArgumentException("Unsupported storage option: {$option}");
            }

            Log::info("Implemented storage solution: {$option}", $result);

        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
            Log::error("Failed to implement storage solution {$option}: {$e->getMessage()}");
        }

        return $result;
    }

    /**
     * Implement Git-based storage.
     *
     * @param array $config
     * @return array
     */
    private function implementGitStorage(array $config): array
    {
        return [
            'success' => true,
            'storage_option' => 'git',
            'configuration' => [
                'repository_url' => $config['repository_url'] ?? 'https://github.com/surrealpilot/playcanvas-samples',
                'branch' => $config['branch'] ?? 'main',
                'submodules' => true,
                'lfs_enabled' => true,
            ],
            'next_steps' => [
                'Create GitHub repository for sample projects',
                'Set up Git LFS for large assets',
                'Create submodules for each sample project',
                'Configure automated builds with GitHub Actions',
                'Set up CDN for built artifacts',
            ],
        ];
    }

    /**
     * Implement S3-based storage.
     *
     * @param array $config
     * @return array
     */
    private function implementS3Storage(array $config): array
    {
        return [
            'success' => true,
            'storage_option' => 's3',
            'configuration' => [
                'bucket_name' => $config['bucket_name'] ?? 'surrealpilot-samples',
                'region' => $config['region'] ?? 'us-east-1',
                'cloudfront_enabled' => true,
                'versioning_enabled' => true,
            ],
            'next_steps' => [
                'Create S3 bucket with appropriate permissions',
                'Set up CloudFront distribution',
                'Configure bucket versioning',
                'Set up lifecycle policies for cost optimization',
                'Create IAM roles for application access',
            ],
        ];
    }

    /**
     * Implement Cloudflare R2 storage.
     *
     * @param array $config
     * @return array
     */
    private function implementCloudflareR2Storage(array $config): array
    {
        return [
            'success' => true,
            'storage_option' => 'cloudflare_r2',
            'configuration' => [
                'bucket_name' => $config['bucket_name'] ?? 'surrealpilot-samples',
                'account_id' => $config['account_id'] ?? '',
                'custom_domain' => $config['custom_domain'] ?? 'samples.surrealpilot.com',
                's3_compatible' => true,
            ],
            'next_steps' => [
                'Create Cloudflare R2 bucket',
                'Set up custom domain with CDN',
                'Configure S3-compatible API access',
                'Set up automated uploads',
                'Configure cache headers for optimal performance',
            ],
        ];
    }

    /**
     * Implement Supabase storage.
     *
     * @param array $config
     * @return array
     */
    private function implementSupabaseStorage(array $config): array
    {
        return [
            'success' => true,
            'storage_option' => 'supabase',
            'configuration' => [
                'project_url' => $config['project_url'] ?? '',
                'bucket_name' => $config['bucket_name'] ?? 'sample-projects',
                'public_access' => true,
                'cdn_enabled' => true,
            ],
            'next_steps' => [
                'Create Supabase project',
                'Set up storage bucket with public access',
                'Configure RLS policies if needed',
                'Set up automated uploads',
                'Configure CDN settings',
            ],
        ];
    }

    /**
     * Implement hybrid storage approach.
     *
     * @param array $config
     * @return array
     */
    private function implementHybridStorage(array $config): array
    {
        return [
            'success' => true,
            'storage_option' => 'hybrid',
            'configuration' => [
                'git_repository' => $config['git_repository'] ?? 'https://github.com/surrealpilot/playcanvas-samples',
                'cdn_storage' => $config['cdn_storage'] ?? 'cloudflare_r2',
                'build_storage' => $config['build_storage'] ?? 'github_releases',
            ],
            'next_steps' => [
                'Set up Git repository for source code',
                'Configure CDN storage for large assets',
                'Set up automated build pipeline',
                'Create GitHub releases for versioned builds',
                'Configure template cloning to use hybrid sources',
            ],
        ];
    }

    /**
     * Clean up stale sample project artifacts.
     *
     * @param string $storageOption
     * @param array $config
     * @return array
     */
    public function cleanupStaleArtifacts(string $storageOption, array $config = []): array
    {
        $result = [
            'success' => false,
            'artifacts_cleaned' => 0,
            'storage_freed' => 0,
            'error' => null,
        ];

        try {
            switch ($storageOption) {
                case 's3':
                case 'cloudflare_r2':
                    $result = $this->cleanupCloudStorageArtifacts($storageOption, $config);
                    break;
                
                case 'supabase':
                    $result = $this->cleanupSupabaseArtifacts($config);
                    break;
                
                case 'git':
                    $result = $this->cleanupGitArtifacts($config);
                    break;
                
                default:
                    $result['success'] = true; // No cleanup needed
                    break;
            }

            Log::info("Cleaned up sample project artifacts for {$storageOption}", $result);

        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
            Log::error("Failed to cleanup sample project artifacts for {$storageOption}: {$e->getMessage()}");
        }

        return $result;
    }

    /**
     * Clean up cloud storage artifacts.
     *
     * @param string $storageOption
     * @param array $config
     * @return array
     */
    private function cleanupCloudStorageArtifacts(string $storageOption, array $config): array
    {
        $disk = Storage::disk($storageOption === 's3' ? 's3' : 'r2');
        $prefix = $config['prefix'] ?? 'samples/';
        $retentionDays = $config['retention_days'] ?? 30;
        
        $cutoffDate = now()->subDays($retentionDays);
        $artifactsCleaned = 0;
        $storageFreed = 0;

        $files = $disk->allFiles($prefix);
        
        foreach ($files as $file) {
            $lastModified = $disk->lastModified($file);
            
            if ($lastModified < $cutoffDate->timestamp) {
                $size = $disk->size($file);
                
                if ($disk->delete($file)) {
                    $artifactsCleaned++;
                    $storageFreed += $size;
                }
            }
        }

        return [
            'success' => true,
            'artifacts_cleaned' => $artifactsCleaned,
            'storage_freed' => $storageFreed,
        ];
    }

    /**
     * Clean up Supabase artifacts.
     *
     * @param array $config
     * @return array
     */
    private function cleanupSupabaseArtifacts(array $config): array
    {
        // Placeholder for Supabase cleanup
        // Would use Supabase API to clean up old files
        
        return [
            'success' => true,
            'artifacts_cleaned' => 0,
            'storage_freed' => 0,
        ];
    }

    /**
     * Clean up Git artifacts.
     *
     * @param array $config
     * @return array
     */
    private function cleanupGitArtifacts(array $config): array
    {
        // For Git, cleanup would involve removing old releases or tags
        // This would typically be done through GitHub API
        
        return [
            'success' => true,
            'artifacts_cleaned' => 0,
            'storage_freed' => 0,
        ];
    }
}