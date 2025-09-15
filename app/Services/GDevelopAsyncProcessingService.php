<?php

namespace App\Services;

use App\Jobs\GDevelopExportJob;
use App\Jobs\GDevelopPreviewJob;
use App\Models\GDevelopGameSession;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Exception;

class GDevelopAsyncProcessingService
{
    private GDevelopPerformanceMonitorService $performanceMonitor;
    private bool $asyncProcessingEnabled;
    private string $exportQueue;
    private string $previewQueue;
    private int $queueRetryAttempts;
    private int $queueRetryDelay;

    public function __construct(GDevelopPerformanceMonitorService $performanceMonitor)
    {
        $this->performanceMonitor = $performanceMonitor;
        $this->asyncProcessingEnabled = config('gdevelop.performance.async_processing_enabled', true);
        $this->exportQueue = config('gdevelop.performance.export_queue', 'gdevelop-exports');
        $this->previewQueue = config('gdevelop.performance.preview_queue', 'gdevelop-previews');
        $this->queueRetryAttempts = config('gdevelop.performance.queue_retry_attempts', 3);
        $this->queueRetryDelay = config('gdevelop.performance.queue_retry_delay', 60);
    }

    /**
     * Queue an export operation for async processing
     */
    public function queueExport(
        string $sessionId,
        array $exportOptions = [],
        ?string $callbackUrl = null
    ): string {
        if (!$this->asyncProcessingEnabled) {
            throw new Exception("Async processing is disabled");
        }

        try {
            $jobId = uniqid('export_', true);
            
            Log::info('Queueing export operation', [
                'session_id' => $sessionId,
                'job_id' => $jobId,
                'export_options' => $exportOptions,
                'callback_url' => $callbackUrl
            ]);

            // Dispatch the export job to the queue
            GDevelopExportJob::dispatch($sessionId, $exportOptions, $jobId, $callbackUrl)
                ->onQueue($this->exportQueue)
                ->attempts($this->queueRetryAttempts)
                ->backoff($this->queueRetryDelay)
                ->delay(now()->addSeconds(1)); // Small delay to ensure session is ready

            // Record the queued operation
            $this->performanceMonitor->recordQueuedOperation('export', $sessionId, $jobId);

            return $jobId;
            
        } catch (Exception $e) {
            Log::error('Failed to queue export operation', [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);
            
            throw new Exception("Failed to queue export: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Queue a preview generation for async processing
     */
    public function queuePreview(
        string $sessionId,
        ?string $callbackUrl = null
    ): string {
        if (!$this->asyncProcessingEnabled) {
            throw new Exception("Async processing is disabled");
        }

        try {
            $jobId = uniqid('preview_', true);
            
            Log::info('Queueing preview operation', [
                'session_id' => $sessionId,
                'job_id' => $jobId,
                'callback_url' => $callbackUrl
            ]);

            // Dispatch the preview job to the queue
            GDevelopPreviewJob::dispatch($sessionId, $jobId, $callbackUrl)
                ->onQueue($this->previewQueue)
                ->attempts($this->queueRetryAttempts)
                ->backoff($this->queueRetryDelay)
                ->delay(now()->addSeconds(1)); // Small delay to ensure session is ready

            // Record the queued operation
            $this->performanceMonitor->recordQueuedOperation('preview', $sessionId, $jobId);

            return $jobId;
            
        } catch (Exception $e) {
            Log::error('Failed to queue preview operation', [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);
            
            throw new Exception("Failed to queue preview: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get the status of a queued operation
     */
    public function getOperationStatus(string $jobId): array
    {
        try {
            // Check if the job is still in the queue
            $queueSize = Queue::size('gdevelop-exports') + Queue::size('gdevelop-previews');
            
            // Get operation details from performance monitor
            $operationDetails = $this->performanceMonitor->getQueuedOperationDetails($jobId);
            
            if (!$operationDetails) {
                return [
                    'status' => 'not_found',
                    'message' => 'Operation not found'
                ];
            }

            // Determine status based on operation details and queue state
            $status = $this->determineOperationStatus($jobId, $operationDetails);
            
            return [
                'job_id' => $jobId,
                'status' => $status,
                'operation_type' => $operationDetails['operation_type'] ?? 'unknown',
                'session_id' => $operationDetails['session_id'] ?? null,
                'queued_at' => $operationDetails['queued_at'] ?? null,
                'started_at' => $operationDetails['started_at'] ?? null,
                'completed_at' => $operationDetails['completed_at'] ?? null,
                'queue_size' => $queueSize,
                'estimated_wait_time' => $this->estimateWaitTime($queueSize)
            ];
            
        } catch (Exception $e) {
            Log::error('Failed to get operation status', [
                'job_id' => $jobId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'status' => 'error',
                'message' => 'Failed to get status: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Cancel a queued operation
     */
    public function cancelOperation(string $jobId): bool
    {
        try {
            Log::info('Cancelling queued operation', [
                'job_id' => $jobId
            ]);

            // Mark operation as cancelled in performance monitor
            $this->performanceMonitor->recordOperationCancellation($jobId);
            
            // Note: Laravel doesn't provide a direct way to cancel specific jobs
            // In a production environment, you might want to use a more sophisticated
            // queue system like Redis with job tracking
            
            return true;
            
        } catch (Exception $e) {
            Log::error('Failed to cancel operation', [
                'job_id' => $jobId,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Get queue statistics
     */
    public function getQueueStatistics(): array
    {
        try {
            return [
                'export_queue_size' => Queue::size($this->exportQueue),
                'preview_queue_size' => Queue::size($this->previewQueue),
                'total_queue_size' => Queue::size($this->exportQueue) + Queue::size($this->previewQueue),
                'failed_jobs' => $this->getFailedJobsCount(),
                'average_processing_time' => $this->performanceMonitor->getAverageProcessingTime(),
                'queue_throughput' => $this->performanceMonitor->getQueueThroughput()
            ];
            
        } catch (Exception $e) {
            Log::error('Failed to get queue statistics', [
                'error' => $e->getMessage()
            ]);
            
            return [];
        }
    }

    /**
     * Process priority operations immediately (bypass queue)
     */
    public function processPriorityOperation(
        string $operationType,
        string $sessionId,
        array $options = []
    ): array {
        try {
            Log::info('Processing priority operation', [
                'operation_type' => $operationType,
                'session_id' => $sessionId,
                'options' => $options
            ]);

            $startTime = microtime(true);
            
            switch ($operationType) {
                case 'export':
                    $result = $this->processPriorityExport($sessionId, $options);
                    break;
                case 'preview':
                    $result = $this->processPriorityPreview($sessionId);
                    break;
                default:
                    throw new Exception("Unknown operation type: {$operationType}");
            }
            
            $processingTime = microtime(true) - $startTime;
            
            // Record performance metrics
            $this->performanceMonitor->recordPriorityOperation($operationType, $processingTime, true);
            
            return $result;
            
        } catch (Exception $e) {
            $processingTime = microtime(true) - ($startTime ?? microtime(true));
            $this->performanceMonitor->recordPriorityOperation($operationType, $processingTime, false);
            
            Log::error('Priority operation failed', [
                'operation_type' => $operationType,
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Determine the status of an operation
     */
    private function determineOperationStatus(string $jobId, array $operationDetails): string
    {
        if (isset($operationDetails['cancelled_at'])) {
            return 'cancelled';
        }
        
        if (isset($operationDetails['completed_at'])) {
            return isset($operationDetails['failed']) && $operationDetails['failed'] ? 'failed' : 'completed';
        }
        
        if (isset($operationDetails['started_at'])) {
            return 'processing';
        }
        
        return 'queued';
    }

    /**
     * Estimate wait time based on queue size
     */
    private function estimateWaitTime(int $queueSize): int
    {
        // Estimate based on average processing time and queue size
        $averageProcessingTime = $this->performanceMonitor->getAverageProcessingTime();
        
        // Default to 30 seconds if no historical data
        if ($averageProcessingTime === 0) {
            $averageProcessingTime = 30;
        }
        
        return $queueSize * $averageProcessingTime;
    }

    /**
     * Get count of failed jobs
     */
    private function getFailedJobsCount(): int
    {
        try {
            // This is a simplified implementation
            // In production, you'd query the failed_jobs table
            return 0;
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Process priority export operation
     */
    private function processPriorityExport(string $sessionId, array $options): array
    {
        // This would integrate with the existing export service
        // For now, return a placeholder result
        return [
            'session_id' => $sessionId,
            'status' => 'completed',
            'export_url' => "/gdevelop/download/{$sessionId}",
            'processed_at' => now()->toISOString()
        ];
    }

    /**
     * Process priority preview operation
     */
    private function processPriorityPreview(string $sessionId): array
    {
        // This would integrate with the existing preview service
        // For now, return a placeholder result
        return [
            'session_id' => $sessionId,
            'status' => 'completed',
            'preview_url' => "/gdevelop/preview/{$sessionId}",
            'processed_at' => now()->toISOString()
        ];
    }
}