<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GDevelopExportRequest;
use App\Services\GDevelopExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GDevelopExportController extends Controller
{
    public function __construct(
        private GDevelopExportService $exportService
    ) {
        // Middleware is applied in routes
    }

    /**
     * Generate export for a GDevelop game session
     */
    public function export(string $sessionId, GDevelopExportRequest $request): JsonResponse
    {
        try {
            Log::info('Export request received', [
                'session_id' => $sessionId,
                'options' => $request->getValidatedData()
            ]);

            $result = $this->exportService->generateExport(
                $sessionId,
                $request->getValidatedData()
            );

            if (!$result->success) {
                return response()->json([
                    'success' => false,
                    'error' => $result->error,
                    'message' => 'Export generation failed'
                ], 422);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'session_id' => $sessionId,
                    'download_url' => $result->downloadUrl,
                    'file_size' => $result->fileSize,
                    'build_time' => $result->buildTime,
                    'export_path' => basename($result->exportPath ?? ''),
                ],
                'message' => 'Export generated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Export generation failed', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Internal server error',
                'message' => 'Failed to generate export'
            ], 500);
        }
    }

    /**
     * Get export status for a session
     */
    public function status(string $sessionId): JsonResponse
    {
        try {
            $status = $this->exportService->getExportStatus($sessionId);

            if (!$status) {
                return response()->json([
                    'success' => false,
                    'error' => 'Session not found',
                    'message' => 'Game session not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'session_id' => $status->sessionId,
                    'exists' => $status->exists,
                    'download_url' => $status->downloadUrl,
                    'file_size' => $status->fileSize,
                    'created_at' => $status->createdAt ? date('c', $status->createdAt) : null,
                    'expires_at' => $status->expiresAt ? date('c', $status->expiresAt) : null,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Export status check failed', [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Internal server error',
                'message' => 'Failed to check export status'
            ], 500);
        }
    }

    /**
     * Download export ZIP file
     */
    public function download(string $sessionId): BinaryFileResponse|JsonResponse
    {
        try {
            $downloadResult = $this->exportService->downloadExport($sessionId);

            if (!$downloadResult) {
                return response()->json([
                    'success' => false,
                    'error' => 'Export not found',
                    'message' => 'Export file not found or has expired'
                ], 404);
            }

            Log::info('Export download initiated', [
                'session_id' => $sessionId,
                'filename' => $downloadResult->filename,
                'file_size' => $downloadResult->fileSize
            ]);

            return response()->download(
                $downloadResult->filePath,
                $downloadResult->filename,
                [
                    'Content-Type' => $downloadResult->mimeType,
                    'Content-Length' => $downloadResult->fileSize,
                    'Cache-Control' => 'no-cache, must-revalidate',
                    'Expires' => 'Sat, 26 Jul 1997 05:00:00 GMT'
                ]
            );

        } catch (\Exception $e) {
            Log::error('Export download failed', [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Internal server error',
                'message' => 'Failed to download export'
            ], 500);
        }
    }

    /**
     * Delete export for a session
     */
    public function delete(string $sessionId): JsonResponse
    {
        try {
            $status = $this->exportService->getExportStatus($sessionId);

            if (!$status || !$status->exists) {
                return response()->json([
                    'success' => false,
                    'error' => 'Export not found',
                    'message' => 'Export file not found'
                ], 404);
            }

            // Get the ZIP path and delete it
            $zipPath = storage_path('gdevelop/exports') . DIRECTORY_SEPARATOR . $sessionId . '.zip';
            
            if (file_exists($zipPath)) {
                unlink($zipPath);
                
                Log::info('Export deleted', [
                    'session_id' => $sessionId,
                    'zip_path' => $zipPath
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Export deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Export deletion failed', [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Internal server error',
                'message' => 'Failed to delete export'
            ], 500);
        }
    }

    /**
     * Clean up old exports
     */
    public function cleanup(Request $request): JsonResponse
    {
        try {
            $hours = $request->input('hours', 24);
            $cleanedCount = $this->exportService->cleanupOldExports($hours);

            Log::info('Export cleanup completed', [
                'hours' => $hours,
                'cleaned_count' => $cleanedCount
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'cleaned_count' => $cleanedCount,
                    'hours' => $hours
                ],
                'message' => "Cleaned up {$cleanedCount} old export files"
            ]);

        } catch (\Exception $e) {
            Log::error('Export cleanup failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Internal server error',
                'message' => 'Failed to cleanup exports'
            ], 500);
        }
    }
}