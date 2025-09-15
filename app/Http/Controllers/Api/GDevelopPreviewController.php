<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GDevelopPreviewService;
use App\Services\GDevelopGameService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Exception;

class GDevelopPreviewController extends Controller
{
    public function __construct(
        private GDevelopPreviewService $previewService,
        private GDevelopGameService $gameService
    ) {
        $this->middleware('gdevelop.enabled');
    }

    /**
     * Serve preview files with proper MIME types and caching
     */
    public function serveFile(string $sessionId, string $filePath = 'index.html'): Response|BinaryFileResponse
    {
        try {
            Log::debug('Serving GDevelop preview file', [
                'session_id' => $sessionId,
                'file_path' => $filePath
            ]);

            // Check if preview exists
            if (!$this->previewService->previewExists($sessionId)) {
                Log::warning('Preview not found for session', [
                    'session_id' => $sessionId,
                    'file_path' => $filePath
                ]);

                return response('Preview not found. Please generate a preview first.', 404);
            }

            // Serve the file
            return $this->previewService->servePreviewFile($sessionId, $filePath);

        } catch (Exception $e) {
            Log::error('Failed to serve preview file', [
                'session_id' => $sessionId,
                'file_path' => $filePath,
                'error' => $e->getMessage()
            ]);

            return response('Internal server error', 500);
        }
    }

    /**
     * Refresh preview by regenerating it
     */
    public function refresh(string $sessionId, Request $request): JsonResponse
    {
        try {
            Log::info('Refreshing GDevelop preview', [
                'session_id' => $sessionId,
                'user_id' => auth()->id()
            ]);

            // Get current game data
            $gameData = $this->gameService->getGameData($sessionId);
            
            if (!$gameData || empty($gameData['game_json'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'No game data found for session'
                ], 404);
            }

            // Refresh the preview
            $result = $this->previewService->refreshPreview($sessionId, $gameData['game_json']);

            if (!$result->success) {
                Log::error('Preview refresh failed', [
                    'session_id' => $sessionId,
                    'error' => $result->error
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Preview refresh failed: ' . $result->error
                ], 500);
            }

            Log::info('GDevelop preview refreshed successfully', [
                'session_id' => $sessionId,
                'preview_url' => $result->previewUrl,
                'build_time' => $result->buildTime
            ]);

            return response()->json([
                'success' => true,
                'session_id' => $sessionId,
                'preview_url' => $result->previewUrl,
                'build_time' => $result->buildTime,
                'message' => 'Preview refreshed successfully'
            ]);

        } catch (Exception $e) {
            Log::error('Preview refresh failed', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Preview refresh failed: ' . $e->getMessage()
            ], 500);
        }
    }
}