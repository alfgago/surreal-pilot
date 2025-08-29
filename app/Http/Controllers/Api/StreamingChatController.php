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

class StreamingChatController extends Controller
{
    public function __construct(
        private ChatConversationService $conversationService,
        private CreditManager $creditManager,
        private ErrorMonitoringService $errorMonitoring
    ) {}

    /**
     * Stream chat responses using Server-Sent Events
     */
    public function stream(ChatMessageRequest $request): StreamedResponse|\Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $company = $user->currentCompany;
        
        $validated = $request->validated();
        $conversationId = $validated['conversation_id'];
        $message = $validated['message'];
        $workspaceId = $validated['workspace_id'];

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

                // Simulate AI response streaming (replace with actual AI integration)
                $fullResponse = $this->generateStreamingResponse($message, $workspace);
                $chunks = $this->chunkResponse($fullResponse);
                
                $assistantMessageId = null;
                $completeResponse = '';
                $totalTokens = 0;

                foreach ($chunks as $index => $chunk) {
                    $completeResponse .= $chunk['content'];
                    $totalTokens += $chunk['tokens'];

                    // Send chunk
                    $this->sendSSEEvent('chunk', [
                        'content' => $chunk['content'],
                        'tokens' => $chunk['tokens'],
                        'chunk_index' => $index,
                        'timestamp' => now()->toISOString(),
                    ]);

                    // Deduct credits for this chunk
                    if ($chunk['tokens'] > 0) {
                        $this->creditManager->deductCredits(
                            $company,
                            $chunk['tokens'],
                            'AI Chat - Streaming Chunk',
                            [
                                'conversation_id' => $conversation->id,
                                'chunk_index' => $index,
                                'user_id' => $user->id,
                            ]
                        );
                    }

                    // Small delay to simulate real streaming
                    usleep(50000); // 50ms
                }

                // Stop typing indicator
                $this->sendSSEEvent('typing_stop', [
                    'role' => 'assistant',
                    'timestamp' => now()->toISOString(),
                ]);

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

                // Send completion event
                $this->sendSSEEvent('completed', [
                    'message_id' => $assistantMessage->id,
                    'total_tokens' => $totalTokens,
                    'credits_remaining' => $company->fresh()->credits,
                    'conversation_id' => $conversation->id,
                    'timestamp' => $assistantMessage->created_at->toISOString(),
                ]);

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
     * Generate streaming response (placeholder for actual AI integration)
     */
    private function generateStreamingResponse(string $message, Workspace $workspace): string
    {
        $engineType = $workspace->engine_type;
        
        if ($engineType === 'playcanvas') {
            return "I'll help you with your PlayCanvas project. Based on your message: '{$message}', here's what I recommend:\n\n" .
                   "1. First, let's check your current scene setup\n" .
                   "2. Then we can add the necessary components\n" .
                   "3. Finally, we'll implement the game logic\n\n" .
                   "Let me know if you'd like me to proceed with any specific aspect!";
        } else {
            return "I'll assist you with your Unreal Engine project. Regarding: '{$message}', here's my suggestion:\n\n" .
                   "1. We should start by examining your Blueprint structure\n" .
                   "2. Next, we can implement the required functionality\n" .
                   "3. Then we'll test and optimize the solution\n\n" .
                   "Would you like me to help with any particular part?";
        }
    }

    /**
     * Break response into chunks for streaming
     */
    private function chunkResponse(string $response): array
    {
        $words = explode(' ', $response);
        $chunks = [];
        $currentChunk = '';
        $wordsPerChunk = 3; // Adjust for desired streaming speed

        foreach ($words as $index => $word) {
            $currentChunk .= ($currentChunk ? ' ' : '') . $word;
            
            if (($index + 1) % $wordsPerChunk === 0 || $index === count($words) - 1) {
                $chunks[] = [
                    'content' => $currentChunk . ($index === count($words) - 1 ? '' : ' '),
                    'tokens' => $this->estimateTokens($currentChunk),
                ];
                $currentChunk = '';
            }
        }

        return $chunks;
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