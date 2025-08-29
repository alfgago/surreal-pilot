<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class PerformanceMonitoringService
{
    private const CACHE_PREFIX = 'performance_metrics:';
    private const METRICS_TTL = 3600; // 1 hour

    /**
     * Track page load performance
     */
    public function trackPageLoad(Request $request, float $loadTime): void
    {
        $route = $request->route()?->getName() ?? $request->path();
        $metrics = $this->getMetrics($route);
        
        $metrics['total_loads']++;
        $metrics['total_time'] += $loadTime;
        $metrics['average_time'] = $metrics['total_time'] / $metrics['total_loads'];
        $metrics['last_load'] = now()->toISOString();
        
        // Track slow loads (>2s)
        if ($loadTime > 2000) {
            $metrics['slow_loads']++;
            $metrics['slow_load_percentage'] = ($metrics['slow_loads'] / $metrics['total_loads']) * 100;
            
            Log::warning('Slow page load detected', [
                'route' => $route,
                'load_time' => $loadTime,
                'user_agent' => $request->userAgent(),
                'ip' => $request->ip()
            ]);
        }
        
        // Update min/max times
        $metrics['min_time'] = min($metrics['min_time'] ?? $loadTime, $loadTime);
        $metrics['max_time'] = max($metrics['max_time'] ?? $loadTime, $loadTime);
        
        $this->storeMetrics($route, $metrics);
    }

    /**
     * Track API response performance
     */
    public function trackApiResponse(string $endpoint, float $responseTime, int $statusCode): void
    {
        $key = "api:{$endpoint}";
        $metrics = $this->getMetrics($key);
        
        $metrics['total_requests']++;
        $metrics['total_time'] += $responseTime;
        $metrics['average_time'] = $metrics['total_time'] / $metrics['total_requests'];
        $metrics['last_request'] = now()->toISOString();
        
        // Track by status code
        $statusGroup = intval($statusCode / 100) * 100;
        $metrics['status_codes'][$statusGroup] = ($metrics['status_codes'][$statusGroup] ?? 0) + 1;
        
        // Track slow API calls (>500ms)
        if ($responseTime > 500) {
            $metrics['slow_requests']++;
            $metrics['slow_request_percentage'] = ($metrics['slow_requests'] / $metrics['total_requests']) * 100;
            
            Log::warning('Slow API response detected', [
                'endpoint' => $endpoint,
                'response_time' => $responseTime,
                'status_code' => $statusCode
            ]);
        }
        
        // Track errors (4xx, 5xx)
        if ($statusCode >= 400) {
            $metrics['error_requests']++;
            $metrics['error_rate'] = ($metrics['error_requests'] / $metrics['total_requests']) * 100;
        }
        
        $this->storeMetrics($key, $metrics);
    }

    /**
     * Track database query performance
     */
    public function trackDatabaseQuery(string $query, float $executionTime): void
    {
        $key = 'database:queries';
        $metrics = $this->getMetrics($key);
        
        $metrics['total_queries']++;
        $metrics['total_time'] += $executionTime;
        $metrics['average_time'] = $metrics['total_time'] / $metrics['total_queries'];
        
        // Track slow queries (>100ms)
        if ($executionTime > 100) {
            $metrics['slow_queries']++;
            $metrics['slow_query_percentage'] = ($metrics['slow_queries'] / $metrics['total_queries']) * 100;
            
            Log::warning('Slow database query detected', [
                'query' => substr($query, 0, 200) . '...',
                'execution_time' => $executionTime
            ]);
        }
        
        $this->storeMetrics($key, $metrics);
    }

    /**
     * Track memory usage
     */
    public function trackMemoryUsage(string $context = 'general'): void
    {
        $memoryUsage = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);
        
        $key = "memory:{$context}";
        $metrics = $this->getMetrics($key);
        
        $metrics['current_usage'] = $memoryUsage;
        $metrics['peak_usage'] = max($metrics['peak_usage'] ?? 0, $peakMemory);
        $metrics['average_usage'] = isset($metrics['total_measurements']) 
            ? (($metrics['average_usage'] * $metrics['total_measurements']) + $memoryUsage) / ($metrics['total_measurements'] + 1)
            : $memoryUsage;
        $metrics['total_measurements'] = ($metrics['total_measurements'] ?? 0) + 1;
        $metrics['last_measurement'] = now()->toISOString();
        
        // Alert on high memory usage (>128MB)
        if ($memoryUsage > 128 * 1024 * 1024) {
            Log::warning('High memory usage detected', [
                'context' => $context,
                'memory_usage' => $this->formatBytes($memoryUsage),
                'peak_memory' => $this->formatBytes($peakMemory)
            ]);
        }
        
        $this->storeMetrics($key, $metrics);
    }

    /**
     * Get performance metrics for a specific key
     */
    public function getMetrics(string $key): array
    {
        return Cache::get(self::CACHE_PREFIX . $key, [
            'total_loads' => 0,
            'total_requests' => 0,
            'total_queries' => 0,
            'total_time' => 0,
            'average_time' => 0,
            'min_time' => null,
            'max_time' => null,
            'slow_loads' => 0,
            'slow_requests' => 0,
            'slow_queries' => 0,
            'error_requests' => 0,
            'slow_load_percentage' => 0,
            'slow_request_percentage' => 0,
            'slow_query_percentage' => 0,
            'error_rate' => 0,
            'status_codes' => [],
            'created_at' => now()->toISOString()
        ]);
    }

    /**
     * Store metrics in cache
     */
    private function storeMetrics(string $key, array $metrics): void
    {
        Cache::put(self::CACHE_PREFIX . $key, $metrics, self::METRICS_TTL);
    }

    /**
     * Get all performance metrics
     */
    public function getAllMetrics(): array
    {
        $cacheKeys = Cache::getRedis()->keys(self::CACHE_PREFIX . '*');
        $metrics = [];
        
        foreach ($cacheKeys as $cacheKey) {
            $key = str_replace(self::CACHE_PREFIX, '', $cacheKey);
            $metrics[$key] = $this->getMetrics($key);
        }
        
        return $metrics;
    }

    /**
     * Generate performance report
     */
    public function generateReport(): array
    {
        $allMetrics = $this->getAllMetrics();
        
        $report = [
            'generated_at' => now()->toISOString(),
            'summary' => [
                'total_pages' => 0,
                'total_api_endpoints' => 0,
                'average_page_load' => 0,
                'average_api_response' => 0,
                'slow_pages' => 0,
                'slow_apis' => 0,
                'error_rate' => 0
            ],
            'pages' => [],
            'apis' => [],
            'database' => [],
            'memory' => [],
            'alerts' => []
        ];
        
        foreach ($allMetrics as $key => $metrics) {
            if (str_starts_with($key, 'api:')) {
                $report['apis'][$key] = $metrics;
                $report['summary']['total_api_endpoints']++;
                $report['summary']['average_api_response'] += $metrics['average_time'] ?? 0;
                $report['summary']['slow_apis'] += $metrics['slow_requests'] ?? 0;
                $report['summary']['error_rate'] += $metrics['error_rate'] ?? 0;
            } elseif (str_starts_with($key, 'database:')) {
                $report['database'][$key] = $metrics;
            } elseif (str_starts_with($key, 'memory:')) {
                $report['memory'][$key] = $metrics;
            } else {
                $report['pages'][$key] = $metrics;
                $report['summary']['total_pages']++;
                $report['summary']['average_page_load'] += $metrics['average_time'] ?? 0;
                $report['summary']['slow_pages'] += $metrics['slow_loads'] ?? 0;
            }
            
            // Generate alerts for problematic metrics
            if (($metrics['slow_load_percentage'] ?? 0) > 10) {
                $report['alerts'][] = [
                    'type' => 'performance',
                    'severity' => 'high',
                    'message' => "High slow load percentage for {$key}: {$metrics['slow_load_percentage']}%"
                ];
            }
            
            if (($metrics['error_rate'] ?? 0) > 5) {
                $report['alerts'][] = [
                    'type' => 'error',
                    'severity' => 'high',
                    'message' => "High error rate for {$key}: {$metrics['error_rate']}%"
                ];
            }
        }
        
        // Calculate averages
        if ($report['summary']['total_pages'] > 0) {
            $report['summary']['average_page_load'] /= $report['summary']['total_pages'];
        }
        
        if ($report['summary']['total_api_endpoints'] > 0) {
            $report['summary']['average_api_response'] /= $report['summary']['total_api_endpoints'];
            $report['summary']['error_rate'] /= $report['summary']['total_api_endpoints'];
        }
        
        return $report;
    }

    /**
     * Clear all performance metrics
     */
    public function clearMetrics(): void
    {
        $cacheKeys = Cache::getRedis()->keys(self::CACHE_PREFIX . '*');
        
        foreach ($cacheKeys as $cacheKey) {
            Cache::forget(str_replace(self::CACHE_PREFIX, '', $cacheKey));
        }
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;
        
        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }
        
        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }
}