<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Models\Workspace;
use Illuminate\Support\Facades\Auth;

class GamesController extends Controller
{
    /**
     * Show the user's games/workspaces
     */
    public function index(): View
    {
        $user = Auth::user();
        $company = $user->currentCompany;

        // Get workspaces for current company
        $workspaces = collect([]);
        if ($company) {
            try {
                $workspaces = Workspace::where('company_id', $company->id)
                    ->orderBy('updated_at', 'desc')
                    ->get();
            } catch (\Exception $e) {
                // Graceful fallback if workspace table doesn't exist yet
                $workspaces = collect([]);
            }
        }

        return view('games.index', compact('workspaces'));
    }

    /**
     * Show a specific game/workspace
     */
    public function show(Request $request, $id): View
    {
        $user = Auth::user();
        $company = $user->currentCompany;

        try {
            $workspace = Workspace::where('company_id', $company->id)
                ->where('id', $id)
                ->firstOrFail();
        } catch (\Exception $e) {
            abort(404, 'Game not found');
        }

        return view('games.show', compact('workspace'));
    }
}
