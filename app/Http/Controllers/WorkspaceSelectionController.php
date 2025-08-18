<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Services\EngineSelectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class WorkspaceSelectionController extends Controller
{
    public function __construct(
        private EngineSelectionService $engineSelectionService
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

        return view('workspace-selection', compact('engineInfo', 'workspaces', 'engineType'));
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
        return redirect()->route('chat', ['workspace' => $workspace->id])
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

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'template_id' => 'nullable|integer|exists:demo_templates,id',
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

        // Create workspace
        $workspace = Workspace::create([
            'company_id' => $company->id,
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'engine_type' => $engineType,
            'template_id' => $request->input('template_id'),
            'status' => 'active',
            'metadata' => [
                'created_by' => $user->id,
                'engine_preference' => $engineType,
            ],
        ]);

        // Store selected workspace in session
        session(['selected_workspace_id' => $workspace->id]);

        // Redirect to chat with new workspace
        return redirect()->route('chat', ['workspace' => $workspace->id])
            ->with('success', "Workspace '{$workspace->name}' created successfully!");
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