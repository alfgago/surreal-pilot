<?php

namespace App\Services;

use App\Exceptions\GDevelop\GDevelopCliException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Exception;

class GDevelopProcessPoolService
{
    private array $processPool = [];
    private int $maxPoolSize;
    private int $processTimeout;
    private array $activeProcesses = [];
    private GDevelopPerformanceMonitorService $performanceMonitor;

    public function __construct(GDevelopPerformanceMonitorService $performanceMonitor)
    {
        $this->performanceMonitor = $performanceMonitor;
        $this->maxPoolSize = config('gdevelop.performance.process_pool_size', 3);
        $this->processTimeout = config('gdevelop.performance.process_timeout', 300);
        
        Log::info('Initialized GDevelop process pool', [
            'max_pool_size' => $this->maxPoolSize,
            'process_timeout' => $this->processTimeout
        ]);
    }

    /**
     * Execute a GDevelop CLI command using process pooling
     */
    public function executeCommand(array $command, ?string $workingDirectory = null): CommandResult
    {
        $startTime = microtime(true);
        
        try {
            Log::debug('Executing command with process pool', [
                'command' => $command,
                'working_directory' => $workingDirectory,
                'active_processes' => count($this->activeProcesses),
                'pool_size' => count($this->processPool)
            ]);

            // Get or create a process from the pool
            $process = $this->getPooledProcess($command, $workingDirectory);
            
            // Execute the process
            $result = $this->executeProcess($process, $command);
            
            // Return process to pool if it's still usable
            $this->returnProcessToPool($process);
            
            // Record performance metrics
            $executionTime = microtime(true) - $startTime;
            $this->performanceMonitor->recordCliExecution($executionTime, $result->success);
            
            return $result;
            
        } catch (Exception $e) {
            $executionTime = microtime(true) - $startTime;
            $this->performanceMonitor->recordCliExecution($executionTime, false);
            
            Log::error('Process pool command execution failed', [
                'command' => $command,
                'error' => $e->getMessage(),
                'execution_time' => $executionTime
            ]);
            
            throw $e;
        }
    }

    /**
     * Execute multiple commands concurrently using the process pool
     */
    public function executeConcurrentCommands(array $commands): array
    {
        $startTime = microtime(true);
        $results = [];
        $processes = [];
        
        try {
            Log::info('Executing concurrent commands', [
                'command_count' => count($commands),
                'max_pool_size' => $this->maxPoolSize
            ]);

            // Start all processes
            foreach ($commands as $index => $commandData) {
                $command = $commandData['command'];
                $workingDirectory = $commandData['working_directory'] ?? null;
                
                $process = $this->createProcess($command, $workingDirectory);
                $process->start();
                
                $processes[$index] = [
                    'process' => $process,
                    'command' => $command,
                    'start_time' => microtime(true)
                ];
                
                $this->activeProcesses[] = $process;
            }

            // Wait for all processes to complete
            foreach ($processes as $index => $processData) {
                $process = $processData['process'];
                $command = $processData['command'];
                $processStartTime = $processData['start_time'];
                
                $process->wait();
                
                $executionTime = microtime(true) - $processStartTime;
                $success = $process->isSuccessful();
                
                $results[$index] = new CommandResult(
                    success: $success,
                    exitCode: $process->getExitCode(),
                    output: $process->getOutput(),
                    errorOutput: $process->getErrorOutput(),
                    command: implode(' ', $command)
                );
                
                // Record individual process performance
                $this->performanceMonitor->recordCliExecution($executionTime, $success);
                
                // Remove from active processes
                $this->removeFromActiveProcesses($process);
            }

            $totalExecutionTime = microtime(true) - $startTime;
            
            Log::info('Completed concurrent command execution', [
                'command_count' => count($commands),
                'total_execution_time' => $totalExecutionTime,
                'successful_commands' => count(array_filter($results, fn($r) => $r->success))
            ]);
            
            return $results;
            
        } catch (Exception $e) {
            // Clean up any remaining processes
            foreach ($processes as $processData) {
                if (isset($processData['process']) && $processData['process']->isRunning()) {
                    $processData['process']->stop();
                    $this->removeFromActiveProcesses($processData['process']);
                }
            }
            
            Log::error('Concurrent command execution failed', [
                'command_count' => count($commands),
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Get process pool statistics
     */
    public function getPoolStatistics(): array
    {
        return [
            'pool_size' => count($this->processPool),
            'max_pool_size' => $this->maxPoolSize,
            'active_processes' => count($this->activeProcesses),
            'available_processes' => count($this->processPool) - count($this->activeProcesses),
            'process_timeout' => $this->processTimeout
        ];
    }

    /**
     * Clear the process pool and terminate all processes
     */
    public function clearPool(): void
    {
        try {
            Log::info('Clearing GDevelop process pool', [
                'pool_size' => count($this->processPool),
                'active_processes' => count($this->activeProcesses)
            ]);

            // Stop all active processes
            foreach ($this->activeProcesses as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }

            // Clear the pools
            $this->processPool = [];
            $this->activeProcesses = [];
            
            Log::info('Process pool cleared successfully');
            
        } catch (Exception $e) {
            Log::error('Failed to clear process pool', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get or create a pooled process
     */
    private function getPooledProcess(array $command, ?string $workingDirectory): Process
    {
        // Check if we have available processes in the pool
        $availableProcesses = array_filter($this->processPool, function($process) {
            return !in_array($process, $this->activeProcesses) && !$process->isRunning();
        });

        if (!empty($availableProcesses)) {
            $process = array_shift($availableProcesses);
            Log::debug('Reusing pooled process');
            return $process;
        }

        // Create new process if pool is not at capacity
        if (count($this->processPool) < $this->maxPoolSize) {
            $process = $this->createProcess($command, $workingDirectory);
            $this->processPool[] = $process;
            
            Log::debug('Created new pooled process', [
                'pool_size' => count($this->processPool)
            ]);
            
            return $process;
        }

        // If pool is at capacity, wait for a process to become available
        return $this->waitForAvailableProcess($command, $workingDirectory);
    }

    /**
     * Create a new process
     */
    private function createProcess(array $command, ?string $workingDirectory): Process
    {
        $process = new Process($command, $workingDirectory, null, null, $this->processTimeout);
        
        // Set process options for better performance
        $process->setOptions([
            'suppress_errors' => false,
            'bypass_shell' => true,
        ]);
        
        return $process;
    }

    /**
     * Execute a process and return the result
     */
    private function executeProcess(Process $process, array $command): CommandResult
    {
        try {
            // Mark process as active
            if (!in_array($process, $this->activeProcesses)) {
                $this->activeProcesses[] = $process;
            }

            // Run the process
            $process->run();

            $result = new CommandResult(
                success: $process->isSuccessful(),
                exitCode: $process->getExitCode(),
                output: $process->getOutput(),
                errorOutput: $process->getErrorOutput(),
                command: implode(' ', $command)
            );

            if (!$result->success) {
                throw new GDevelopCliException(
                    message: "GDevelop CLI command failed: " . $process->getErrorOutput(),
                    command: implode(' ', $command),
                    stdout: $process->getOutput(),
                    stderr: $process->getErrorOutput(),
                    exitCode: $process->getExitCode()
                );
            }

            return $result;
            
        } finally {
            // Remove from active processes
            $this->removeFromActiveProcesses($process);
        }
    }

    /**
     * Return a process to the pool for reuse
     */
    private function returnProcessToPool(Process $process): void
    {
        // Only return healthy processes to the pool
        if (!$process->isRunning() && $process->getExitCode() === 0) {
            // Process is already in the pool, just remove from active
            $this->removeFromActiveProcesses($process);
            
            Log::debug('Returned process to pool');
        } else {
            // Remove unhealthy process from pool
            $this->removeProcessFromPool($process);
            
            Log::debug('Removed unhealthy process from pool');
        }
    }

    /**
     * Wait for an available process in the pool
     */
    private function waitForAvailableProcess(array $command, ?string $workingDirectory): Process
    {
        $maxWaitTime = 30; // 30 seconds
        $waitInterval = 0.1; // 100ms
        $waitedTime = 0;

        while ($waitedTime < $maxWaitTime) {
            // Check for available processes
            foreach ($this->processPool as $process) {
                if (!in_array($process, $this->activeProcesses) && !$process->isRunning()) {
                    Log::debug('Found available process after waiting', [
                        'waited_time' => $waitedTime
                    ]);
                    return $process;
                }
            }

            usleep($waitInterval * 1000000); // Convert to microseconds
            $waitedTime += $waitInterval;
        }

        // If we couldn't get a pooled process, create a new temporary one
        Log::warning('Creating temporary process due to pool exhaustion', [
            'waited_time' => $waitedTime,
            'pool_size' => count($this->processPool),
            'active_processes' => count($this->activeProcesses)
        ]);

        return $this->createProcess($command, $workingDirectory);
    }

    /**
     * Remove process from active processes array
     */
    private function removeFromActiveProcesses(Process $process): void
    {
        $this->activeProcesses = array_filter($this->activeProcesses, function($activeProcess) use ($process) {
            return $activeProcess !== $process;
        });
    }

    /**
     * Remove process from the pool entirely
     */
    private function removeProcessFromPool(Process $process): void
    {
        $this->processPool = array_filter($this->processPool, function($pooledProcess) use ($process) {
            return $pooledProcess !== $process;
        });
        
        $this->removeFromActiveProcesses($process);
    }
}