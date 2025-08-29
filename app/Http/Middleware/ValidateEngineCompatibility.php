<?php

namespace App\Http\Middleware;

use App\Services\ApiErrorHandler;
use App\Services\EngineSelectionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateEngineCompatibility
{
    public function __construct(
        private EngineSelectionService $engineService,
        private ApiErrorHandler $errorHandler
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ?string $requiredEngine = null): Response
    {
        $user = $request->user();
        
        if (!$user) {
            return $this->errorHandler->handleAuthenticationError();
        }

        // Check if user has selected an engine
        $userEngine = $this->engineService->getUserEnginePreference($user);
        
        if (!$userEngine) {
            return response()->json([
                'success' => false,
                'error' => 'engine_not_selected',
                'error_code' => 'ENGINE_NOT_SELECTED',
                'message' => 'No engine type selected.',
                'user_message' => 'Please select an engine type (PlayCanvas or Unreal Engine) before proceeding.',
                'data' => [
                    'actions' => [
                        'select_engine' => '/api/engines',
                        'set_preference' => '/api/user/engine-preference',
                    ],
                ],
            ], 400);
        }

        // Check if specific engine is required
        if ($requiredEngine && $userEngine !== $requiredEngine) {
            return response()->json([
                'success' => false,
                'error' => 'engine_mismatch',
                'error_code' => 'ENGINE_MISMATCH',
                'message' => "This action requires {$requiredEngine} engine, but you have {$userEngine} selected.",
                'user_message' => "This feature is only available for {$requiredEngine} projects. Please switch your engine preference or use a {$requiredEngine} workspace.",
                'data' => [
                    'required_engine' => $requiredEngine,
                    'current_engine' => $userEngine,
                    'actions' => [
                        'change_engine' => '/api/user/engine-preference',
                        'view_workspaces' => '/api/workspaces',
                    ],
                ],
            ], 409);
        }

        // Check if user can access the selected engine
        if (!$this->engineService->canUserAccessEngine($user, $userEngine)) {
            return response()->json([
                'success' => false,
                'error' => 'engine_access_denied',
                'error_code' => 'ENGINE_ACCESS_DENIED',
                'message' => "You don't have access to {$userEngine} engine.",
                'user_message' => "Your current plan doesn't include access to {$userEngine}. Please upgrade your plan or contact your administrator.",
                'data' => [
                    'engine' => $userEngine,
                    'actions' => [
                        'upgrade_plan' => '/dashboard/billing/plans',
                        'contact_admin' => 'Contact your company administrator',
                        'select_different_engine' => '/api/engines',
                    ],
                ],
            ], 403);
        }

        // Add engine context to request for controllers
        $request->attributes->set('user_engine', $userEngine);

        return $next($request);
    }
}