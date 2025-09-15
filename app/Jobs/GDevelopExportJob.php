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

class GDevelopExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600; // 10 minutes
    public int $tries = 3;

    public function __construct(
        private string $sessionId,
        private array $exportOptions,
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
            Log::info('Starting GDevelop export job', [
                'job_id' => $this->jobId,
                'session_id' => $this->sessionId,
                'export_options' => $this->exportOptions
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

            // Build the export
            $exportResult = $runtimeService->buildExport(
                $this->sessionId,
                $gameJsonPath,
                $this->exportOptions
            );

            if (!$exportResult->success) {
                throw new Exception("Export build failed: " . ($exportResult->error ?? 'Unknown error'));
            }

            $processingTime = microtime(true) - $startTime;

            // Record successful completion
            $performanceMonitor->recordOperationCompletion($this->jobId, true);
            $performanceMonitor->recordExportGeneration(
                $processingTime,
                true,
                $this->sessionId,
                $this->exportOptions
            );

            // Send callback notification if provided
            if ($this->callbackUrl) {
                $this->sendCallback(true, [
                    'export_result' => $exportResult,
                    'processing_time' => $processingTime
                ]);
            }

            Log::info('GDevelop export job completed successfully', [
                'job_id' => $this->jobId,
                'session_id' => $this->sessionId,
                'processing_time' => $processingTime,
                'export_path' => $exportResult->exportPath,
                'zip_path' => $exportResult->zipPath
            ]);

        } catch (Exception $e) {
            $processingTime = microtime(true) - $startTime;
            
            // Record failed completion
            $performanceMonitor->recordOperationCompletion($this->jobId, false, $e->getMessage());
            $performanceMonitor->recordExportGeneration(
                $processingTime,
                false,
                $this->sessionId,
                $this->exportOptions
            );

            // Send callback notification if provided
            if ($this->callbackUrl) {
                $this->sendCallback(false, [
                    'error' => $e->getMessage(),
                    'processing_time' => $processingTime
                ]);
            }

            Log::error('GDevelop export job failed', [
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
        Log::error('GDevelop export job failed permanently', [
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
                'operation_type' => 'export',
                'success' => $success,
                'timestamp' => now()->toISOString(),
                ...$data
            ];

            Http::timeout(10)->post($this->callbackUrl, $payload);
            
            Log::debug('Sent export job callback', [
                'job_id' => $this->jobId,
                'callback_url' => $this->callbackUrl,
                'success' => $success
            ]);

        } catch (Exception $e) {
            Log::warning('Failed to send export job callback', [
                'job_id' => $this->jobId,
                'callback_url' => $this->callbackUrl,
                'error' => $e->getMessage()
            ]);
        }
    }
}