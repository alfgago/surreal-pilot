<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Services\ChatConversationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ChatController extends Controller
{
    public function __construct(
        private ChatConversationService $conversationService
    ) {}

    /**
     * Show the chat interface
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $company = $user->currentCompany;

        // Check if user has selected an engine
        if (!$user->hasSelectedEngine()) {
            return redirect()->route('engine.selection');
        }

        // Check if workspace is specified in the query or session
        $workspaceId = $request->query('workspace') ?? session('selected_workspace_id');
        $workspace = null;

        if ($workspaceId) {
            $workspace = Workspace::where('id', $workspaceId)
                ->where('company_id', $company->id)
                ->where('engine_type', $user->getSelectedEngineType())
                ->first();

            if ($workspace) {
                session(['selected_workspace_id' => $workspace->id]);
            }
        }

        // If no valid workspace, redirect to workspace selection
        if (!$workspace) {
            return redirect()->route('workspace.selection');
        }

        // Get recent conversations for this workspace
        $conversations = $this->conversationService->getWorkspaceConversations($workspace);

        // Handle conversation selection or creation
        $currentConversation = null;
        $messages = [];

        $conversationId = $request->query('conversation');
        if ($conversationId) {
            // Try to find the specified conversation
            $currentConversation = $conversations->firstWhere('id', $conversationId);
            if ($currentConversation) {
                // Get messages for this conversation
                $messages = $this->conversationService->getConversationMessages($currentConversation);
            }
        }

        // If no conversation exists, create a default one
        if (!$currentConversation && $conversations->isEmpty()) {
            $currentConversation = $this->conversationService->createConversation($workspace, 'New Chat');
            $conversations = collect([$currentConversation]);
        } elseif (!$currentConversation && $conversations->isNotEmpty()) {
            // Use the most recent conversation
            $currentConversation = $conversations->first();
            $messages = $this->conversationService->getConversationMessages($currentConversation);
        }

        // Simplified provider list for settings modal
        $providers = [
            'anthropic' => ['name' => 'Claude Sonnet 4', 'requires_key' => true],
            'openai' => ['name' => 'OpenAI GPT-4', 'requires_key' => true],
            'gemini' => ['name' => 'Google Gemini', 'requires_key' => true],
            'ollama' => ['name' => 'Ollama (Local)', 'requires_key' => false],
        ];

        // Get default chat settings from environment configuration
        $chatSettings = [
            'provider' => config('ai.provider', 'anthropic'),
            'model' => $workspace->engine_type === 'playcanvas' 
                ? config('ai.agents.playcanvas.model', 'claude-3-5-sonnet-20241022')
                : config('ai.agents.unreal.model', 'claude-3-5-sonnet-20241022'),
            'temperature' => $workspace->engine_type === 'playcanvas' 
                ? config('ai.agents.playcanvas.temperature', 0.2)
                : config('ai.agents.unreal.temperature', 0.2),
            'max_tokens' => $workspace->engine_type === 'playcanvas' 
                ? config('ai.agents.playcanvas.max_tokens', 1200)
                : config('ai.agents.unreal.max_tokens', 1200),
            'system_prompt' => null,
            'api_keys' => [
                'anthropic' => env('ANTHROPIC_API_KEY') ? '***' : null,
                'openai' => env('OPENAI_API_KEY') ? '***' : null,
                'gemini' => env('GEMINI_API_KEY') ? '***' : null,
            ],
            'preferences' => [
                'auto_save_conversations' => true,
                'show_token_usage' => false,
                'enable_context_memory' => true,
                'stream_responses' => true,
            ]
        ];

        return \Inertia\Inertia::render('Chat', compact('workspace', 'conversations', 'providers', 'chatSettings', 'currentConversation', 'messages'));
    }
}
