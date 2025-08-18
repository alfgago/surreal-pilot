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
    public function index(Request $request): View
    {
        $user = Auth::user();
        $company = $user->currentCompany;

        // Check if user has selected an engine
        if (!$user->hasSelectedEngine()) {
            return redirect()->route('engine.selection');
        }

        // Check if workspace is specified in the route
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

        // Simplified provider list for settings modal
        $providers = [
            'anthropic' => ['name' => 'Claude Sonnet 4', 'requires_key' => true],
            'openai' => ['name' => 'OpenAI GPT-4', 'requires_key' => true],
            'gemini' => ['name' => 'Google Gemini', 'requires_key' => true],
            'ollama' => ['name' => 'Ollama (Local)', 'requires_key' => false],
        ];

        return view('chat-multi', compact('workspace', 'conversations', 'providers'));
    }
}
