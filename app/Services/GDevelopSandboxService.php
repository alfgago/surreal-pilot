<?php

namespace App\Services;

use App\Exceptions\GDevelopCliException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\Exception\ProcessTimedOutException;

class GDevelopSandboxService
{
    /**
     * Maximum execution time for CLI commands (in seconds)
     */
    private const MAX_EXECUTION_TIME = 300; // 5 minutes

    /**
     * Maximum memory usage for CLI processes (in MB)
     */
    private const MAX_MEMORY_USAGE = 512;

    /**
     * Allowed GDevelop CLI commands
     */
    private const ALLOWED_COMMANDS = [
        'export',
        'build',
        'preview'
    ];

    /**
     * Execute GDevelop CLI command in sandboxed environment
     *
     * @param string $command
     * @param array $arguments
     * @param string $workingDirectory
     * @return array
     * @throws GDevelopCliException
     */
    public function executeSandboxedCommand(string $command, array $arguments, string $workingDirectory): array
    {
        // Validate command
        if (!in_array($command, self::ALLOWED_COMMANDS)) {
            throw new GDevelopCliException("Command '{$command}' is not allowed");
        }

        // Validate working directory
        $this->validateWorkingDirectory($workingDirectory);

        // Sanitize arguments
        $sanitizedArguments = $this->sanitizeArguments($arguments);

        // Build full command
        $fullCommand = $this->buildCommand($command, $sanitizedArguments);

        Log::info('Executing sandboxed GDevelop command', [
            'command' => $command,
            'arguments' => $sanitizedArguments,
            'working_directory' => $workingDirectory
        ]);

        try {
            // Execute with resource limits
            $result = Process::timeout(self::MAX_EXECUTION_TIME)
                ->path($workingDirectory)
                ->run($fullCommand);

            if (!$result->successful()) {
                Log::error('GDevelop command failed', [
                    'command' => $fullCommand,
                    'exit_code' => $result->exitCode(),
                    'error_output' => $result->errorOutput()
                ]);

                throw new GDevelopCliException(
                    "Command failed with exit code {$result->exitCode()}: {$result->errorOutput()}"
                );
            }

            Log::info('GDevelop command completed successfully', [
                'command' => $command,
                'execution_time' => $result->duration()
            ]);

            return [
                'success' => true,
                'output' => $result->output(),
                'error_output' => $result->errorOutput(),
                'exit_code' => $result->exitCode(),
                'duration' => $result->duration()
            ];

        } catch (ProcessTimedOutException $e) {
            Log::error('GDevelop command timed out', [
                'command' => $fullCommand,
                'timeout' => self::MAX_EXECUTION_TIME
            ]);

            throw new GDevelopCliException("Command timed out after " . self::MAX_EXECUTION_TIME . " seconds");
        }
    }

    /**
     * Validate working directory
     */
    private function validateWorkingDirectory(string $directory): void
    {
        // Ensure directory exists
        if (!is_dir($directory)) {
            throw new GDevelopCliException("Working directory does not exist: {$directory}");
        }

        // Ensure directory is within allowed paths
        $realPath = realpath($directory);
        $allowedBasePaths = [
            realpath(storage_path('gdevelop')),
            realpath(storage_path('workspaces'))
        ];

        $isAllowed = false;
        foreach ($allowedBasePaths as $basePath) {
            if ($basePath && strpos($realPath, $basePath) === 0) {
                $isAllowed = true;
                break;
            }
        }

        if (!$isAllowed) {
            throw new GDevelopCliException("Working directory is not in allowed path: {$directory}");
        }

        // Check for directory traversal attempts
        if (strpos($directory, '..') !== false) {
            throw new GDevelopCliException("Directory traversal detected in path: {$directory}");
        }
    }

    /**
     * Sanitize command arguments
     */
    private function sanitizeArguments(array $arguments): array
    {
        $sanitized = [];

        foreach ($arguments as $key => $value) {
            // Remove potentially dangerous characters
            $sanitizedKey = preg_replace('/[^a-zA-Z0-9\-_]/', '', $key);
            $sanitizedValue = $this->sanitizeArgumentValue($value);

            if ($sanitizedKey && $sanitizedValue !== null) {
                $sanitized[$sanitizedKey] = $sanitizedValue;
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize individual argument value
     */
    private function sanitizeArgumentValue($value): ?string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }

        $stringValue = (string) $value;

        // Remove shell metacharacters
        $dangerous = ['|', '&', ';', '`', '$', '(', ')', '<', '>', '"', "'", '\\', "\n", "\r"];
        $sanitized = str_replace($dangerous, '', $stringValue);

        // Limit length
        if (strlen($sanitized) > 1000) {
            $sanitized = substr($sanitized, 0, 1000);
        }

        return $sanitized;
    }

    /**
     * Build the full command string
     */
    private function buildCommand(string $command, array $arguments): string
    {
        $gdevelopPath = config('gdevelop.cli_path', 'gdevelop-cli');
        
        $commandParts = [$gdevelopPath, $command];

        foreach ($arguments as $key => $value) {
            if (is_numeric($key)) {
                $commandParts[] = escapeshellarg($value);
            } else {
                $commandParts[] = "--{$key}";
                if ($value !== '') {
                    $commandParts[] = escapeshellarg($value);
                }
            }
        }

        return implode(' ', $commandParts);
    }

    /**
     * Create isolated workspace for command execution
     */
    public function createIsolatedWorkspace(string $sessionId): string
    {
        $basePath = storage_path('gdevelop/isolated');
        $workspacePath = $basePath . '/' . $sessionId;

        // Create directory if it doesn't exist
        if (!is_dir($workspacePath)) {
            if (!mkdir($workspacePath, 0755, true)) {
                throw new GDevelopCliException("Failed to create isolated workspace: {$workspacePath}");
            }
        }

        // Set proper permissions
        chmod($workspacePath, 0755);

        Log::info('Created isolated workspace', [
            'session_id' => $sessionId,
            'workspace_path' => $workspacePath
        ]);

        return $workspacePath;
    }

    /**
     * Clean up isolated workspace
     */
    public function cleanupIsolatedWorkspace(string $sessionId): void
    {
        $workspacePath = storage_path('gdevelop/isolated/' . $sessionId);

        if (is_dir($workspacePath)) {
            $this->recursiveDelete($workspacePath);
            
            Log::info('Cleaned up isolated workspace', [
                'session_id' => $sessionId,
                'workspace_path' => $workspacePath
            ]);
        }
    }

    /**
     * Recursively delete directory
     */
    private function recursiveDelete(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = array_diff(scandir($directory), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $directory . '/' . $file;
            
            if (is_dir($path)) {
                $this->recursiveDelete($path);
            } else {
                unlink($path);
            }
        }
        
        rmdir($directory);
    }

    /**
     * Monitor resource usage during command execution
     */
    public function monitorResourceUsage(int $processId): array
    {
        // Get memory usage (Linux/Unix systems)
        if (function_exists('proc_get_status')) {
            $status = proc_get_status($processId);
            
            if ($status && isset($status['memory'])) {
                $memoryUsageMB = $status['memory'] / 1024 / 1024;
                
                if ($memoryUsageMB > self::MAX_MEMORY_USAGE) {
                    Log::warning('GDevelop process exceeding memory limit', [
                        'process_id' => $processId,
                        'memory_usage_mb' => $memoryUsageMB,
                        'limit_mb' => self::MAX_MEMORY_USAGE
                    ]);
                }
                
                return [
                    'memory_usage_mb' => $memoryUsageMB,
                    'within_limits' => $memoryUsageMB <= self::MAX_MEMORY_USAGE
                ];
            }
        }

        return [
            'memory_usage_mb' => 0,
            'within_limits' => true
        ];
    }
}