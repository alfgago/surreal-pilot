<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Services\MultiplayerService;
use App\Services\MultiplayerStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class MultiplayerController extends Controller
{
    private MultiplayerService $multiplayerService;
    private MultiplayerStorageService $storageService;

    public function __construct(
        MultiplayerService $multiplayerService,
        MultiplayerStorageService $storageService
    ) {
        $this->multiplayerService = $multiplayerService;
        $this->storageService = $storageService;
    }

    /**
     * Start a new multiplayer session.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function start(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'workspace_id' => 'required|integer|exists:workspaces,id',
            'max_players' => 'sometimes|integer|min:2|max:16',
            'ttl_minutes' => 'sometimes|integer|min:10|max:120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $workspace = Workspace::findOrFail($request->workspace_id);

            // Check if user has access to this workspace
            if ($workspace->company_id !== auth()->user()->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied to this workspace',
                ], 403);
            }

            // Check if workspace is PlayCanvas
            if (!$workspace->isPlayCanvas()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Multiplayer sessions are only supported for PlayCanvas workspaces',
                ], 400);
            }

            $maxPlayers = $request->get('max_players', config('multiplayer.default_max_players', 8));
            $ttlMinutes = $request->get('ttl_minutes', config('multiplayer.default_ttl_minutes', 40));

            $result = $this->multiplayerService->startSession($workspace, $maxPlayers, $ttlMinutes);

            Log::info('Multiplayer session started via API', [
                'user_id' => auth()->id(),
                'workspace_id' => $workspace->id,
                'session_id' => $result['session_id'],
                'max_players' => $maxPlayers,
                'ttl_minutes' => $ttlMinutes,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Multiplayer session started successfully',
                'data' => [
                    'session_id' => $result['session_id'],
                    'session_url' => $result['session_url'],
                    'expires_at' => $result['expires_at'],
                    'max_players' => $maxPlayers,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to start multiplayer session via API', [
                'user_id' => auth()->id(),
                'workspace_id' => $request->workspace_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to start multiplayer session: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Stop a multiplayer session.
     *
     * @param Request $request
     * @param string $sessionId
     * @return JsonResponse
     */
    public function stop(Request $request, string $sessionId): JsonResponse
    {
        try {
            $success = $this->multiplayerService->stopSession($sessionId);

            if ($success) {
                Log::info('Multiplayer session stopped via API', [
                    'user_id' => auth()->id(),
                    'session_id' => $sessionId,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Multiplayer session stopped successfully',
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to stop multiplayer session or session not found',
                ], 404);
            }

        } catch (\Exception $e) {
            Log::error('Failed to stop multiplayer session via API', [
                'user_id' => auth()->id(),
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to stop multiplayer session: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get the status of a multiplayer session.
     *
     * @param string $sessionId
     * @return JsonResponse
     */
    public function status(string $sessionId): JsonResponse
    {
        try {
            $status = $this->multiplayerService->getSessionStatus($sessionId);

            return response()->json([
                'success' => true,
                'data' => $status,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get multiplayer session status via API', [
                'user_id' => auth()->id(),
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get session status: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload a progress file for a multiplayer session.
     *
     * @param Request $request
     * @param string $sessionId
     * @return JsonResponse
     */
    public function uploadProgress(Request $request, string $sessionId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:10240', // 10MB max
            'filename' => 'sometimes|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Get session and verify access
            $session = \App\Models\MultiplayerSession::find($sessionId);
            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session not found',
                ], 404);
            }

            $workspace = $session->workspace;
            if ($workspace->company_id !== auth()->user()->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied to this session',
                ], 403);
            }

            $file = $request->file('file');
            $filename = $request->get('filename', $file->getClientOriginalName());

            $result = $this->storageService->storeProgressFile($workspace, $sessionId, $file, $filename);

            Log::info('Multiplayer progress file uploaded via API', [
                'user_id' => auth()->id(),
                'session_id' => $sessionId,
                'filename' => $result['filename'],
                'size' => $result['size'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Progress file uploaded successfully',
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to upload multiplayer progress file via API', [
                'user_id' => auth()->id(),
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload progress file: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download a progress file for a multiplayer session.
     *
     * @param string $sessionId
     * @param string $filename
     * @return JsonResponse|\Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function downloadProgress(string $sessionId, string $filename)
    {
        try {
            // Get session and verify access
            $session = \App\Models\MultiplayerSession::find($sessionId);
            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session not found',
                ], 404);
            }

            $workspace = $session->workspace;
            if ($workspace->company_id !== auth()->user()->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied to this session',
                ], 403);
            }

            $fileData = $this->storageService->getProgressFile($workspace, $sessionId, $filename);

            if (!$fileData) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found',
                ], 404);
            }

            return response()->streamDownload(function () use ($fileData) {
                echo $fileData['content'];
            }, $fileData['filename']);

        } catch (\Exception $e) {
            Log::error('Failed to download multiplayer progress file via API', [
                'user_id' => auth()->id(),
                'session_id' => $sessionId,
                'filename' => $filename,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to download progress file: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List progress files for a multiplayer session.
     *
     * @param string $sessionId
     * @return JsonResponse
     */
    public function listProgress(string $sessionId): JsonResponse
    {
        try {
            // Get session and verify access
            $session = \App\Models\MultiplayerSession::find($sessionId);
            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session not found',
                ], 404);
            }

            $workspace = $session->workspace;
            if ($workspace->company_id !== auth()->user()->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied to this session',
                ], 403);
            }

            $files = $this->storageService->listProgressFiles($workspace, $sessionId);

            return response()->json([
                'success' => true,
                'data' => [
                    'files' => $files,
                    'count' => count($files),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to list multiplayer progress files via API', [
                'user_id' => auth()->id(),
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to list progress files: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get multiplayer session statistics.
     *
     * @return JsonResponse
     */
    public function stats(): JsonResponse
    {
        try {
            $stats = $this->multiplayerService->getSessionStats();

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get multiplayer stats via API', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get multiplayer stats: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get active sessions for the authenticated user's company.
     *
     * @return JsonResponse
     */
    public function activeSessions(): JsonResponse
    {
        try {
            $companyId = auth()->user()->company_id;
            
            $sessions = \App\Models\MultiplayerSession::whereHas('workspace', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })
            ->active()
            ->with('workspace:id,name,engine_type')
            ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'sessions' => $sessions,
                    'count' => $sessions->count(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get active multiplayer sessions via API', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get active sessions: ' . $e->getMessage(),
            ], 500);
        }
    }
}