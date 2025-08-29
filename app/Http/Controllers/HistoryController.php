<?php

namespace App\Http\Controllers;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\CreditTransaction;
use App\Models\Game;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HistoryController extends Controller
{
    /**
     * Display the activity history.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $company = $user->currentCompany;
        $type = $request->get('type', 'all');
        $search = $request->get('search', '');
        $workspaceId = $request->get('workspace');

        // Get user's workspaces for filtering
        $workspaces = $company->workspaces()
            ->select('id', 'name', 'engine_type')
            ->orderBy('name')
            ->get();

        // Build activity timeline
        $activities = collect();

        // Chat conversations and messages
        if ($type === 'all' || $type === 'chat') {
            $conversationsQuery = ChatConversation::with(['workspace:id,name'])
                ->whereHas('workspace', function ($query) use ($company) {
                    $query->where('company_id', $company->id);
                });

            if ($workspaceId) {
                $conversationsQuery->where('workspace_id', $workspaceId);
            }

            if ($search) {
                $conversationsQuery->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhereHas('messages', function ($messageQuery) use ($search) {
                          $messageQuery->where('content', 'like', "%{$search}%");
                      });
                });
            }

            $conversations = $conversationsQuery
                ->orderBy('updated_at', 'desc')
                ->limit(50)
                ->get();

            foreach ($conversations as $conversation) {
                $activities->push([
                    'id' => 'conversation-' . $conversation->id,
                    'type' => 'chat',
                    'title' => $conversation->title ?: 'Untitled Conversation',
                    'description' => $conversation->description,
                    'workspace' => $conversation->workspace ? [
                        'id' => $conversation->workspace->id,
                        'name' => $conversation->workspace->name,
                    ] : null,
                    'metadata' => [
                        'conversation_id' => $conversation->id,
                        'message_count' => $conversation->messages()->count(),
                    ],
                    'created_at' => $conversation->created_at,
                    'updated_at' => $conversation->updated_at,
                ]);
            }
        }

        // Games
        if ($type === 'all' || $type === 'games') {
            $gamesQuery = Game::with(['workspace:id,name'])
                ->whereHas('workspace', function ($query) use ($company) {
                    $query->where('company_id', $company->id);
                });

            if ($workspaceId) {
                $gamesQuery->where('workspace_id', $workspaceId);
            }

            if ($search) {
                $gamesQuery->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhere('status', 'like', "%{$search}%");
                });
            }

            $games = $gamesQuery
                ->orderBy('updated_at', 'desc')
                ->limit(50)
                ->get();

            foreach ($games as $game) {
                $activities->push([
                    'id' => 'game-' . $game->id,
                    'type' => 'game',
                    'title' => $game->title,
                    'description' => $game->description,
                    'workspace' => $game->workspace ? [
                        'id' => $game->workspace->id,
                        'name' => $game->workspace->name,
                    ] : null,
                    'metadata' => [
                        'game_id' => $game->id,
                        'status' => $game->status,
                        'play_count' => $game->play_count,
                        'is_published' => $game->is_public,
                    ],
                    'created_at' => $game->created_at,
                    'updated_at' => $game->updated_at,
                ]);
            }
        }

        // Credit transactions
        if ($type === 'all' || $type === 'credits') {
            $creditsQuery = CreditTransaction::where('company_id', $company->id);

            if ($search) {
                $creditsQuery->where(function ($q) use ($search) {
                    $q->where('description', 'like', "%{$search}%")
                      ->orWhere('type', 'like', "%{$search}%");
                });
            }

            $credits = $creditsQuery
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get();

            foreach ($credits as $credit) {
                $activities->push([
                    'id' => 'credit-' . $credit->id,
                    'type' => 'credit',
                    'title' => $credit->description,
                    'description' => "Credit {$credit->type}: " . ($credit->amount > 0 ? '+' : '') . $credit->amount,
                    'workspace' => null,
                    'metadata' => [
                        'amount' => $credit->amount,
                        'transaction_type' => $credit->type,
                        'metadata' => $credit->metadata ? json_decode($credit->metadata, true) : null,
                    ],
                    'created_at' => $credit->created_at,
                    'updated_at' => $credit->updated_at,
                ]);
            }
        }

        // Workspaces
        if ($type === 'all' || $type === 'workspaces') {
            $workspacesQuery = Workspace::where('company_id', $company->id);

            if ($search) {
                $workspacesQuery->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('engine_type', 'like', "%{$search}%")
                      ->orWhere('status', 'like', "%{$search}%");
                });
            }

            $workspacesList = $workspacesQuery
                ->orderBy('updated_at', 'desc')
                ->limit(50)
                ->get();

            foreach ($workspacesList as $workspace) {
                $activities->push([
                    'id' => 'workspace-' . $workspace->id,
                    'type' => 'workspace',
                    'title' => $workspace->name,
                    'description' => "Workspace created for {$workspace->engine_type}",
                    'workspace' => [
                        'id' => $workspace->id,
                        'name' => $workspace->name,
                    ],
                    'metadata' => [
                        'workspace_id' => $workspace->id,
                        'engine_type' => $workspace->engine_type,
                        'status' => $workspace->status,
                        'template_id' => $workspace->template_id,
                    ],
                    'created_at' => $workspace->created_at,
                    'updated_at' => $workspace->updated_at,
                ]);
            }
        }

        // Sort activities by updated_at descending
        $activities = $activities->sortByDesc('updated_at')->take(100)->values();

        // Get activity stats
        $stats = [
            'total_conversations' => ChatConversation::whereHas('workspace', function ($query) use ($company) {
                $query->where('company_id', $company->id);
            })->count(),
            'total_games' => Game::whereHas('workspace', function ($query) use ($company) {
                $query->where('company_id', $company->id);
            })->count(),
            'total_workspaces' => $company->workspaces()->count(),
            'credits_used_this_month' => CreditTransaction::where('company_id', $company->id)
                ->where('amount', '<', 0)
                ->whereMonth('created_at', now()->month)
                ->sum('amount') * -1,
        ];

        return Inertia::render('History/Index', [
            'activities' => $activities,
            'workspaces' => $workspaces,
            'filters' => [
                'type' => $type,
                'search' => $search,
                'workspace' => $workspaceId,
            ],
            'stats' => $stats,
        ]);
    }
}