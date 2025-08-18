<?php

namespace App\Services;

use App\Models\Workspace;
use App\Services\CreditManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Exception;

class PublishService
{
    private const GITHUB_API_BASE = 'https://api.github.com';
    private const CLOUDFLARE_API_BASE = 'https://api.cloudflare.com/client/v4';

    private CreditManager $creditManager;

    public function __construct(CreditManager $creditManager)
    {
        $this->creditManager = $creditManager;
    }

    /**
     * Publish workspace to static hosting (S3/CloudFront or GitHub Pages)
     */
    public function publishToStatic(Workspace $workspace): string
    {
        Log::info("Starting static publish for workspace {$workspace->id}");

        // Update workspace status
        $workspace->update(['status' => 'building']);

        try {
            // Build the project
            if (!$this->buildProject($workspace)) {
                throw new Exception('Build process failed');
            }

            // Determine publishing method based on configuration
            $publishedUrl = $this->getPublishingMethod() === 'github'
                ? $this->publishToGitHubPages($workspace)
                : $this->publishToS3($workspace);

            // Update workspace with published URL
            $workspace->update([
                'status' => 'published',
                'published_url' => $publishedUrl
            ]);

            Log::info("Successfully published workspace {$workspace->id} to {$publishedUrl}");

            return $publishedUrl;

        } catch (Exception $e) {
            Log::error("Failed to publish workspace {$workspace->id}: " . $e->getMessage());

            $workspace->update(['status' => 'error']);

            throw $e;
        }
    }

    /**
     * Build PlayCanvas project using npm
     */
    public function buildProject(Workspace $workspace): bool
    {
        if (!$workspace->isPlayCanvas()) {
            throw new Exception('Build process only supports PlayCanvas workspaces');
        }

        $workspacePath = $this->getWorkspacePath($workspace);
        $isTemporaryPath = str_contains($workspacePath, sys_get_temp_dir());

        if (!is_dir($workspacePath)) {
            throw new Exception("Workspace directory not found: {$workspacePath}");
        }

        Log::info("Building PlayCanvas project at {$workspacePath}");

        try {
            // Check if package.json exists
            if (!file_exists($workspacePath . '/package.json')) {
                throw new Exception('package.json not found in workspace');
            }

            // Install dependencies if node_modules doesn't exist
            if (!is_dir($workspacePath . '/node_modules')) {
                $installResult = Process::path($workspacePath)
                    ->timeout(300) // 5 minutes timeout
                    ->run('npm install');

                if (!$installResult->successful()) {
                    throw new Exception('npm install failed: ' . $installResult->errorOutput());
                }
            }

            // Run build command
            $buildResult = Process::path($workspacePath)
                ->timeout(600) // 10 minutes timeout
                ->run('npm run build');

            if (!$buildResult->successful()) {
                throw new Exception('npm run build failed: ' . $buildResult->errorOutput());
            }

            // Verify build output exists
            $buildPath = $workspacePath . '/dist';
            if (!is_dir($buildPath)) {
                $buildPath = $workspacePath . '/build';
            }

            if (!is_dir($buildPath)) {
                throw new Exception('Build output directory not found (expected dist/ or build/)');
            }

            // Optimize assets for mobile
            $this->optimizeAssetsForMobile($buildPath);

            // Store the built game in the configured storage system
            $this->storeBuildArtifacts($workspace, $buildPath);

            Log::info("Successfully built PlayCanvas project for workspace {$workspace->id}");

            return true;

        } catch (Exception $e) {
            Log::error("Build failed for workspace {$workspace->id}: " . $e->getMessage());
            return false;
        } finally {
            // Clean up temporary workspace if it was downloaded from cloud storage
            if ($isTemporaryPath && is_dir($workspacePath)) {
                $this->cleanupTemporaryDirectory($workspacePath);
            }
        }
    }

    /**
     * Publish to S3 with CloudFront distribution
     */
    public function publishToS3(Workspace $workspace, string $environment = 'production'): string
    {
        $buildPath = $this->getBuildPath($workspace);
        $s3Path = $this->generateS3Path($workspace);
        if ($environment !== 'production') {
            $s3Path = $environment . '/' . ltrim($s3Path, '/');
        }

        // Upload files to S3
        $this->uploadToS3($buildPath, $s3Path);

        // Generate CloudFront URL
        $cloudFrontUrl = $this->generateCloudFrontUrl($s3Path);

        // Invalidate CloudFront cache
        $this->invalidateCloudFront($s3Path);

        return $cloudFrontUrl;
    }

    /**
     * Publish to GitHub Pages
     */
    public function publishToGitHubPages(Workspace $workspace): string
    {
        $buildPath = $this->getBuildPath($workspace);
        $repoPath = $this->getGitHubPagesPath($workspace);

        // Upload to GitHub Pages repository
        $this->uploadToGitHubPages($buildPath, $repoPath);

        return $this->generateGitHubPagesUrl($workspace);
    }

    /**
     * Publish workspace to PlayCanvas cloud
     */
    public function publishToPlayCanvasCloud(Workspace $workspace, array $credentials): string
    {
        if (!$workspace->isPlayCanvas()) {
            throw new Exception('PlayCanvas cloud publishing only supports PlayCanvas workspaces');
        }

        Log::info("Starting PlayCanvas cloud publish for workspace {$workspace->id}");

        // Calculate build cost (build tokens only, no MCP surcharge for cloud publishing)
        $buildCost = $this->calculateBuildCost($workspace);

        // Check if company can afford the build cost
        if (!$this->creditManager->canAffordRequest($workspace->company, $buildCost)) {
            throw new Exception('Insufficient credits for PlayCanvas cloud publishing');
        }

        // Update workspace status
        $workspace->update(['status' => 'building']);

        $buildPath = null;
        $isTemporaryBuildPath = false;

        try {
            // Build the project first
            if (!$this->buildProject($workspace)) {
                throw new Exception('Build process failed');
            }

            // Get build artifacts
            $buildPath = $this->getBuildPath($workspace);
            $isTemporaryBuildPath = str_contains($buildPath, sys_get_temp_dir());

            // Validate credentials
            if (empty($credentials['api_key']) || empty($credentials['project_id'])) {
                throw new Exception('PlayCanvas API key and Project ID are required');
            }

            // Upload build to PlayCanvas cloud
            $launchUrl = $this->uploadToPlayCanvasCloud($buildPath, $credentials);

            // Deduct credits for build tokens only (as per requirement 7.4)
            $this->creditManager->deductCredits(
                $workspace->company,
                $buildCost,
                'PlayCanvas cloud publishing',
                [
                    'workspace_id' => $workspace->id,
                    'engine_type' => 'playcanvas',
                    'publish_type' => 'cloud',
                    'project_id' => $credentials['project_id'],
                    'build_cost' => $buildCost,
                    'mcp_surcharge' => 0, // No MCP surcharge for cloud publishing
                ]
            );

            // Update workspace with published URL
            $workspace->update([
                'status' => 'published',
                'published_url' => $launchUrl
            ]);

            Log::info("Successfully published workspace {$workspace->id} to PlayCanvas cloud: {$launchUrl}");

            return $launchUrl;

        } catch (Exception $e) {
            Log::error("Failed to publish workspace {$workspace->id} to PlayCanvas cloud: " . $e->getMessage());

            $workspace->update(['status' => 'error']);

            throw $e;
        } finally {
            // Clean up temporary build artifacts if they were downloaded from cloud storage
            if ($buildPath && $isTemporaryBuildPath && is_dir($buildPath)) {
                $this->cleanupTemporaryDirectory($buildPath);
            }
        }
    }

    /**
     * Invalidate CloudFront distribution
     */
    public function invalidateCloudFront(string $path): bool
    {
        if (!config('services.aws.cloudfront_distribution_id')) {
            Log::warning('CloudFront distribution ID not configured, skipping invalidation');
            return true;
        }

        try {
            // For now, we'll use HTTP client to call CloudFront API
            // In production, you'd want to use AWS SDK
            $response = Http::withHeaders([
                'Authorization' => 'AWS4-HMAC-SHA256 ' . $this->generateAwsSignature(),
                'Content-Type' => 'application/json',
            ])->post(self::CLOUDFLARE_API_BASE . '/distributions/' . config('services.aws.cloudfront_distribution_id') . '/invalidation', [
                'InvalidationBatch' => [
                    'Paths' => [
                        'Quantity' => 1,
                        'Items' => ["/{$path}/*"]
                    ],
                    'CallerReference' => Str::uuid()
                ]
            ]);

            if ($response->successful()) {
                Log::info("CloudFront invalidation created for path: {$path}");
                return true;
            }

            Log::error("CloudFront invalidation failed: " . $response->body());
            return false;

        } catch (Exception $e) {
            Log::error("CloudFront invalidation error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Optimize assets for mobile performance
     */
    private function optimizeAssetsForMobile(string $buildPath): void
    {
        Log::info("Optimizing assets for mobile at {$buildPath}");

        // Enable gzip compression for text files
        $this->compressTextFiles($buildPath);

        // Add mobile-optimized headers to HTML files
        $this->addMobileOptimizationHeaders($buildPath);

        // Optimize images if possible
        $this->optimizeImages($buildPath);
    }

    /**
     * Compress text files with gzip
     */
    private function compressTextFiles(string $buildPath): void
    {
        $extensions = ['html', 'css', 'js', 'json', 'xml', 'txt'];

        foreach ($extensions as $ext) {
            $files = glob($buildPath . "/**/*.{$ext}", GLOB_BRACE);

            foreach ($files as $file) {
                if (file_exists($file)) {
                    $content = file_get_contents($file);
                    $compressed = gzencode($content, 9);
                    file_put_contents($file . '.gz', $compressed);
                }
            }
        }
    }

    /**
     * Add mobile optimization headers to HTML files
     */
    private function addMobileOptimizationHeaders(string $buildPath): void
    {
        $htmlFiles = glob($buildPath . '/**/*.html', GLOB_BRACE);

        foreach ($htmlFiles as $file) {
            $content = file_get_contents($file);

            // Add mobile viewport and performance hints
            $mobileOptimizations = '
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="preload" as="script" href="./playcanvas-stable.min.js">
    <link rel="dns-prefetch" href="//fonts.googleapis.com">
    <style>
        body { margin: 0; padding: 0; overflow: hidden; }
        canvas { display: block; width: 100vw; height: 100vh; }
    </style>';

            $content = str_replace('</head>', $mobileOptimizations . '</head>', $content);
            file_put_contents($file, $content);
        }
    }

    /**
     * Basic image optimization
     */
    private function optimizeImages(string $buildPath): void
    {
        // For now, just log that we would optimize images
        // In production, you might use imagemin or similar tools
        Log::info("Image optimization placeholder for {$buildPath}");
    }

    /**
     * Upload files to S3
     */
    private function uploadToS3(string $buildPath, string $s3Path): void
    {
        $disk = Storage::disk('s3');

        $files = $this->getAllFiles($buildPath);

        foreach ($files as $file) {
            $relativePath = str_replace($buildPath . DIRECTORY_SEPARATOR, '', $file);
            $relativePath = str_replace('\\', '/', $relativePath); // Normalize path separators
            $s3Key = $s3Path . '/' . $relativePath;

            $content = file_get_contents($file);
            $mimeType = $this->getMimeType($file);

            $options = [
                'ContentType' => $mimeType,
                'CacheControl' => $this->getCacheControl($file),
            ];

            // Add compression headers for compressed files
            if (str_ends_with($file, '.gz')) {
                $options['ContentEncoding'] = 'gzip';
            }

            $disk->put($s3Key, $content, $options);
        }

        Log::info("Uploaded " . count($files) . " files to S3 path: {$s3Path}");
    }

    /**
     * Upload files to GitHub Pages
     */
    private function uploadToGitHubPages(string $buildPath, string $repoPath): void
    {
        $token = config('services.github.token');
        $repo = config('services.github.pages_repo');

        if (!$token || !$repo) {
            throw new Exception('GitHub configuration missing');
        }

        $files = $this->getAllFiles($buildPath);

        foreach ($files as $file) {
            $relativePath = str_replace($buildPath . '/', '', $file);
            $filePath = $repoPath . '/' . $relativePath;

            $content = base64_encode(file_get_contents($file));

            $response = Http::withToken($token)
                ->put(self::GITHUB_API_BASE . "/repos/{$repo}/contents/{$filePath}", [
                    'message' => "Deploy workspace {$repoPath}",
                    'content' => $content,
                    'branch' => 'gh-pages'
                ]);

            if (!$response->successful()) {
                Log::error("Failed to upload {$filePath} to GitHub: " . $response->body());
            }
        }

        Log::info("Uploaded " . count($files) . " files to GitHub Pages: {$repoPath}");
    }

    /**
     * Get all files recursively from a directory
     */
    private function getAllFiles(string $directory): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * Get workspace filesystem path using Laravel storage system
     */
    private function getWorkspacePath(Workspace $workspace): string
    {
        // Use the configured storage disk for workspaces
        $storageDisk = config('workspace.workspace_disk', 'local');
        $disk = Storage::disk($storageDisk);

        $workspacePath = "workspaces/{$workspace->company_id}/{$workspace->id}";

        // For local storage, return the full path
        if ($storageDisk === 'local') {
            return $disk->path($workspacePath);
        }

        // For cloud storage, we need to handle differently
        // Download the workspace to a temporary location for building
        return $this->downloadWorkspaceForBuilding($workspace, $workspacePath);
    }

    /**
     * Download workspace from cloud storage to temporary location for building
     */
    private function downloadWorkspaceForBuilding(Workspace $workspace, string $workspacePath): string
    {
        $storageDisk = config('workspace.workspace_disk', 'local');
        $disk = Storage::disk($storageDisk);

        // Create temporary directory
        $tempDir = config('workspace.temp_directory', sys_get_temp_dir());
        $tempPath = $tempDir . '/workspace_' . $workspace->id . '_' . uniqid();
        if (!mkdir($tempPath, 0755, true)) {
            throw new Exception("Failed to create temporary workspace directory: {$tempPath}");
        }

        // Download all workspace files
        $files = $disk->allFiles($workspacePath);

        foreach ($files as $file) {
            $localPath = $tempPath . '/' . str_replace($workspacePath . '/', '', $file);
            $localDir = dirname($localPath);

            if (!is_dir($localDir)) {
                mkdir($localDir, 0755, true);
            }

            $content = $disk->get($file);
            file_put_contents($localPath, $content);
        }

        Log::info("Downloaded workspace {$workspace->id} from {$storageDisk} storage to {$tempPath}");

        return $tempPath;
    }

    /**
     * Get build output path
     */
    private function getBuildPath(Workspace $workspace): string
    {
        // First try to get stored build artifacts
        try {
            return $this->getBuildArtifactsPath($workspace);
        } catch (Exception $e) {
            // Fall back to workspace build directory if no stored artifacts
            Log::info("No stored build artifacts found, checking workspace build directory: " . $e->getMessage());
        }

        $workspacePath = $this->getWorkspacePath($workspace);

        if (is_dir($workspacePath . '/dist')) {
            return $workspacePath . '/dist';
        }

        if (is_dir($workspacePath . '/build')) {
            return $workspacePath . '/build';
        }

        throw new Exception('Build output directory not found');
    }

    /**
     * Generate S3 path for workspace
     */
    private function generateS3Path(Workspace $workspace): string
    {
        return "builds/{$workspace->company_id}/{$workspace->id}/v" . time();
    }

    /**
     * Generate CloudFront URL
     */
    private function generateCloudFrontUrl(string $s3Path): string
    {
        $domain = config('services.aws.cloudfront_domain');
        return "https://{$domain}/{$s3Path}/index.html";
    }

    /**
     * Generate GitHub Pages path
     */
    private function getGitHubPagesPath(Workspace $workspace): string
    {
        return "builds/{$workspace->company_id}/{$workspace->id}";
    }

    /**
     * Generate GitHub Pages URL
     */
    private function generateGitHubPagesUrl(Workspace $workspace): string
    {
        $username = config('services.github.username');
        $repo = config('services.github.pages_repo');
        $path = $this->getGitHubPagesPath($workspace);

        return "https://{$username}.github.io/{$repo}/{$path}/";
    }

    /**
     * Get publishing method from configuration
     */
    private function getPublishingMethod(): string
    {
        return config('services.publishing.method', 's3');
    }

    /**
     * Get MIME type for file
     */
    private function getMimeType(string $file): string
    {
        $extension = pathinfo($file, PATHINFO_EXTENSION);

        return match($extension) {
            'html' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            default => 'application/octet-stream'
        };
    }

    /**
     * Get cache control header for file
     */
    private function getCacheControl(string $file): string
    {
        $extension = pathinfo($file, PATHINFO_EXTENSION);

        return match($extension) {
            'html' => 'no-cache, must-revalidate',
            'css', 'js' => 'public, max-age=31536000', // 1 year
            'png', 'jpg', 'jpeg', 'gif', 'svg' => 'public, max-age=31536000', // 1 year
            'woff', 'woff2', 'ttf' => 'public, max-age=31536000', // 1 year
            default => 'public, max-age=3600' // 1 hour
        };
    }

    /**
     * Upload build to PlayCanvas cloud
     */
    private function uploadToPlayCanvasCloud(string $buildPath, array $credentials): string
    {
        $apiKey = $credentials['api_key'];
        $projectId = $credentials['project_id'];

        // PlayCanvas API endpoint for publishing
        $apiUrl = "https://playcanvas.com/api/apps/{$projectId}/publish";

        try {
            // Create a zip file of the build
            $zipPath = $this->createBuildZip($buildPath);

            // Upload to PlayCanvas
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'multipart/form-data',
            ])->attach(
                'archive', file_get_contents($zipPath), 'build.zip'
            )->post($apiUrl, [
                'name' => 'Surreal Pilot Build ' . date('Y-m-d H:i:s'),
                'description' => 'Published from Surreal Pilot',
            ]);

            // Clean up zip file
            if (file_exists($zipPath)) {
                unlink($zipPath);
            }

            if (!$response->successful()) {
                $error = $response->json('error') ?? 'Unknown error';
                throw new Exception("PlayCanvas API error: {$error}");
            }

            $responseData = $response->json();

            // Return the launch URL from PlayCanvas response
            if (isset($responseData['url'])) {
                return $responseData['url'];
            }

            // Fallback: construct URL from project ID
            return "https://playcanv.as/{$projectId}/";

        } catch (Exception $e) {
            Log::error("PlayCanvas cloud upload failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create a zip file of the build directory
     */
    private function createBuildZip(string $buildPath): string
    {
        $zipPath = sys_get_temp_dir() . '/playcanvas_build_' . uniqid() . '.zip';

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE) !== TRUE) {
            throw new Exception('Cannot create zip file');
        }

        $files = $this->getAllFiles($buildPath);

        foreach ($files as $file) {
            $relativePath = str_replace($buildPath . DIRECTORY_SEPARATOR, '', $file);
            $relativePath = str_replace('\\', '/', $relativePath); // Normalize path separators
            $zip->addFile($file, $relativePath);
        }

        $zip->close();

        return $zipPath;
    }

    /**
     * Calculate the build cost for publishing (build tokens only)
     */
    private function calculateBuildCost(Workspace $workspace): float
    {
        // Base cost for building and publishing to PlayCanvas cloud
        // This represents the computational cost of building the project
        return 1.0; // 1 credit for build process
    }

    /**
     * Store build artifacts in the configured storage system
     */
    private function storeBuildArtifacts(Workspace $workspace, string $buildPath): void
    {
        $storageDisk = config('workspace.builds_disk', config('filesystems.default', 'local'));
        $disk = Storage::disk($storageDisk);

        $buildStoragePath = "builds/{$workspace->company_id}/{$workspace->id}/" . date('Y-m-d_H-i-s');

        $files = $this->getAllFiles($buildPath);

        foreach ($files as $file) {
            $relativePath = str_replace($buildPath . DIRECTORY_SEPARATOR, '', $file);
            $relativePath = str_replace('\\', '/', $relativePath); // Normalize path separators
            $storageKey = $buildStoragePath . '/' . $relativePath;

            $content = file_get_contents($file);
            $mimeType = $this->getMimeType($file);

            $options = [
                'ContentType' => $mimeType,
                'CacheControl' => $this->getCacheControl($file),
            ];

            // Add compression headers for compressed files
            if (str_ends_with($file, '.gz')) {
                $options['ContentEncoding'] = 'gzip';
            }

            $disk->put($storageKey, $content, $options);
        }

        // Update workspace metadata with build storage path
        $metadata = $workspace->metadata ?? [];
        $metadata['latest_build_path'] = $buildStoragePath;
        $metadata['build_timestamp'] = now()->toISOString();
        $metadata['build_storage_disk'] = $storageDisk;

        $workspace->update(['metadata' => $metadata]);

        Log::info("Stored " . count($files) . " build files for workspace {$workspace->id} in {$storageDisk} storage at {$buildStoragePath}");
    }

    /**
     * Get build artifacts path from storage
     */
    private function getBuildArtifactsPath(Workspace $workspace): string
    {
        $metadata = $workspace->metadata ?? [];

        if (!isset($metadata['latest_build_path'])) {
            throw new Exception('No build artifacts found for workspace');
        }

        $storageDisk = $metadata['build_storage_disk'] ?? config('workspace.builds_disk', config('filesystems.default', 'local'));
        $disk = Storage::disk($storageDisk);
        $buildPath = $metadata['latest_build_path'];

        // For local storage, return the full path
        if ($storageDisk === 'local') {
            return $disk->path($buildPath);
        }

        // For cloud storage, download to temporary location
        return $this->downloadBuildArtifacts($workspace, $buildPath, $storageDisk);
    }

    /**
     * Download build artifacts from cloud storage to temporary location
     */
    private function downloadBuildArtifacts(Workspace $workspace, string $buildPath, string $storageDisk): string
    {
        $disk = Storage::disk($storageDisk);

        // Create temporary directory
        $tempDir = config('workspace.temp_directory', sys_get_temp_dir());
        $tempPath = $tempDir . '/build_' . $workspace->id . '_' . uniqid();
        if (!mkdir($tempPath, 0755, true)) {
            throw new Exception("Failed to create temporary build directory: {$tempPath}");
        }

        // Download all build files
        $files = $disk->allFiles($buildPath);

        foreach ($files as $file) {
            $localPath = $tempPath . '/' . str_replace($buildPath . '/', '', $file);
            $localDir = dirname($localPath);

            if (!is_dir($localDir)) {
                mkdir($localDir, 0755, true);
            }

            $content = $disk->get($file);
            file_put_contents($localPath, $content);
        }

        Log::info("Downloaded build artifacts for workspace {$workspace->id} from {$storageDisk} storage to {$tempPath}");

        return $tempPath;
    }

    /**
     * Clean up temporary directory
     */
    private function cleanupTemporaryDirectory(string $path): void
    {
        $tempDir = config('workspace.temp_directory', sys_get_temp_dir());

        if (!str_contains($path, $tempDir)) {
            Log::warning("Attempted to cleanup non-temporary directory: {$path}");
            return;
        }

        try {
            $this->deleteDirectory($path);
            Log::info("Cleaned up temporary directory: {$path}");
        } catch (Exception $e) {
            Log::error("Failed to cleanup temporary directory {$path}: " . $e->getMessage());
        }
    }

    /**
     * Recursively delete a directory
     */
    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;

            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }

    /**
     * Generate AWS signature (placeholder - use AWS SDK in production)
     */
    private function generateAwsSignature(): string
    {
        // This is a placeholder - in production you'd use AWS SDK
        return 'placeholder-signature';
    }
}
