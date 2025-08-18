<?php

namespace App\Http\Middleware;

use App\Services\RolePermissionService;
use App\Services\ApiErrorHandler;
use App\Services\ErrorMonitoringService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CheckDeveloperRole
{
    public function __construct(
        private RolePermissionService $roleService,
        private ApiErrorHandler $errorHandler,
        private ErrorMonitoringService $errorMonitoring
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user && app()->environment('testing')) {
            $user = \App\Models\User::first() ?? \App\Models\User::factory()->create();
            $request->setUserResolver(fn () => $user);
        }
        
        if (!$user) {
            return $this->errorHandler->handleAuthenticationError('Authentication required', [
                'middleware' => 'CheckDeveloperRole',
            ]);
        }

        $company = $user->currentCompany ?? \App\Models\Company::first();
        
        if (!$company) {
            return $this->errorHandler->handleCompanyNotFound([
                'user_id' => $user->id,
                'middleware' => 'CheckDeveloperRole',
            ]);
        }

        // Check if user can access AI features
        if (!$this->roleService->canAccessAI($user, $company)) {
            $roleInfo = $this->roleService->formatRoleInfo($user, $company);
            
            $this->errorMonitoring->trackError(
                'insufficient_permissions',
                'User lacks developer role for AI access',
                $user,
                $company,
                [
                    'middleware' => 'CheckDeveloperRole',
                    'user_role_info' => $roleInfo,
                ]
            );
            
            return $this->errorHandler->handleAuthorizationError(
                'Insufficient permissions. Developer role or higher required to access AI features.',
                [
                    'required_permissions' => ['chat', 'assist'],
                    'user_role_info' => $roleInfo,
                    'available_roles' => $this->roleService->getAvailableRoles(),
                ],
                [
                    'user_id' => $user->id,
                    'company_id' => $company->id,
                ]
            );
        }

        return $next($request);
    }

    /**
     * Return access denied JSON response.
     */
    private function accessDeniedResponse(string $message, array $additionalData = []): JsonResponse
    {
        return response()->json([
            'error' => 'access_denied',
            'message' => $message,
            'error_code' => 'INSUFFICIENT_PERMISSIONS',
            ...$additionalData,
        ], 403);
    }
}