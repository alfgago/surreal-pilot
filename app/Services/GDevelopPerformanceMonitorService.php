<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Exception;

class GDevelopPerformanceMonitorService
{
    private int $metricsTtl;
    private int $metricsHistoryLimit;
    private bool $monitoringEnabled;
    private bool $performanceAlertsEnabled;
    private float $slowOperationThreshold;

    public function __construct()
    {
        $this->monitoringEnabled = config('gdevelop.performance.monitoring_enabled', true);
        $this->metricsTtl = config('gdevelop.performance.metrics_ttl', 86400);
        $this->metricsHistoryLimit = config('gdevelop.performance.metrics_history_limit', 1000);
        $this->performanceAlertsEnabled = config('gdevelop.performance.performance_alerts_enabled', true);
        $this->slowOperationThreshold = config('gdevelop.performance.slow_operation_threshold', 30);
    }

    /**
     * Record preview generation time
     */
    public function recordPreviewGeneration(float $generationTime, bool $success, string $sessionId): void
    {
        if (!$this->monitoringEnabled) {
            return;
        }

        try {
            $this->recordMetricInternal('preview_generation', [
                'generation_time' => $generationTime,
                'success' => $success,
                'session_id' => $sessionId,
                'timestamp' => microtime(true)
            ]);

            // Check for slow operations and trigger alerts if enabled
            if ($this->performanceAlertsEnabled && $generationTime > $this->slowOperationThreshold) {
                $this->triggerSlowOperationAlert('preview_generation', $generationTime, $sessionId);
            }
            
            Log::debug('Recorded preview generation metric', [
                'generation_time' => $generationTime,
                'success' => $success,
                'session_id' => $sessionId
            ]);
        } catch (Exception $e) {
            Log::warning('Failed to record preview generation metric', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Record export generation time
     */
    public function recordExportGeneration(float $generationTime, bool $success, string $sessionId, array $options = []): void
    {
        if (!$this->monitoringEnabled) {
            return;
        }

        try {
            $this->recordMetricInternal('export_generation', [
                'generation_time' => $generationTime,
                'success' => $success,
                'session_id' => $sessionId,
                'export_options' => $options,
                'timestamp' => microtime(true)
            ]);

            // Check for slow operations and trigger alerts if enabled
            if ($this->performanceAlertsEnabled && $generationTime > $this->slowOperationThreshold) {
                $this->triggerSlowOperationAlert('export_generation', $generationTime, $sessionId);
            }
            
            Log::debug('Recorded export generation metric', [
                'generation_time' => $generationTime,
                'success' => $success,
                'session_id' => $sessionId
            ]);
        } catch (Exception $e) {
            Log::warning('Failed to record export generation metric', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Record CLI command execution time
     */
    public function recordCliExecution(float $executionTime, bool $success): void
    {
        try {
            $this->recordMetricInternal('cli_execution', [
                'execution_time' => $executionTime,
                'success' => $success,
                'timestamp' => microtime(true)
            ]);
            
            Log::debug('Recorded CLI execution metric', [
                'execution_time' => $executionTime,
                'success' => $success
            ]);
        } catch (Exception $e) {
            Log::warning('Failed to record CLI execution metric', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Record game generation time
     */
    public function recordGameGeneration(float $generationTime, bool $success, string $gameType): void
    {
        try {
            $this->recordMetricInternal('game_generation', [
                'generation_time' => $generationTime,
                'success' => $success,
                'game_type' => $gameType,
                'timestamp' => microtime(true)
            ]);
            
            Log::debug('Recorded game generation metric', [
                'generation_time' => $generationTime,
                'success' => $success,
                'game_type' => $gameType
            ]);
        } catch (Exception $e) {
            Log::warning('Failed to record game generation metric', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Record queued operation
     */
    public function recordQueuedOperation(string $operationType, string $sessionId, string $jobId): void
    {
        try {
            $operationData = [
                'operation_type' => $operationType,
                'session_id' => $sessionId,
                'job_id' => $jobId,
                'queued_at' => microtime(true),
                'status' => 'queued'
            ];
            
            Cache::put("gdevelop:queue:{$jobId}", $operationData, self::METRICS_TTL);
            
            $this->recordMetricInternal('queued_operations', $operationData);
            
            Log::debug('Recorded queued operation', [
                'operation_type' => $operationType,
                'session_id' => $sessionId,
                'job_id' => $jobId
            ]);
        } catch (Exception $e) {
            Log::warning('Failed to record queued operation', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Record operation start
     */
    public function recordOperationStart(string $jobId): void
    {
        try {
            $operationData = Cache::get("gdevelop:queue:{$jobId}");
            
            if ($operationData) {
                $operationData['started_at'] = microtime(true);
                $operationData['status'] = 'processing';
                
                Cache::put("gdevelop:queue:{$jobId}", $operationData, self::METRICS_TTL);
            }
            
            Log::debug('Recorded operation start', [
                'job_id' => $jobId
            ]);
        } catch (Exception $e) {
            Log::warning('Failed to record operation start', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Record operation completion
     */
    public function recordOperationCompletion(string $jobId, bool $success, ?string $errorMessage = null): void
    {
        try {
            $operationData = Cache::get("gdevelop:queue:{$jobId}");
            
            if ($operationData) {
                $operationData['completed_at'] = microtime(true);
                $operationData['status'] = $success ? 'completed' : 'failed';
                $operationData['success'] = $success;
                
                if ($errorMessage) {
                    $operationData['error_message'] = $errorMessage;
                }
                
                // Calculate processing time if we have start time
                if (isset($operationData['started_at'])) {
                    $operationData['processing_time'] = $operationData['completed_at'] - $operationData['started_at'];
                }
                
                Cache::put("gdevelop:queue:{$jobId}", $operationData, self::METRICS_TTL);
                
                // Record completion metric
                $this->recordMetricInternal('operation_completions', $operationData);
            }
            
            Log::debug('Recorded operation completion', [
                'job_id' => $jobId,
                'success' => $success
            ]);
        } catch (Exception $e) {
            Log::warning('Failed to record operation completion', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Record operation cancellation
     */
    public function recordOperationCancellation(string $jobId): void
    {
        try {
            $operationData = Cache::get("gdevelop:queue:{$jobId}");
            
            if ($operationData) {
                $operationData['cancelled_at'] = microtime(true);
                $operationData['status'] = 'cancelled';
                
                Cache::put("gdevelop:queue:{$jobId}", $operationData, self::METRICS_TTL);
            }
            
            Log::debug('Recorded operation cancellation', [
                'job_id' => $jobId
            ]);
        } catch (Exception $e) {
            Log::warning('Failed to record operation cancellation', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Record priority operation
     */
    public function recordPriorityOperation(string $operationType, float $processingTime, bool $success): void
    {
        try {
            $this->recordMetricInternal('priority_operations', [
                'operation_type' => $operationType,
                'processing_time' => $processingTime,
                'success' => $success,
                'timestamp' => microtime(true)
            ]);
            
            Log::debug('Recorded priority operation', [
                'operation_type' => $operationType,
                'processing_time' => $processingTime,
                'success' => $success
            ]);
        } catch (Exception $e) {
            Log::warning('Failed to record priority operation', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get queued operation details
     */
    public function getQueuedOperationDetails(string $jobId): ?array
    {
        try {
            return Cache::get("gdevelop:queue:{$jobId}");
        } catch (Exception $e) {
            Log::warning('Failed to get queued operation details', [
                'job_id' => $jobId,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }

    /**
     * Get performance statistics
     */
    public function getPerformanceStatistics(): array
    {
        try {
            return [
                'preview_generation' => $this->getMetricStatistics('preview_generation'),
                'export_generation' => $this->getMetricStatistics('export_generation'),
                'cli_execution' => $this->getMetricStatistics('cli_execution'),
                'game_generation' => $this->getMetricStatistics('game_generation'),
                'queued_operations' => $this->getQueueStatistics(),
                'system_performance' => $this->getSystemPerformanceMetrics()
            ];
        } catch (Exception $e) {
            Log::error('Failed to get performance statistics', [
                'error' => $e->getMessage()
            ]);
            
            return [];
        }
    }

    /**
     * Get average processing time for queue estimation
     */
    public function getAverageProcessingTime(): float
    {
        try {
            $completionMetrics = $this->getMetrics('operation_completions');
            
            if (empty($completionMetrics)) {
                return 0;
            }
            
            $processingTimes = array_filter(array_column($completionMetrics, 'processing_time'));
            
            if (empty($processingTimes)) {
                return 0;
            }
            
            return array_sum($processingTimes) / count($processingTimes);
        } catch (Exception $e) {
            Log::warning('Failed to get average processing time', [
                'error' => $e->getMessage()
            ]);
            
            return 0;
        }
    }

    /**
     * Get queue throughput (operations per minute)
     */
    public function getQueueThroughput(): float
    {
        try {
            $completionMetrics = $this->getMetrics('operation_completions');
            
            if (empty($completionMetrics)) {
                return 0;
            }
            
            // Get completions in the last hour
            $oneHourAgo = microtime(true) - 3600;
            $recentCompletions = array_filter($completionMetrics, function($metric) use ($oneHourAgo) {
                return ($metric['completed_at'] ?? 0) > $oneHourAgo;
            });
            
            // Calculate operations per minute
            return count($recentCompletions) / 60;
        } catch (Exception $e) {
            Log::warning('Failed to get queue throughput', [
                'error' => $e->getMessage()
            ]);
            
            return 0;
        }
    }

    /**
     * Clear old performance metrics
     */
    public function clearOldMetrics(): void
    {
        try {
            $metricTypes = [
                'preview_generation',
                'export_generation',
                'cli_execution',
                'game_generation',
                'queued_operations',
                'operation_completions',
                'priority_operations'
            ];
            
            foreach ($metricTypes as $metricType) {
                $this->clearOldMetricsForType($metricType);
            }
            
            Log::info('Cleared old performance metrics');
        } catch (Exception $e) {
            Log::error('Failed to clear old metrics', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Record a metric (public method for middleware usage)
     */
    public function recordMetric(string $metricType, array $data): void
    {
        $this->recordMetricInternal($metricType, $data);
    }

    /**
     * Record a metric (internal implementation)
     */
    private function recordMetricInternal(string $metricType, array $data): void
    {
        $cacheKey = "gdevelop:metrics:{$metricType}";
        $metrics = Cache::get($cacheKey, []);
        
        // Add new metric
        $metrics[] = $data;
        
        // Limit history size
        if (count($metrics) > $this->metricsHistoryLimit) {
            $metrics = array_slice($metrics, -$this->metricsHistoryLimit);
        }
        
        Cache::put($cacheKey, $metrics, $this->metricsTtl);
    }

    /**
     * Get metrics for a specific type
     */
    private function getMetrics(string $metricType): array
    {
        $cacheKey = "gdevelop:metrics:{$metricType}";
        return Cache::get($cacheKey, []);
    }

    /**
     * Get statistics for a specific metric type
     */
    private function getMetricStatistics(string $metricType): array
    {
        $metrics = $this->getMetrics($metricType);
        
        if (empty($metrics)) {
            return [
                'count' => 0,
                'average_time' => 0,
                'min_time' => 0,
                'max_time' => 0,
                'success_rate' => 0
            ];
        }
        
        $times = [];
        $successCount = 0;
        
        foreach ($metrics as $metric) {
            if (isset($metric['generation_time'])) {
                $times[] = $metric['generation_time'];
            } elseif (isset($metric['execution_time'])) {
                $times[] = $metric['execution_time'];
            } elseif (isset($metric['processing_time'])) {
                $times[] = $metric['processing_time'];
            }
            
            if (isset($metric['success']) && $metric['success']) {
                $successCount++;
            }
        }
        
        return [
            'count' => count($metrics),
            'average_time' => !empty($times) ? array_sum($times) / count($times) : 0,
            'min_time' => !empty($times) ? min($times) : 0,
            'max_time' => !empty($times) ? max($times) : 0,
            'success_rate' => count($metrics) > 0 ? ($successCount / count($metrics)) * 100 : 0
        ];
    }

    /**
     * Get queue-specific statistics
     */
    private function getQueueStatistics(): array
    {
        $queuedMetrics = $this->getMetrics('queued_operations');
        $completionMetrics = $this->getMetrics('operation_completions');
        
        return [
            'total_queued' => count($queuedMetrics),
            'total_completed' => count($completionMetrics),
            'average_processing_time' => $this->getAverageProcessingTime(),
            'throughput_per_minute' => $this->getQueueThroughput()
        ];
    }

    /**
     * Get system performance metrics
     */
    private function getSystemPerformanceMetrics(): array
    {
        $cpuLoad = 0;
        
        // sys_getloadavg() is not available on Windows
        if (function_exists('sys_getloadavg')) {
            $loadAvg = sys_getloadavg();
            $cpuLoad = $loadAvg[0] ?? 0;
        }
        
        return [
            'memory_usage' => memory_get_usage(true),
            'peak_memory_usage' => memory_get_peak_usage(true),
            'cpu_load' => $cpuLoad,
            'timestamp' => microtime(true)
        ];
    }

    /**
     * Clear old metrics for a specific type
     */
    private function clearOldMetricsForType(string $metricType): void
    {
        $cacheKey = "gdevelop:metrics:{$metricType}";
        $metrics = Cache::get($cacheKey, []);
        
        // Keep only metrics from the last 24 hours
        $oneDayAgo = microtime(true) - 86400;
        $recentMetrics = array_filter($metrics, function($metric) use ($oneDayAgo) {
            return ($metric['timestamp'] ?? 0) > $oneDayAgo;
        });
        
        Cache::put($cacheKey, array_values($recentMetrics), $this->metricsTtl);
    }

    /**
     * Trigger alert for slow operations
     */
    private function triggerSlowOperationAlert(string $operationType, float $operationTime, string $sessionId): void
    {
        try {
            Log::warning('Slow GDevelop operation detected', [
                'operation_type' => $operationType,
                'operation_time' => $operationTime,
                'threshold' => $this->slowOperationThreshold,
                'session_id' => $sessionId,
                'timestamp' => now()->toISOString()
            ]);

            // Record alert metric
            $this->recordMetricInternal('performance_alerts', [
                'operation_type' => $operationType,
                'operation_time' => $operationTime,
                'threshold' => $this->slowOperationThreshold,
                'session_id' => $sessionId,
                'timestamp' => microtime(true)
            ]);

            // In a production environment, you might want to:
            // - Send notifications to administrators
            // - Trigger automated scaling
            // - Update monitoring dashboards
            // - Store alerts in a dedicated table

        } catch (Exception $e) {
            Log::error('Failed to trigger slow operation alert', [
                'operation_type' => $operationType,
                'error' => $e->getMessage()
            ]);
        }
    }
}