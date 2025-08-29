<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Workspace;
use App\Services\GameStorageService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GamesController extends Controller
{
    public function __construct(
        private GameStorageService $gameStorageService
    ) {}

    /**
     * Display a listing of games for the current workspace.
     */
    public function index(Request $request): Response
    {
        $user = auth()->user();
        $company = $user->currentCompany;
        
        if (!$company) {
            return redirect()->route('workspace.selection');
        }

        // Try to get workspace from session, otherwise use the first available workspace
        $workspaceId = session('selected_workspace_id');
        $workspace = null;
        
        if ($workspaceId) {
            $workspace = Workspace::where('id', $workspaceId)
                ->where('company_id', $company->id)
                ->first();
        }
        
        // If no workspace found, get the first available workspace for this company
        if (!$workspace) {
            $workspace = Workspace::where('company_id', $company->id)->first();
            
            if ($workspace) {
                // Set it in session for future requests
                session(['selected_workspace_id' => $workspace->id]);
            }
        }
        
        if (!$workspace) {
            return redirect()->route('workspace.selection');
        }

        $games = $workspace->games()
            ->with(['workspace'])
            ->orderBy('updated_at', 'desc')
            ->paginate(12);

        return Inertia::render('Games/Index', [
            'games' => $games,
            'workspace' => $workspace,
        ]);
    }

    /**
     * Show the form for creating a new game.
     */
    public function create(Request $request): Response
    {
        $workspaceId = session('selected_workspace_id');
        
        if (!$workspaceId) {
            return redirect()->route('workspace.selection');
        }

        $workspace = Workspace::find($workspaceId);
        
        if (!$workspace) {
            return redirect()->route('workspace.selection');
        }

        // Get available templates
        $templates = $this->gameStorageService->getAvailableTemplates($workspace->engine_type);

        return Inertia::render('Games/Create', [
            'workspace' => $workspace,
            'templates' => $templates,
        ]);
    }

    /**
     * Store a newly created game in storage.
     */
    public function store(Request $request)
    {
        $workspaceId = session('selected_workspace_id');
        
        if (!$workspaceId) {
            return redirect()->route('workspace.selection');
        }

        $workspace = Workspace::find($workspaceId);
        
        if (!$workspace) {
            return redirect()->route('workspace.selection');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'template_id' => 'nullable|string',
            'engine' => 'required|in:playcanvas,unreal',
        ]);

        try {
            $game = Game::create([
                'workspace_id' => $workspace->id,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? '',
                'engine_type' => $validated['engine'],
                'template_id' => $validated['template_id'] ?? null,
                'status' => 'draft',
            ]);

            return redirect()->route('games.show', $game->id)
                ->with('success', 'Game created successfully!');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to create game: ' . $e->getMessage()]);
        }
    }

    /**
     * Display the specified game.
     */
    public function show(Game $game): Response
    {
        // Check if user has access to this game
        $user = auth()->user();
        $company = $user->currentCompany;
        
        if (!$company || $game->workspace->company_id !== $company->id) {
            abort(403, 'You do not have access to this game.');
        }

        // Load game files
        $files = [];

        return Inertia::render('Games/Show', [
            'game' => array_merge($game->toArray(), [
                'files' => $files,
                'engine' => $game->getEngineType(),
                'preview_url' => $game->preview_url,
                'published_url' => $game->published_url,
                'version' => $game->version ?? '1.0.0',
                'is_public' => $game->is_public ?? false,
                'share_token' => $game->share_token,
                'build_status' => $game->build_status ?? 'none',
                'last_build_at' => $game->last_build_at?->toISOString(),
                'published_at' => $game->published_at?->toISOString(),
                'sharing_settings' => $game->sharing_settings ?? [
                    'allow_embedding' => true,
                    'show_controls' => true,
                    'show_info' => true,
                ],
                'metadata' => [
                    'version' => $game->version ?? '1.0.0',
                    'tags' => $game->tags ?? [],
                    'playCount' => $game->play_count ?? 0,
                    'lastPlayed' => $game->last_played_at?->toISOString(),
                ],
            ]),
        ]);
    }

    /**
     * Show the form for editing the specified game.
     */
    public function edit(Game $game): Response
    {
        // Check if user has access to this game
        $user = auth()->user();
        $company = $user->currentCompany;
        
        if (!$company || $game->workspace->company_id !== $company->id) {
            abort(403, 'You do not have access to this game.');
        }

        return $this->show($game);
    }

    /**
     * Update the specified game in storage.
     */
    public function update(Request $request, Game $game)
    {
        // Check if user has access to this game
        $user = auth()->user();
        $company = $user->currentCompany;
        
        if (!$company || $game->workspace->company_id !== $company->id) {
            abort(403, 'You do not have access to this game.');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'status' => 'required|in:draft,published,archived',
        ]);

        try {
            $game->update($validated);

            return back()->with('success', 'Game updated successfully!');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to update game: ' . $e->getMessage()]);
        }
    }

    /**
     * Remove the specified game from storage.
     */
    public function destroy(Game $game)
    {
        // Check if user has access to this game
        $user = auth()->user();
        $company = $user->currentCompany;
        
        if (!$company || $game->workspace->company_id !== $company->id) {
            abort(403, 'You do not have access to this game.');
        }

        try {
            $game->delete();

            return redirect()->route('games.index')
                ->with('success', 'Game deleted successfully!');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to delete game: ' . $e->getMessage()]);
        }
    }

    /**
     * Play the specified game.
     */
    public function play(Game $game): Response
    {
        // Check if user has access to this game
        $user = auth()->user();
        $company = $user->currentCompany;
        
        if (!$company || $game->workspace->company_id !== $company->id) {
            abort(403, 'You do not have access to this game.');
        }

        // Increment play count
        $game->incrementPlayCount();

        return Inertia::render('Games/Play', [
            'game' => array_merge($game->toArray(), [
                'engine' => $game->getEngineType(),
                'preview_url' => $game->preview_url,
                'published_url' => $game->published_url,
                'metadata' => [
                    'version' => $game->version ?? '1.0.0',
                    'tags' => $game->tags ?? [],
                    'playCount' => $game->play_count ?? 0,
                    'lastPlayed' => $game->last_played_at?->toISOString(),
                ],
            ]),
        ]);
    }
}