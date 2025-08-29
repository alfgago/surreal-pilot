<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use App\Services\ApiErrorHandler;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateWorkspaceAccess
{
    public function __construct(
        private ApiErrorHandler $errorHandler
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        if (!$user) {
            return $this->errorHandler->handleAuthenticationError();
        }

        $company = $user->currentCompany;
        if (!$company) {
            return $this->errorHandler->handleCompanyNotFound();
        }

        // Get workspace ID from route parameters
        $workspaceId = $request->route('workspaceId') ?? $request->route('workspace');
        
        if ($workspaceId) {
            try {
                $workspace = Workspace::where('id', $workspaceId)
                    ->where('company_id', $company->id)
                    ->firstOrFail();

                // Add workspace to request attributes for controllers
                $request->attributes->set('workspace', $workspace);

                // Validate engine compatibility if user has engine preference
                $userEngine = $user->selected_engine_type;
                if ($userEngine && $workspace->engine_type !== $userEngine) {
                    return response()->json([
                        'success' => false,
                        'error' => 'workspace_engine_mismatch',
                        'error_code' => 'WORKSPACE_ENGINE_MISMATCH',
                        'message' => "Workspace engine type ({$workspace->engine_type}) doesn't match your selected engine ({$userEngine}).",
                        'user_message' => "This workspace is configured for {$workspace->engine_type}, but you have {$userEngine} selected. Please switch your engine preference or select a different workspace.",
                        'data' => [
                            'workspace_engine' => $workspace->engine_type,
                            'user_engine' => $userEngine,
                            'workspace_name' => $workspace->name,
                            'actions' => [
                                'change_engine_preference' => '/api/user/engine-preference',
                                'select_different_workspace' => '/api/workspaces',
                            ],
                        ],
                    ], 409);
                }

            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                return response()->json([
                    'success' => false,
                    'error' => 'workspace_not_found',
                    'error_code' => 'WORKSPACE_NOT_FOUND',
                    'message' => 'The specified workspace was not found.',
                    'user_message' => 'The workspace you\'re trying to access doesn\'t exist or you don\'t have permission to access it.',
                    'data' => [
                        'workspace_id' => $workspaceId,
                        'actions' => [
                            'view_workspaces' => '/api/workspaces',
                            'create_workspace' => '/api/prototype',
                        ],
                    ],
                ], 404);
            }
        }

        return $next($request);
    }
}