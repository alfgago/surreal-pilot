<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\Workspace;
use App\Services\ChatConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ConversationController extends Controller
{
    public function __construct(
        private ChatConversationService $conversationService
    ) {}

    /**
     * Get conversations for a workspace.
     */
    public function getWorkspaceConversations(Request $request, int $workspaceId): JsonResponse
    {
        try {
            $user = $request->user();
            $company = $user->currentCompany;

            $workspace = Workspace::where('id', $workspaceId)
                ->where('company_id', $company->id)
                ->firstOrFail();

            $conversations = $this->conversationService->getWorkspaceConversations($workspace);

            $conversationsData = $conversations->map(function ($conversation) {
                return [
                    'id' => $conversation->id,
                    'title' => $conversation->title,
                    'description' => $conversation->description,
                    'created_at' => $conversation->created_at,
                    'updated_at' => $conversation->updated_at,
                    'message_count' => $conversation->getMessageCount(),
                    'last_message_preview' => $conversation->getLastMessagePreview(),
                ];
            });

            return response()->json([
                'success' => true,
                'conversations' => $conversationsData,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Workspace not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve conversations',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new conversation for a workspace.
     */
    public function createConversation(Request $request, int $workspaceId): JsonResponse
    {
        try {
            $user = $request->user();
            $company = $user->currentCompany;
            
            if (!$company) {
                return response()->json([
                    'success' => false,
                    'message' => 'No current company found for user',
                    'debug' => [
                        'user_id' => $user->id,
                        'current_company_id' => $user->current_company_id,
                        'companies_count' => $user->companies()->count(),
                    ]
                ], 400);
            }

            $workspace = Workspace::where('id', $workspaceId)
                ->where('company_id', $company->id)
                ->firstOrFail();

            $validated = $request->validate([
                'title' => 'nullable|string|max:255',
                'description' => 'nullable|string|max:1000',
            ]);

            $conversation = $this->conversationService->createConversation(
                $workspace,
                $validated['title'] ?? null
            );

            if (!empty($validated['description'])) {
                $conversation->update(['description' => $validated['description']]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Conversation created successfully',
                'conversation' => [
                    'id' => $conversation->id,
                    'title' => $conversation->title,
                    'description' => $conversation->description,
                    'created_at' => $conversation->created_at,
                    'updated_at' => $conversation->updated_at,
                    'message_count' => 0,
                    'last_message_preview' => 'No messages yet',
                ],
            ], 201);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Workspace not found',
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create conversation',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get messages for a conversation.
     */
    public function getConversationMessages(Request $request, int $conversationId): JsonResponse
    {
        try {
            $user = $request->user();
            $company = $user->currentCompany;

            $conversation = ChatConversation::whereHas('workspace', function ($query) use ($company) {
                $query->where('company_id', $company->id);
            })->findOrFail($conversationId);

            $messages = $this->conversationService->getConversationMessages($conversation);

            $messagesData = $messages->map(function ($message) {
                return [
                    'id' => $message->id,
                    'role' => $message->role,
                    'content' => $message->content,
                    'metadata' => $message->metadata,
                    'created_at' => $message->created_at,
                    'role_display_name' => $message->getRoleDisplayName(),
                ];
            });

            return response()->json([
                'success' => true,
                'conversation' => [
                    'id' => $conversation->id,
                    'title' => $conversation->title,
                    'description' => $conversation->description,
                ],
                'messages' => $messagesData,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Conversation not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve messages',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Add a message to a conversation.
     */
    public function addMessage(Request $request, int $conversationId): JsonResponse
    {
        try {
            $user = $request->user();
            $company = $user->currentCompany;

            $conversation = ChatConversation::whereHas('workspace', function ($query) use ($company) {
                $query->where('company_id', $company->id);
            })->findOrFail($conversationId);

            $validated = $request->validate([
                'role' => ['required', 'string', Rule::in(['user', 'assistant', 'system'])],
                'content' => 'required|string',
                'metadata' => 'nullable|array',
            ]);

            $message = $this->conversationService->addMessage(
                $conversation,
                $validated['role'],
                $validated['content'],
                $validated['metadata'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Message added successfully',
                'chat_message' => [
                    'id' => $message->id,
                    'role' => $message->role,
                    'content' => $message->content,
                    'metadata' => $message->metadata,
                    'created_at' => $message->created_at,
                    'role_display_name' => $message->getRoleDisplayName(),
                ],
            ], 201);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Conversation not found',
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add message',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update conversation details.
     */
    public function updateConversation(Request $request, int $conversationId): JsonResponse
    {
        try {
            $user = $request->user();
            $company = $user->currentCompany;

            $conversation = ChatConversation::whereHas('workspace', function ($query) use ($company) {
                $query->where('company_id', $company->id);
            })->findOrFail($conversationId);

            $validated = $request->validate([
                'title' => 'nullable|string|max:255',
                'description' => 'nullable|string|max:1000',
            ]);

            $updatedConversation = $this->conversationService->updateConversation($conversation, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Conversation updated successfully',
                'conversation' => [
                    'id' => $updatedConversation->id,
                    'title' => $updatedConversation->title,
                    'description' => $updatedConversation->description,
                    'updated_at' => $updatedConversation->updated_at,
                ],
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Conversation not found',
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update conversation',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a conversation.
     */
    public function deleteConversation(Request $request, int $conversationId): JsonResponse
    {
        try {
            $user = $request->user();
            $company = $user->currentCompany;

            $conversation = ChatConversation::whereHas('workspace', function ($query) use ($company) {
                $query->where('company_id', $company->id);
            })->findOrFail($conversationId);

            $this->conversationService->deleteConversation($conversation);

            return response()->json([
                'success' => true,
                'message' => 'Conversation deleted successfully',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Conversation not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete conversation',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get recent conversations across all workspaces.
     */
    public function getRecentConversations(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $company = $user->currentCompany;

            $limit = $request->query('limit', 10);
            $conversations = $this->conversationService->getRecentConversations($company->id, $limit);

            $conversationsData = $conversations->map(function ($conversation) {
                return [
                    'id' => $conversation->id,
                    'title' => $conversation->title,
                    'description' => $conversation->description,
                    'workspace' => [
                        'id' => $conversation->workspace->id,
                        'name' => $conversation->workspace->name,
                        'engine_type' => $conversation->workspace->engine_type,
                    ],
                    'created_at' => $conversation->created_at,
                    'updated_at' => $conversation->updated_at,
                    'message_count' => $conversation->getMessageCount(),
                    'last_message_preview' => $conversation->getLastMessagePreview(),
                ];
            });

            return response()->json([
                'success' => true,
                'conversations' => $conversationsData,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve recent conversations',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
