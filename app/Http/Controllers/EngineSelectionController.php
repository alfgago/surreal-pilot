<?php

namespace App\Http\Controllers;

use App\Services\EngineSelectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EngineSelectionController extends Controller
{
    public function __construct(
        private EngineSelectionService $engineSelectionService
    ) {}

    /**
     * Show the engine selection page.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        // If user already has an engine preference, redirect to workspace selection
        if ($user && $user->hasSelectedEngine()) {
            return redirect()->route('workspace.selection');
        }

        return view('engine-selection');
    }

    /**
     * Handle engine selection and redirect to workspace selection.
     */
    public function select(Request $request)
    {
        $request->validate([
            'engine_type' => 'required|string|in:playcanvas,unreal',
        ]);

        $user = Auth::user();
        $engineType = $request->input('engine_type');

        // Validate user can access this engine
        if (!$this->engineSelectionService->canUserAccessEngine($user, $engineType)) {
            return back()->withErrors([
                'engine_type' => 'You do not have access to this engine type.',
            ]);
        }

        // Save engine preference
        $this->engineSelectionService->setUserEnginePreference($user, $engineType);

        // Redirect to workspace selection
        return redirect()->route('workspace.selection')->with('success', 
            'Engine preference saved! Now choose or create a workspace.'
        );
    }

    /**
     * Clear engine selection and return to selection page.
     */
    public function clear(Request $request)
    {
        $user = Auth::user();
        $user->update(['selected_engine_type' => null]);

        return redirect()->route('engine.selection')->with('success', 
            'Engine preference cleared. Please select an engine.'
        );
    }
}