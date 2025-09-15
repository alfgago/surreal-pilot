<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Models\DemoTemplate;
use App\Services\EngineSelectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class WorkspacesController extends Controller
{
    public function __construct(
        private EngineSelectionService $engineSelectionService
    ) {}

    /**
     * Show all workspaces for the current company.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $company = $user->currentCompany;

        // Get all workspaces for this company, grouped by engine type
        $workspaces = $company->workspaces()
            ->orderBy('updated_at', 'desc')
            ->get()
            ->groupBy('engine_type');

        // Get available engines
        $engines = $this->engineSelectionService->getAvailableEngines();

        return \Inertia\Inertia::render('Workspaces/Index', [
            'workspaces' => $workspaces,
            'engines' => $engines,
        ]);
    }

    /**
     * Show the form for creating a new workspace.
     */
    public function create(Request $request)
    {
        $engines = $this->engineSelectionService->getAvailableEngines();
        
        return \Inertia\Inertia::render('Workspaces/Create', [
            'engines' => $engines,
        ]);
    }

    /**
     * Store a newly created workspace.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $company = $user->currentCompany;

        // Convert empty string to null for template_id before validation
        if ($request->input('template_id') === '' || $request->input('template_id') === 'none') {
            $request->merge(['template_id' => null]);
        }

        // Base validation
        $rules = [
            'name' => 'required|string|max:255',
            'engine_type' => 'required|string|in:playcanvas,gdevelop,unreal',
        ];

        // Conditional template validation based on engine type
        $engineType = $request->input('engine_type');
        if ($engineType === 'gdevelop') {
            $rules['template_id'] = 'nullable|string';
        } else {
            $rules['template_id'] = 'nullable|string|exists:demo_templates,id';
        }

        $request->validate($rules);

        // Validate template is compatible with engine type if provided
        if ($request->filled('template_id')) {
            $engineType = $request->input('engine_type');
            
            // For GDevelop, validate against config templates
            if ($engineType === 'gdevelop') {
                $gdevelopTemplates = array_keys(config('gdevelop.templates', []));
                if (!in_array($request->input('template_id'), $gdevelopTemplates)) {
                    return back()->withErrors([
                        'template_id' => 'Selected GDevelop template is not available.',
                    ]);
                }
            } else {
                // For other engines, validate against database templates
                $template = DemoTemplate::findOrFail($request->input('template_id'));
                if ($template->engine_type !== $engineType) {
                    return back()->withErrors([
                        'template_id' => 'Selected template is not compatible with the chosen engine.',
                    ]);
                }
            }
        }

        try {
            $engineType = $request->input('engine_type');
            
            // Create workspace with engine-specific configuration
            $workspaceData = [
                'company_id' => $company->id,
                'name' => $request->input('name'),
                'engine_type' => $engineType,
                'template_id' => $request->input('template_id'),
                'status' => 'ready', // Ready for use
                'metadata' => [
                    'created_by' => $user->id,
                    'engine_preference' => $engineType,
                ],
            ];

            // Add engine-specific metadata
            if ($engineType === 'gdevelop') {
                $workspaceData['metadata']['gdevelop_session_id'] = null; // Will be created on first use
                $workspaceData['metadata']['template_type'] = $request->input('template_id') ?? 'basic';
            } else {
                $workspaceData['metadata']['mcp_strategy'] = 'on_demand'; // Flag for on-demand MCP servers
            }

            $workspace = Workspace::create($workspaceData);

            // Store selected workspace in session
            session(['selected_workspace_id' => $workspace->id]);

            // Redirect to chat with new workspace
            return redirect()->route('chat')
                ->with('success', "Workspace '{$workspace->name}' created successfully!");
                
        } catch (\Exception $e) {
            Log::error('Failed to create workspace', [
                'user_id' => $user->id,
                'company_id' => $company->id,
                'engine_type' => $request->input('engine_type'),
                'error' => $e->getMessage()
            ]);
            
            return back()->withErrors([
                'name' => 'Failed to create workspace. Please try again.'
            ]);
        }
    }

    /**
     * Select a workspace for the current session.
     */
    public function select(Request $request)
    {
        $request->validate([
            'workspace_id' => 'required|integer|exists:workspaces,id',
        ]);

        $user = Auth::user();
        $company = $user->currentCompany;
        $workspaceId = $request->input('workspace_id');

        // Verify workspace belongs to user's company
        $workspace = Workspace::where('id', $workspaceId)
            ->where('company_id', $company->id)
            ->firstOrFail();

        // Store selected workspace in session
        session(['selected_workspace_id' => $workspace->id]);

        // Redirect to chat with workspace context
        return redirect()->route('chat')
            ->with('success', "Switched to workspace: {$workspace->name}");
    }

    /**
     * Get available templates for a specific engine type.
     */
    public function getTemplates(Request $request)
    {
        $request->validate([
            'engine_type' => 'required|string|in:playcanvas,gdevelop,unreal',
        ]);

        $engineType = $request->input('engine_type');

        // For GDevelop, return built-in templates from config
        if ($engineType === 'gdevelop') {
            $gdevelopTemplates = collect(config('gdevelop.templates', []))
                ->map(function ($template, $key) {
                    return [
                        'id' => $key,
                        'name' => $template['name'],
                        'description' => $template['description'],
                        'thumbnail_url' => null,
                    ];
                })
                ->values();

            return response()->json([
                'success' => true,
                'templates' => $gdevelopTemplates,
            ]);
        }

        // For other engines, use database templates
        $templates = DemoTemplate::where('engine_type', $engineType)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'thumbnail_url']);

        return response()->json([
            'success' => true,
            'templates' => $templates,
        ]);
    }

    /**
     * Show a specific workspace.
     */
    public function show(Workspace $workspace)
    {
        $user = Auth::user();
        $company = $user->currentCompany;

        // Verify workspace belongs to user's company
        if ($workspace->company_id !== $company->id) {
            abort(403, 'You do not have access to this workspace.');
        }

        // Store selected workspace in session
        session(['selected_workspace_id' => $workspace->id]);

        // Redirect to chat with workspace context
        return redirect()->route('chat')
            ->with('success', "Switched to workspace: {$workspace->name}");
    }
}