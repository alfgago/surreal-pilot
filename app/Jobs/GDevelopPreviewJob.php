<?php

namespace App\Jobs;

use App\Services\GDevelopRuntimeService;
use App\Services\GDevelopPerformanceMonitorService;
use App\Services\GDevelopSessionManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Exception;

class GDevelopPreviewJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300; // 5 minutes
    public int $tries = 3;

    public function __construct(
        private string $sessionId,
        private string $jobId,
        private ?string $callbackUrl = null
    ) {}

    /**
     * Execute the job
     */
    public function handle(
        GDevelopRuntimeService $runtimeService,
        GDevelopPerformanceMonitorService $performanceMonitor,
        GDevelopSessionManager $sessionManager
    ): void {
        $startTime = microtime(true);
        
        try {
            Log::info('Starting GDevelop preview job', [
                'job_id' => $this->jobId,
                'session_id' => $this->sessionId
            ]);

            // Record job start
            $performanceMonitor->recordOperationStart($this->jobId);

            // Get the game session
            $gameSession = $sessionManager->getSession($this->sessionId);
            
            if (!$gameSession) {
                throw new Exception("Game session not found: {$this->sessionId}");
            }

            // Get the game JSON path
            $gameJsonPath = $gameSession->getStoragePath() . '/game.json';
            
            if (!file_exists($gameJsonPath)) {
                throw new Exception("Game JSON file not found: {$gameJsonPath}");
            }

            // Build the preview
            $previewResult = $runtimeService->buildPreview($this->sessionId, $gameJsonPath);

            if (!$previewResult->success) {
                throw new Exception("Preview build failed: " . ($previewResult->error ?? 'Unknown error'));
            }

            $processingTime = microtime(true) - $startTime;

            // Record successful completion
            $performanceMonitor->recordOperationCompletion($this->jobId, true);
            $performanceMonitor->recordPreviewGeneration(
                $processingTime,
                true,
                $this->sessionId
            );

            // Send callback notification if provided
            if ($this->callbackUrl) {
                $this->sendCallback(true, [
                    'preview_result' => $previewResult,
                    'processing_time' => $processingTime
                ]);
            }

            Log::info('GDevelop preview job completed successfully', [
                'job_id' => $this->jobId,
                'session_id' => $this->sessionId,
                'processing_time' => $processingTime,
                'preview_path' => $previewResult->previewPath,
                'preview_url' => $previewResult->previewUrl
            ]);

        } catch (Exception $e) {
            $processingTime = microtime(true) - $startTime;
            
            // Record failed completion
            $performanceMonitor->recordOperationCompletion($this->jobId, false, $e->getMessage());
            $performanceMonitor->recordPreviewGeneration(
                $processingTime,
                false,
                $this->sessionId
            );

            // Send callback notification if provided
            if ($this->callbackUrl) {
                $this->sendCallback(false, [
                    'error' => $e->getMessage(),
                    'processing_time' => $processingTime
                ]);
            }

            Log::error('GDevelop preview job failed', [
                'job_id' => $this->jobId,
                'session_id' => $this->sessionId,
                'error' => $e->getMessage(),
                'processing_time' => $processingTime
            ]);

            // Re-throw to mark job as failed
            throw $e;
        }
    }

    /**
     * Handle job failure
     */
    public function failed(Exception $exception): void
    {
        Log::error('GDevelop preview job failed permanently', [
            'job_id' => $this->jobId,
            'session_id' => $this->sessionId,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);

        // Send failure callback if provided
        if ($this->callbackUrl) {
            $this->sendCallback(false, [
                'error' => $exception->getMessage(),
                'attempts' => $this->attempts(),
                'final_failure' => true
            ]);
        }
    }

    /**
     * Send callback notification
     */
    private function sendCallback(bool $success, array $data): void
    {
        try {
            $payload = [
                'job_id' => $this->jobId,
                'session_id' => $this->sessionId,
                'operation_type' => 'preview',
                'success' => $success,
                'timestamp' => now()->toISOString(),
                ...$data
            ];

            Http::timeout(10)->post($this->callbackUrl, $payload);
            
            Log::debug('Sent preview job callback', [
                'job_id' => $this->jobId,
                'callback_url' => $this->callbackUrl,
                'success' => $success
            ]);

        } catch (Exception $e) {
            Log::warning('Failed to send preview job callback', [
                'job_id' => $this->jobId,
                'callback_url' => $this->callbackUrl,
                'error' => $e->getMessage()
            ]);
        }
    }
}