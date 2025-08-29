<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RealtimeChatService;
use App\Models\ChatConversation;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RealtimeChatController extends Controller
{
    public function __construct(
        private RealtimeChatService $realtimeChatService
    ) {}

    /**
     * Get the user's company, falling back to first company if no current company is set
     */
    private function getUserCompany(Request $request)
    {
        $user = $request->user();
        return $user->currentCompany ?? $user->companies()->first();
    }

    /**
     * Update typing status for a conversation
     */
    public function updateTypingStatus(Request $request, int $conversationId): JsonResponse
    {
        try {
            $user = $request->user();
            $company = $this->getUserCompany($request);

            if (!$company) {
                return response()->json([
                    'success' => false,
                    'message' => 'No company associated with user',
                ], 403);
            }

            $validated = $request->validate([
                'is_typing' => 'required|boolean',
            ]);

            $conversation = ChatConversation::whereHas('workspace', function ($query) use ($company) {
                $query->where('company_id', $company->id);
            })->findOrFail($conversationId);

            $this->realtimeChatService->broadcastTyping(
                $user,
                $conversation,
                $validated['is_typing']
            );

            return response()->json([
                'success' => true,
                'message' => 'Typing status updated',
                'is_typing' => $validated['is_typing'],
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Conversation not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Failed to update typing status', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update typing status',
            ], 500);
        }
    }

    /**
     * Update connection status for a workspace
     */
    public function updateConnectionStatus(Request $request, int $workspaceId): JsonResponse
    {
        try {
            $user = $request->user();
            $company = $user->currentCompany ?? $user->companies()->first();

            if (!$company) {
                return response()->json([
                    'success' => false,
                    'message' => 'No company associated with user',
                ], 403);
            }

            $validated = $request->validate([
                'status' => 'required|string|in:connected,disconnected,reconnecting',
                'metadata' => 'nullable|array',
            ]);

            $workspace = Workspace::where('id', $workspaceId)
                ->where('company_id', $company->id)
                ->firstOrFail();

            $this->realtimeChatService->broadcastConnectionStatus(
                $user,
                $workspace,
                $validated['status'],
                $validated['metadata'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Connection status updated',
                'status' => $validated['status'],
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Workspace not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Failed to update connection status', [
                'workspace_id' => $workspaceId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update connection status',
            ], 500);
        }
    }

    /**
     * Get typing users for a conversation
     */
    public function getTypingUsers(Request $request, int $conversationId): JsonResponse
    {
        try {
            $user = $request->user();
            $company = $this->getUserCompany($request);

            if (!$company) {
                return response()->json([
                    'success' => false,
                    'message' => 'No company associated with user',
                ], 403);
            }

            $conversation = ChatConversation::whereHas('workspace', function ($query) use ($company) {
                $query->where('company_id', $company->id);
            })->findOrFail($conversationId);

            $typingUsers = $this->realtimeChatService->getTypingUsers($conversation);

            return response()->json([
                'success' => true,
                'typing_users' => $typingUsers,
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Conversation not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Failed to get typing users', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get typing users',
            ], 500);
        }
    }

    /**
     * Get connection statuses for a workspace
     */
    public function getConnectionStatuses(Request $request, int $workspaceId): JsonResponse
    {
        try {
            $user = $request->user();
            $company = $this->getUserCompany($request);

            if (!$company) {
                return response()->json([
                    'success' => false,
                    'message' => 'No company associated with user',
                ], 403);
            }

            $workspace = Workspace::where('id', $workspaceId)
                ->where('company_id', $company->id)
                ->firstOrFail();

            $connections = $this->realtimeChatService->getConnectionStatuses($workspace);

            return response()->json([
                'success' => true,
                'connections' => $connections,
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Workspace not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Failed to get connection statuses', [
                'workspace_id' => $workspaceId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get connection statuses',
            ], 500);
        }
    }

    /**
     * Get real-time chat statistics for a workspace
     */
    public function getChatStatistics(Request $request, int $workspaceId): JsonResponse
    {
        try {
            $user = $request->user();
            $company = $user->currentCompany;

            $workspace = Workspace::where('id', $workspaceId)
                ->where('company_id', $company->id)
                ->firstOrFail();

            $statistics = $this->realtimeChatService->getChatStatistics($workspace);

            return response()->json([
                'success' => true,
                'statistics' => $statistics,
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Workspace not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Failed to get chat statistics', [
                'workspace_id' => $workspaceId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get chat statistics',
            ], 500);
        }
    }

    /**
     * Get collaboration statistics for a workspace
     */
    public function getCollaborationStats(Request $request, int $workspaceId): JsonResponse
    {
        try {
            $user = $request->user();
            $company = $user->currentCompany;

            $workspace = Workspace::where('id', $workspaceId)
                ->where('company_id', $company->id)
                ->firstOrFail();

            $statistics = $this->realtimeChatService->getChatStatistics($workspace);

            return response()->json($statistics);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Workspace not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Failed to get collaboration statistics', [
                'workspace_id' => $workspaceId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get collaboration statistics',
            ], 500);
        }
    }

    /**
     * Join collaboration session for a workspace
     */
    public function joinCollaboration(Request $request, int $workspaceId): JsonResponse
    {
        try {
            $user = $request->user();
            $company = $user->currentCompany;

            $validated = $request->validate([
                'tool' => 'nullable|string|max:255',
                'metadata' => 'nullable|array',
            ]);

            $workspace = Workspace::where('id', $workspaceId)
                ->where('company_id', $company->id)
                ->firstOrFail();

            $metadata = $validated['metadata'] ?? [];
            if (isset($validated['tool'])) {
                $metadata['current_tool'] = $validated['tool'];
            }

            $this->realtimeChatService->broadcastConnectionStatus(
                $user,
                $workspace,
                'connected',
                $metadata
            );

            return response()->json([
                'success' => true,
                'message' => 'Joined collaboration session',
                'workspace_id' => $workspaceId,
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Workspace not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Failed to join collaboration', [
                'workspace_id' => $workspaceId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to join collaboration session',
            ], 500);
        }
    }

    /**
     * Leave collaboration session for a workspace
     */
    public function leaveCollaboration(Request $request, int $workspaceId): JsonResponse
    {
        try {
            $user = $request->user();
            $company = $user->currentCompany;

            $workspace = Workspace::where('id', $workspaceId)
                ->where('company_id', $company->id)
                ->firstOrFail();

            $this->realtimeChatService->broadcastConnectionStatus(
                $user,
                $workspace,
                'disconnected'
            );

            return response()->json([
                'success' => true,
                'message' => 'Left collaboration session',
                'workspace_id' => $workspaceId,
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Workspace not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Failed to leave collaboration', [
                'workspace_id' => $workspaceId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to leave collaboration session',
            ], 500);
        }
    }
}