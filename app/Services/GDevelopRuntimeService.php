<?php

namespace App\Services;

use App\Exceptions\GDevelop\GDevelopCliException;
use App\Exceptions\GDevelop\GDevelopPreviewException;
use App\Exceptions\GDevelop\GDevelopExportException;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class GDevelopRuntimeService
{
    private string $cliPath;
    private string $coreToolsPath;
    private string $sessionsPath;
    private string $exportsPath;
    private GDevelopErrorRecoveryService $errorRecovery;

    public function __construct(
        GDevelopErrorRecoveryService $errorRecovery,
        private GDevelopProcessPoolService $processPool,
        private GDevelopPerformanceMonitorService $performanceMonitor
    ) {
        $this->errorRecovery = $errorRecovery;
        $this->cliPath = config('gdevelop.cli_path', 'gdexport');
        $this->coreToolsPath = config('gdevelop.core_tools_path', 'gdcore-tools');
        $this->sessionsPath = storage_path(config('gdevelop.sessions_path', 'gdevelop/sessions'));
        $this->exportsPath = storage_path(config('gdevelop.exports_path', 'gdevelop/exports'));
        
        // Ensure directories exist
        if (!is_dir($this->sessionsPath)) {
            mkdir($this->sessionsPath, 0755, true);
        }
        if (!is_dir($this->exportsPath)) {
            mkdir($this->exportsPath, 0755, true);
        }
    }

    /**
     * Execute a GDevelop CLI command headlessly using process pool
     */
    public function executeGDevelopCommand(array $command, ?string $workingDirectory = null): CommandResult
    {
        try {
            $workingDir = $workingDirectory ?? getcwd();
            
            Log::info('Executing GDevelop command with process pool', [
                'command' => $command,
                'working_directory' => $workingDir
            ]);

            // Use process pool for better performance
            return $this->processPool->executeCommand($command, $workingDir);

        } catch (GDevelopCliException $e) {
            throw $e; // Re-throw CLI exceptions
        } catch (Exception $e) {
            Log::error('GDevelop command execution failed', [
                'command' => $command,
                'error' => $e->getMessage()
            ]);

            throw new GDevelopCliException(
                message: "Failed to execute GDevelop command: " . $e->getMessage(),
                command: implode(' ', $command),
                stdout: '',
                stderr: $e->getMessage(),
                exitCode: -1,
                previous: $e
            );
        }
    }

    /**
     * Build HTML5 preview for a GDevelop project
     */
    public function buildPreview(string $sessionId, string $gameJsonPath): PreviewResult
    {
        $startTime = microtime(true);
        
        try {
            return $this->errorRecovery->executeWithRetry(
                operation: function () use ($sessionId, $gameJsonPath, $startTime) {
                    $outputPath = $this->sessionsPath . DIRECTORY_SEPARATOR . $sessionId . DIRECTORY_SEPARATOR . 'preview';
                    
                    if (!is_dir($outputPath)) {
                        mkdir($outputPath, 0755, true);
                    }

                    $command = [
                        $this->cliPath,
                        $gameJsonPath,
                        '--output', $outputPath,
                        '--target', 'html5',
                        '--minify', 'false'
                    ];

                    $result = $this->executeGDevelopCommand($command, dirname($gameJsonPath));

                    // Verify preview was actually created
                    if (!file_exists($outputPath . '/index.html')) {
                        throw new GDevelopPreviewException(
                            message: "Preview build completed but index.html not found",
                            sessionId: $sessionId,
                            previewPath: $outputPath
                        );
                    }

                    $buildTime = microtime(true) - $startTime;
                    
                    // Record performance metrics
                    $this->performanceMonitor->recordPreviewGeneration($buildTime, true, $sessionId);

                    return new PreviewResult(
                        success: true,
                        previewPath: $outputPath,
                        previewUrl: $this->generatePreviewUrl($sessionId),
                        error: null,
                        buildTime: time()
                    );
                },
                operationType: 'preview_build',
                context: ['session_id' => $sessionId, 'game_json_path' => $gameJsonPath]
            );
        } catch (GDevelopCliException $e) {
            $buildTime = microtime(true) - $startTime;
            $this->performanceMonitor->recordPreviewGeneration($buildTime, false, $sessionId);
            
            throw new GDevelopPreviewException(
                message: "Preview build failed: " . $e->getMessage(),
                sessionId: $sessionId,
                previewPath: null,
                buildLogs: [$e->stderr],
                previous: $e
            );
        } catch (Exception $e) {
            $buildTime = microtime(true) - $startTime;
            $this->performanceMonitor->recordPreviewGeneration($buildTime, false, $sessionId);
            
            throw new GDevelopPreviewException(
                message: "Unexpected error during preview build: " . $e->getMessage(),
                sessionId: $sessionId,
                previewPath: null,
                previous: $e
            );
        }
    }

    /**
     * Build complete HTML5 export for a GDevelop project
     */
    public function buildExport(string $sessionId, string $gameJsonPath, array $options = []): ExportResult
    {
        $startTime = microtime(true);
        
        try {
            return $this->errorRecovery->executeWithRetry(
                operation: function () use ($sessionId, $gameJsonPath, $options, $startTime) {
                    $outputPath = $this->exportsPath . DIRECTORY_SEPARATOR . $sessionId;
                    
                    if (!is_dir($outputPath)) {
                        mkdir($outputPath, 0755, true);
                    }

                    $command = [
                        $this->cliPath,
                        $gameJsonPath,
                        '--output', $outputPath,
                        '--target', 'html5'
                    ];

                    // Add export options
                    if ($options['minify'] ?? true) {
                        $command[] = '--minify';
                        $command[] = 'true';
                    }

                    if ($options['mobile_optimized'] ?? false) {
                        $command[] = '--mobile-optimized';
                    }

                    $result = $this->executeGDevelopCommand($command, dirname($gameJsonPath));

                    // Verify export was created
                    if (!file_exists($outputPath . '/index.html')) {
                        throw new GDevelopExportException(
                            message: "Export build completed but index.html not found",
                            sessionId: $sessionId,
                            exportPath: $outputPath,
                            exportOptions: $options
                        );
                    }

                    $zipPath = $this->createExportZip($sessionId, $outputPath);
                    
                    if (!file_exists($zipPath)) {
                        throw new GDevelopExportException(
                            message: "zip creation failed",
                            sessionId: $sessionId,
                            exportPath: $outputPath,
                            exportOptions: $options
                        );
                    }

                    $buildTime = microtime(true) - $startTime;
                    
                    // Record performance metrics
                    $this->performanceMonitor->recordExportGeneration($buildTime, true, $sessionId, $options);

                    return new ExportResult(
                        success: true,
                        exportPath: $outputPath,
                        zipPath: $zipPath,
                        downloadUrl: $this->generateDownloadUrl($sessionId),
                        error: null,
                        buildTime: time()
                    );
                },
                operationType: 'export_build',
                context: ['session_id' => $sessionId, 'game_json_path' => $gameJsonPath, 'options' => $options]
            );
        } catch (GDevelopCliException $e) {
            $buildTime = microtime(true) - $startTime;
            $this->performanceMonitor->recordExportGeneration($buildTime, false, $sessionId, $options);
            
            throw new GDevelopExportException(
                message: "Export build failed: " . $e->getMessage(),
                sessionId: $sessionId,
                exportPath: null,
                exportOptions: $options,
                buildLogs: [$e->stderr],
                previous: $e
            );
        } catch (Exception $e) {
            $buildTime = microtime(true) - $startTime;
            $this->performanceMonitor->recordExportGeneration($buildTime, false, $sessionId, $options);
            
            throw new GDevelopExportException(
                message: "Unexpected error during export build: " . $e->getMessage(),
                sessionId: $sessionId,
                exportPath: null,
                exportOptions: $options,
                previous: $e
            );
        }
    }

    /**
     * Validate GDevelop CLI installation
     */
    public function validateInstallation(): ValidationResult
    {
        $errors = [];
        $warnings = [];

        // Check GDevelop CLI - we expect it to fail without game.json, but it should be found
        $cliResult = $this->executeGDevelopCommand([$this->cliPath, '--version']);
        if ($cliResult->exitCode === 127 || str_contains($cliResult->errorOutput, 'not recognized') || str_contains($cliResult->errorOutput, 'command not found')) {
            $errors[] = "GDevelop CLI not found or not working: {$cliResult->errorOutput}";
        } elseif (str_contains($cliResult->errorOutput, 'ENOENT') && str_contains($cliResult->errorOutput, 'game.json')) {
            // This is expected - CLI is working but needs a game.json file
            // We'll consider this as success for validation purposes
        } elseif (!$cliResult->success && !str_contains($cliResult->errorOutput, 'game.json')) {
            $errors[] = "GDevelop CLI error: {$cliResult->errorOutput}";
        }

        // Check Node.js version
        $nodeResult = $this->executeGDevelopCommand(['node', '--version']);
        if (!$nodeResult->success) {
            $errors[] = "Node.js not found";
        } else {
            $version = trim($nodeResult->output);
            $versionNumber = (int) str_replace(['v', '.'], ['', ''], explode('.', $version)[0]);
            if ($versionNumber < 16) {
                $warnings[] = "Node.js version {$version} detected. Version 16+ recommended.";
            }
        }

        // Check directory permissions
        if (!is_writable($this->sessionsPath)) {
            $errors[] = "Sessions directory not writable: {$this->sessionsPath}";
        }

        if (!is_writable($this->exportsPath)) {
            $errors[] = "Exports directory not writable: {$this->exportsPath}";
        }

        // Determine CLI version - if CLI is working but failed due to missing game.json, that's OK
        $cliVersion = null;
        if ($cliResult->success) {
            $cliVersion = trim($cliResult->output);
        } elseif (str_contains($cliResult->errorOutput, 'game.json')) {
            $cliVersion = 'gdexporter (working - requires game.json file)';
        }

        return new ValidationResult(
            valid: empty($errors),
            errors: $errors,
            warnings: $warnings,
            cliVersion: $cliVersion
        );
    }

    /**
     * Create ZIP file from export directory
     */
    private function createExportZip(string $sessionId, string $exportPath): string
    {
        $zipPath = $this->exportsPath . DIRECTORY_SEPARATOR . $sessionId . '.zip';
        
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === TRUE) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($exportPath),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($files as $name => $file) {
                if (!$file->isDir()) {
                    $filePath = $file->getRealPath();
                    $relativePath = substr($filePath, strlen($exportPath) + 1);
                    $zip->addFile($filePath, $relativePath);
                }
            }
            $zip->close();
        }

        return $zipPath;
    }

    /**
     * Generate preview URL for a session
     */
    private function generatePreviewUrl(string $sessionId): string
    {
        try {
            return route('gdevelop.preview', ['sessionId' => $sessionId]);
        } catch (\Exception $e) {
            // Fallback for testing or when routes are not defined
            return "/gdevelop/preview/{$sessionId}";
        }
    }

    /**
     * Generate download URL for an export
     */
    private function generateDownloadUrl(string $sessionId): string
    {
        try {
            return route('gdevelop.download', ['sessionId' => $sessionId]);
        } catch (\Exception $e) {
            // Fallback for testing or when routes are not defined
            return "/gdevelop/download/{$sessionId}";
        }
    }
}

/**
 * Command execution result
 */
class CommandResult
{
    public function __construct(
        public bool $success,
        public int $exitCode,
        public string $output,
        public string $errorOutput,
        public string $command
    ) {}
}

/**
 * Preview build result
 */
class PreviewResult
{
    public function __construct(
        public bool $success,
        public ?string $previewPath,
        public ?string $previewUrl,
        public ?string $error,
        public int $buildTime
    ) {}
}

/**
 * Export build result
 */
class ExportResult
{
    public function __construct(
        public bool $success,
        public ?string $exportPath,
        public ?string $zipPath,
        public ?string $downloadUrl,
        public ?string $error,
        public int $buildTime
    ) {}
}

/**
 * Installation validation result
 */
class ValidationResult
{
    public function __construct(
        public bool $valid,
        public array $errors,
        public array $warnings,
        public ?string $cliVersion
    ) {}
}