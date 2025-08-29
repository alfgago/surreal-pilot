<?php

namespace App\Http\Controllers;

use App\Models\MultiplayerSession;
use App\Models\Workspace;
use App\Services\MultiplayerService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MultiplayerController extends Controller
{
    private MultiplayerService $multiplayerService;

    public function __construct(MultiplayerService $multiplayerService)
    {
        $this->multiplayerService = $multiplayerService;
    }

    /**
     * Display the multiplayer sessions management page.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $company = $user->currentCompany;

        // Get active sessions
        $activeSessions = MultiplayerSession::whereHas('workspace', function ($query) use ($company) {
            $query->where('company_id', $company->id);
        })
        ->active()
        ->with(['workspace:id,name,engine_type'])
        ->orderBy('created_at', 'desc')
        ->get()
        ->map(function ($session) {
            return [
                'id' => $session->id,
                'workspace' => [
                    'id' => $session->workspace->id,
                    'name' => $session->workspace->name,
                    'engine_type' => $session->workspace->engine_type,
                ],
                'session_url' => $session->session_url,
                'status' => $session->status,
                'max_players' => $session->max_players,
                'current_players' => $session->current_players,
                'expires_at' => $session->expires_at,
                'created_at' => $session->created_at,
                'updated_at' => $session->updated_at,
            ];
        });

        // Get recent sessions (last 30 days)
        $recentSessions = MultiplayerSession::whereHas('workspace', function ($query) use ($company) {
            $query->where('company_id', $company->id);
        })
        ->where('created_at', '>=', now()->subDays(30))
        ->with(['workspace:id,name,engine_type'])
        ->orderBy('created_at', 'desc')
        ->limit(20)
        ->get()
        ->map(function ($session) {
            return [
                'id' => $session->id,
                'workspace' => [
                    'id' => $session->workspace->id,
                    'name' => $session->workspace->name,
                    'engine_type' => $session->workspace->engine_type,
                ],
                'session_url' => $session->session_url,
                'status' => $session->status,
                'max_players' => $session->max_players,
                'current_players' => $session->current_players,
                'expires_at' => $session->expires_at,
                'created_at' => $session->created_at,
                'updated_at' => $session->updated_at,
            ];
        });

        // Get available workspaces (PlayCanvas only)
        $workspaces = $company->workspaces()
            ->where('engine_type', 'playcanvas')
            ->where('status', 'active')
            ->select('id', 'name', 'engine_type', 'status')
            ->orderBy('name')
            ->get();

        // Get session stats
        $stats = [
            'active_sessions' => $activeSessions->count(),
            'total_sessions_this_month' => MultiplayerSession::whereHas('workspace', function ($query) use ($company) {
                $query->where('company_id', $company->id);
            })
            ->whereMonth('created_at', now()->month)
            ->count(),
            'total_players_this_month' => MultiplayerSession::whereHas('workspace', function ($query) use ($company) {
                $query->where('company_id', $company->id);
            })
            ->whereMonth('created_at', now()->month)
            ->sum('current_players'),
            'average_session_duration' => MultiplayerSession::whereHas('workspace', function ($query) use ($company) {
                $query->where('company_id', $company->id);
            })
            ->whereNotNull('expires_at')
            ->whereMonth('created_at', now()->month)
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, expires_at)) as avg_duration')
            ->value('avg_duration') ?? 0,
        ];

        return Inertia::render('Multiplayer/Index', [
            'activeSessions' => $activeSessions,
            'recentSessions' => $recentSessions,
            'workspaces' => $workspaces,
            'stats' => $stats,
        ]);
    }

    /**
     * Show a specific multiplayer session.
     */
    public function show(Request $request, string $sessionId): Response
    {
        $user = $request->user();
        $company = $user->currentCompany;

        $session = MultiplayerSession::whereHas('workspace', function ($query) use ($company) {
            $query->where('company_id', $company->id);
        })
        ->with(['workspace'])
        ->findOrFail($sessionId);

        return Inertia::render('Multiplayer/Show', [
            'session' => [
                'id' => $session->id,
                'workspace' => [
                    'id' => $session->workspace->id,
                    'name' => $session->workspace->name,
                    'engine_type' => $session->workspace->engine_type,
                ],
                'fargate_task_arn' => $session->fargate_task_arn,
                'ngrok_url' => $session->ngrok_url,
                'session_url' => $session->session_url,
                'status' => $session->status,
                'max_players' => $session->max_players,
                'current_players' => $session->current_players,
                'expires_at' => $session->expires_at,
                'created_at' => $session->created_at,
                'updated_at' => $session->updated_at,
            ],
        ]);
    }
}