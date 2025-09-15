<?php

namespace App\Http\Middleware;

use App\Services\GDevelopPerformanceMonitorService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class GDevelopPerformanceMiddleware
{
    public function __construct(
        private GDevelopPerformanceMonitorService $performanceMonitor
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if this is a GDevelop-related request
        if (!$this->isGDevelopRequest($request)) {
            return $next($request);
        }

        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        // Check resource limits before processing
        if (!$this->checkResourceLimits()) {
            return response()->json([
                'error' => 'Server is currently under high load. Please try again later.',
                'code' => 'RESOURCE_LIMIT_EXCEEDED'
            ], 503);
        }

        // Process the request
        $response = $next($request);

        // Record performance metrics
        $this->recordRequestMetrics($request, $startTime, $startMemory, $response);

        return $response;
    }

    /**
     * Check if this is a GDevelop-related request
     */
    private function isGDevelopRequest(Request $request): bool
    {
        $path = $request->path();
        
        return str_starts_with($path, 'api/gdevelop') || 
               str_starts_with($path, 'gdevelop/') ||
               $request->has('engine') && $request->get('engine') === 'gdevelop';
    }

    /**
     * Check resource limits
     */
    private function checkResourceLimits(): bool
    {
        try {
            // Check memory usage
            $memoryLimit = $this->parseMemoryLimit(config('gdevelop.performance.memory_limit', '512M'));
            $currentMemory = memory_get_usage(true);
            
            if ($currentMemory > $memoryLimit * 0.9) { // 90% threshold
                Log::warning('GDevelop memory limit approaching', [
                    'current_memory' => $currentMemory,
                    'memory_limit' => $memoryLimit,
                    'usage_percentage' => ($currentMemory / $memoryLimit) * 100
                ]);
                return false;
            }

            // Check concurrent operations
            $maxConcurrent = config('gdevelop.performance.max_concurrent_operations', 5);
            $currentOperations = $this->getCurrentOperationCount();
            
            if ($currentOperations >= $maxConcurrent) {
                Log::warning('GDevelop concurrent operations limit reached', [
                    'current_operations' => $currentOperations,
                    'max_concurrent' => $maxConcurrent
                ]);
                return false;
            }

            // Check disk space
            $diskThreshold = $this->parseDiskSpace(config('gdevelop.performance.disk_space_threshold', '1GB'));
            $availableSpace = disk_free_space(storage_path());
            
            if ($availableSpace < $diskThreshold) {
                Log::warning('GDevelop disk space threshold reached', [
                    'available_space' => $availableSpace,
                    'threshold' => $diskThreshold
                ]);
                return false;
            }

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to check resource limits', [
                'error' => $e->getMessage()
            ]);
            
            // Allow request to proceed if we can't check limits
            return true;
        }
    }

    /**
     * Record request performance metrics
     */
    private function recordRequestMetrics(
        Request $request, 
        float $startTime, 
        int $startMemory, 
        Response $response
    ): void {
        try {
            $executionTime = microtime(true) - $startTime;
            $memoryUsed = memory_get_usage(true) - $startMemory;
            $success = $response->getStatusCode() < 400;

            // Determine operation type from request
            $operationType = $this->getOperationType($request);

            // Record the metric
            $this->performanceMonitor->recordMetric('api_requests', [
                'operation_type' => $operationType,
                'execution_time' => $executionTime,
                'memory_used' => $memoryUsed,
                'status_code' => $response->getStatusCode(),
                'success' => $success,
                'method' => $request->method(),
                'path' => $request->path(),
                'timestamp' => microtime(true)
            ]);

            // Log slow requests
            $slowThreshold = config('gdevelop.performance.slow_operation_threshold', 30);
            if ($executionTime > $slowThreshold) {
                Log::warning('Slow GDevelop API request detected', [
                    'operation_type' => $operationType,
                    'execution_time' => $executionTime,
                    'memory_used' => $memoryUsed,
                    'path' => $request->path(),
                    'method' => $request->method()
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to record request metrics', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get operation type from request
     */
    private function getOperationType(Request $request): string
    {
        $path = $request->path();
        
        if (str_contains($path, 'chat')) {
            return 'chat';
        } elseif (str_contains($path, 'preview')) {
            return 'preview';
        } elseif (str_contains($path, 'export')) {
            return 'export';
        } elseif (str_contains($path, 'download')) {
            return 'download';
        } else {
            return 'other';
        }
    }

    /**
     * Parse memory limit string to bytes
     */
    private function parseMemoryLimit(string $memoryLimit): int
    {
        $unit = strtoupper(substr($memoryLimit, -1));
        $value = (int) substr($memoryLimit, 0, -1);
        
        return match ($unit) {
            'K' => $value * 1024,
            'M' => $value * 1024 * 1024,
            'G' => $value * 1024 * 1024 * 1024,
            default => (int) $memoryLimit
        };
    }

    /**
     * Parse disk space string to bytes
     */
    private function parseDiskSpace(string $diskSpace): int
    {
        $unit = strtoupper(substr($diskSpace, -2));
        $value = (int) substr($diskSpace, 0, -2);
        
        return match ($unit) {
            'KB' => $value * 1024,
            'MB' => $value * 1024 * 1024,
            'GB' => $value * 1024 * 1024 * 1024,
            'TB' => $value * 1024 * 1024 * 1024 * 1024,
            default => (int) $diskSpace
        };
    }

    /**
     * Get current operation count (simplified implementation)
     */
    private function getCurrentOperationCount(): int
    {
        // This is a simplified implementation
        // In production, you might want to use Redis or another shared storage
        // to track concurrent operations across multiple servers
        
        try {
            // Count active processes, queue jobs, etc.
            // For now, return a placeholder value
            return 0;
        } catch (\Exception $e) {
            return 0;
        }
    }
}