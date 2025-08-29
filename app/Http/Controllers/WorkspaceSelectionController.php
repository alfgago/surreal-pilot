<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Services\EngineSelectionService;
use App\Services\PlayCanvasMcpManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class WorkspaceSelectionController extends Controller
{
    public function __construct(
        private EngineSelectionService $engineSelectionService,
        private PlayCanvasMcpManager $playCanvasMcpManager
    ) {}

    /**
     * Show the workspace selection page.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $company = $user->currentCompany;

        // If user hasn't selected an engine, redirect to engine selection
        if (!$user->hasSelectedEngine()) {
            return redirect()->route('engine.selection');
        }

        $engineType = $user->getSelectedEngineType();
        $engineInfo = $this->engineSelectionService->getEngineDisplayInfo($engineType);

        // Get existing workspaces for this engine type
        $workspaces = $company->workspaces()
            ->where('engine_type', $engineType)
            ->orderBy('updated_at', 'desc')
            ->get();

        return \Inertia\Inertia::render('WorkspaceSelection', [
            'engineInfo' => $engineInfo,
            'workspaces' => $workspaces,
            'engineType' => $engineType,
        ]);
    }

    /**
     * Select an existing workspace.
     */
    public function select(Request $request)
    {
        $request->validate([
            'workspace_id' => 'required|integer|exists:workspaces,id',
        ]);

        $user = Auth::user();
        $company = $user->currentCompany;
        $workspaceId = $request->input('workspace_id');

        // Verify workspace belongs to user's company and matches engine type
        $workspace = Workspace::where('id', $workspaceId)
            ->where('company_id', $company->id)
            ->where('engine_type', $user->getSelectedEngineType())
            ->firstOrFail();

        // Store selected workspace in session
        session(['selected_workspace_id' => $workspace->id]);

        // Redirect to chat with workspace context
        return redirect()->route('chat')
            ->with('success', "Switched to workspace: {$workspace->name}");
    }

    /**
     * Create a new workspace.
     */
    public function create(Request $request)
    {
        $user = Auth::user();
        $company = $user->currentCompany;
        $engineType = $user->getSelectedEngineType();

        // Convert empty string to null for template_id before validation
        if ($request->input('template_id') === '' || $request->input('template_id') === 'none') {
            $request->merge(['template_id' => null]);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'template_id' => 'nullable|string|exists:demo_templates,id',
        ]);

        // Validate template is compatible with engine type if provided
        if ($request->filled('template_id')) {
            $template = \App\Models\DemoTemplate::findOrFail($request->input('template_id'));
            if ($template->engine_type !== $engineType) {
                return back()->withErrors([
                    'template_id' => 'Selected template is not compatible with your chosen engine.',
                ]);
            }
        }

        try {
            // Create workspace in ready state - MCP server will be started on-demand
            $workspace = Workspace::create([
                'company_id' => $company->id,
                'name' => $request->input('name'),
                'engine_type' => $engineType,
                'template_id' => $request->input('template_id'),
                'status' => 'ready', // Ready for use, MCP server starts when needed
                'metadata' => [
                    'created_by' => $user->id,
                    'engine_preference' => $engineType,
                    'mcp_strategy' => 'on_demand', // Flag for on-demand MCP servers
                ],
            ]);

            // Store selected workspace in session
            session(['selected_workspace_id' => $workspace->id]);

            // Redirect to chat with new workspace
            return redirect()->route('chat')
                ->with('success', "Workspace '{$workspace->name}' created successfully!");
                
        } catch (\Exception $e) {
            Log::error('Failed to create workspace', [
                'user_id' => $user->id,
                'company_id' => $company->id,
                'engine_type' => $engineType,
                'error' => $e->getMessage()
            ]);
            
            return back()->withErrors([
                'name' => 'Failed to create workspace. Please try again.'
            ]);
        }
    }

    /**
     * Get available templates for the current engine type.
     */
    public function getTemplates(Request $request)
    {
        $user = Auth::user();
        $engineType = $user->getSelectedEngineType();

        $templates = \App\Models\DemoTemplate::where('engine_type', $engineType)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'thumbnail_url']);

        return response()->json([
            'success' => true,
            'templates' => $templates,
        ]);
    }
}