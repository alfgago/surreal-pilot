<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChatMessageRequest;
use App\Services\ChatConversationService;
use App\Services\CreditManager;
use App\Services\ErrorMonitoringService;
use App\Models\ChatConversation;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Support\AI\AgentRouter;

class StreamingChatController extends Controller
{
    public function __construct(
        private ChatConversationService $conversationService,
        private CreditManager $creditManager,
        private ErrorMonitoringService $errorMonitoring
    ) {}

    /**
     * Stream chat responses using Server-Sent Events (GET method for EventSource)
     */
    public function streamSSE(Request $request): StreamedResponse|\Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $company = $user->currentCompany;

        $conversationId = $request->query('conversation_id');
        $message = $request->query('message');
        $workspaceId = $request->query('workspace_id');

        if (!$conversationId || !$message || !$workspaceId) {
            return response()->json([
                'success' => false,
                'message' => 'Missing required parameters: conversation_id, message, workspace_id',
            ], 400);
        }

        // Verify conversation access
        $conversation = ChatConversation::whereHas('workspace', function ($query) use ($company) {
            $query->where('company_id', $company->id);
        })->where('id', $conversationId)->first();

        if (!$conversation) {
            return response()->json([
                'success' => false,
                'message' => 'Conversation not found or access denied',
            ], 404);
        }

        $workspace = $conversation->workspace;

        return response()->stream(function () use ($conversation, $workspace, $message, $user, $company) {
            try {
                // Send initial connection event
                $this->sendSSEEvent('connected', [
                    'conversation_id' => $conversation->id,
                    'workspace_id' => $workspace->id,
                    'timestamp' => now()->toISOString(),
                ]);

                // Add user message to conversation
                $userMessage = $this->conversationService->addMessage(
                    $conversation,
                    'user',
                    $message
                );

                // Send user message confirmation
                $this->sendSSEEvent('user_message', [
                    'id' => $userMessage->id,
                    'content' => $message,
                    'timestamp' => $userMessage->created_at->toISOString(),
                ]);

                // Send typing indicator
                $this->sendSSEEvent('typing_start', [
                    'role' => 'assistant',
                    'timestamp' => now()->toISOString(),
                ]);

                // Generate a simple fallback response for SSE endpoint
                $fullResponse = "I'm sorry, but the SSE endpoint is deprecated. Please use the POST /api/chat/stream endpoint with the useStream hook for proper streaming functionality.";

                // Send the complete response as one chunk
                $this->sendSSEEvent('chunk', [
                    'content' => $fullResponse,
                    'tokens' => 10,
                    'chunk_index' => 0,
                    'timestamp' => now()->toISOString(),
                ]);

                // Stop typing indicator
                $this->sendSSEEvent('typing_stop', [
                    'role' => 'assistant',
                    'timestamp' => now()->toISOString(),
                ]);

                // Save complete assistant response
                $assistantMessage = $this->conversationService->addMessage(
                    $conversation,
                    'assistant',
                    $fullResponse,
                    [
                        'tokens_used' => 10,
                        'streaming' => false,
                        'engine_type' => $workspace->engine_type,
                    ]
                );

                // Send completion event
                $this->sendSSEEvent('completed', [
                    'message_id' => $assistantMessage->id,
                    'total_tokens' => 10,
                    'credits_remaining' => $company->fresh()->credits,
                    'conversation_id' => $conversation->id,
                    'timestamp' => $assistantMessage->created_at->toISOString(),
                ]);

                Log::info('Streaming chat completed', [
                    'conversation_id' => $conversation->id,
                    'user_id' => $user->id,
                    'total_tokens' => 10,
                ]);

            } catch (\Throwable $e) {
                $this->errorMonitoring->trackError(
                    'streaming_chat_error',
                    $e->getMessage(),
                    $user,
                    $company,
                    [
                        'conversation_id' => $conversation->id,
                        'workspace_id' => $workspace->id,
                    ]
                );

                $this->sendSSEEvent('error', [
                    'message' => 'An error occurred during streaming',
                    'error_code' => 'STREAMING_ERROR',
                    'timestamp' => now()->toISOString(),
                ]);

                Log::error('Streaming chat error', [
                    'conversation_id' => $conversation->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Headers' => 'Cache-Control',
        ]);
    }

    /**
     * Stream chat responses using Laravel Stream (POST method compatible with useStream hook)
     */
    public function stream(Request $request): StreamedResponse
    {
        $user = $request->user();
        $company = $user->currentCompany;

        // Get the message data from request
        $messages = $request->input('messages', []);
        $conversationId = $request->input('conversation_id');
        $workspaceId = $request->input('workspace_id');

        if (empty($messages)) {
            return response()->stream(function () {
                echo 'No message provided';
            }, 400);
        }

        // Get the latest user message
        $lastMessage = end($messages);
        $messageContent = $lastMessage['content'] ?? '';

        if (!$conversationId || !$workspaceId) {
            return response()->stream(function () {
                echo 'Missing conversation_id or workspace_id';
            }, 400);
        }

        // Verify conversation access
        $conversation = ChatConversation::whereHas('workspace', function ($query) use ($company) {
            $query->where('company_id', $company->id);
        })->where('id', $conversationId)->first();

        if (!$conversation) {
            return response()->stream(function () {
                echo 'Conversation not found or access denied';
            }, 404);
        }

        $workspace = $conversation->workspace;

        return response()->stream(function () use ($conversation, $workspace, $messageContent, $messages, $user, $company) {
            try {
                // Add user message to conversation
                $userMessage = $this->conversationService->addMessage(
                    $conversation,
                    'user',
                    $messageContent
                );

                // Use real AI agents for streaming
                $engineType = strtolower($workspace->engine_type ?? 'playcanvas');
                $agentClass = AgentRouter::forEngine($engineType);

                // Prepare context for the agent
                $context = [
                    'conversation_id' => $conversation->id,
                    'workspace_id' => $workspace->id,
                    'engine_type' => $engineType,
                ];

                // Build the prompt with context
                $prompt = $this->buildPromptWithContext($messages, $context, $engineType);

                // Set default model/provider for the engine
                $model = $engineType === 'playcanvas' ?
                    config('ai.models.playcanvas', 'claude-sonnet-4-20250514') :
                    config('ai.models.unreal', 'claude-sonnet-4-20250514');

                config([
                    'vizra-adk.default_provider' => 'anthropic',
                    'vizra-adk.default_model' => $model,
                ]);

                // Execute streaming with the agent
                $executor = $agentClass::run($prompt)->streaming(true)->withContext($context);
                $stream = $executor->go();

                $completeResponse = '';
                $totalTokens = 0;

                foreach ($stream as $chunk) {
                    $text = method_exists($chunk, 'getContent') ?
                        $chunk->getContent() :
                        ($chunk->text ?? (string) $chunk);

                    $tokens = $chunk->usage->outputTokens ?? 0;
                    $totalTokens += $tokens;
                    $completeResponse .= $text;

                    // Output the chunk directly (Laravel Stream format)
                    echo $text;

                    if (ob_get_level()) {
                        ob_flush();
                    }
                    flush();

                    // Deduct credits for this chunk
                    if ($tokens > 0) {
                        $this->creditManager->deductCredits(
                            $company,
                            $tokens,
                            'AI Chat - Streaming',
                            [
                                'conversation_id' => $conversation->id,
                                'user_id' => $user->id,
                                'engine_type' => $engineType,
                            ]
                        );
                    }
                }

                // Save complete assistant response
                $assistantMessage = $this->conversationService->addMessage(
                    $conversation,
                    'assistant',
                    $completeResponse,
                    [
                        'tokens_used' => $totalTokens,
                        'streaming' => true,
                        'engine_type' => $workspace->engine_type,
                    ]
                );

                Log::info('Streaming chat completed', [
                    'conversation_id' => $conversation->id,
                    'user_id' => $user->id,
                    'total_tokens' => $totalTokens,
                ]);

            } catch (\Throwable $e) {
                $this->errorMonitoring->trackError(
                    'streaming_chat_error',
                    $e->getMessage(),
                    $user,
                    $company,
                    [
                        'conversation_id' => $conversation->id ?? null,
                        'workspace_id' => $workspace->id ?? null,
                    ]
                );

                Log::error('Streaming chat error', [
                    'conversation_id' => $conversation->id ?? null,
                    'error' => $e->getMessage(),
                ]);

                // Send error message as response
                echo 'I apologize, but I encountered an error while processing your request. Please try again.';
            }
        }, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Send Server-Sent Event
     */
    private function sendSSEEvent(string $event, array $data): void
    {
        echo "event: {$event}\n";
        echo "data: " . json_encode($data) . "\n\n";

        if (ob_get_level()) {
            ob_flush();
        }
        flush();
    }

    /**
     * Build prompt with conversation context for the AI agent
     */
    private function buildPromptWithContext(array $messages, array $context, string $engineType): string
    {
        $conversationHistory = '';

        // Build conversation history from messages
        foreach ($messages as $message) {
            $role = $message['role'] ?? 'user';
            $content = $message['content'] ?? '';
            $conversationHistory .= ucfirst($role) . ": " . $content . "\n\n";
        }

        // Add engine-specific context
        $engineContext = $engineType === 'playcanvas' ?
            "You are assisting with a PlayCanvas web game development project." :
            "You are assisting with an Unreal Engine game development project.";

        return $engineContext . "\n\nConversation:\n" . $conversationHistory;
    }



    /**
     * Estimate tokens for a text chunk
     */
    private function estimateTokens(string $text): int
    {
        // Rough estimation: ~4 characters per token
        return max(1, intval(strlen($text) / 4));
    }
}
