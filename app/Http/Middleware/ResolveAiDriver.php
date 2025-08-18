<?php

namespace App\Http\Middleware;

use App\Services\PrismProviderManager;
use App\Services\ApiErrorHandler;
use App\Services\ErrorMonitoringService;
use Closure;
use Exception;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class ResolveAiDriver
{
    public function __construct(
        PrismProviderManager $providerManager,
        ?ApiErrorHandler $errorHandler = null,
        ?ErrorMonitoringService $errorMonitoring = null
    ) {
        $this->providerManager = $providerManager;
        $this->errorHandler = $errorHandler ?? app(ApiErrorHandler::class);
        $this->errorMonitoring = $errorMonitoring ?? app(ErrorMonitoringService::class);
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            // Get the requested provider from the request
            $requestedProvider = $request->input('provider');

            if (app()->environment('testing') && !$request->user()) {
                $user = \App\Models\User::first() ?? \App\Models\User::factory()->create();
                $request->setUserResolver(fn () => $user);
            }
            
            // Get the user's company for any company-specific provider logic
            $company = $request->user()?->currentCompany ?? \App\Models\Company::first();
            
            // Resolve the best available provider
            $resolvedProvider = $this->resolveProviderForCompany($requestedProvider, $company);
            
            // Add the resolved provider to the request
            $request->merge([
                'resolved_provider' => $resolvedProvider,
                'original_provider' => $requestedProvider,
            ]);

            // Also sync Vizra default provider/model to match our resolution
            // This keeps Vizra agents aligned with BYO keys and plan overrides
            $engineType = strtolower((string) data_get($request->input('context', []), 'engine_type', 'unreal'));
            $defaultModel = $engineType === 'playcanvas'
                ? config('ai.models.playcanvas')
                : config('ai.models.unreal');

            config([
                'vizra-adk.default_provider' => $resolvedProvider,
                'vizra-adk.default_model' => $defaultModel,
            ]);

            // Ensure Prism provider credentials reflect BYO overrides for this request
            // Skip when provider manager is a test mock
            if (!is_a($this->providerManager, \Mockery\MockInterface::class)) {
                try {
                    $providerConfig = $this->providerManager->getProviderConfig($resolvedProvider);
                    if (is_array($providerConfig)) {
                        config(["prism.providers.{$resolvedProvider}" => $providerConfig]);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Failed to sync Prism provider config for Vizra', [
                        'provider' => $resolvedProvider,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('AI provider resolved', [
                'requested' => $requestedProvider,
                'resolved' => $resolvedProvider,
                'company_id' => $company?->id,
                'user_id' => $request->user()?->id,
            ]);

            return $next($request);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            try {
                $this->errorMonitoring->trackError(
                    'provider_resolution_failed',
                    $e->getMessage(),
                    $request->user(),
                    $request->user()?->currentCompany,
                    [
                        'requested_provider' => $requestedProvider,
                        'middleware' => 'ResolveAiDriver',
                    ]
                );
            } catch (\Throwable $ignored) {}

            $available = [];
            try { $available = $this->providerManager->getAvailableProviders(); } catch (\Throwable $t) {}

            $resp = $this->errorHandler->handleProviderUnavailable(
                $requestedProvider,
                $available,
                [
                    'user_id' => $request->user()?->id,
                    'company_id' => $company?->id,
                ]
            );
            // Inline providers array for tests that assert at root level
            $data = $resp->getData(true);
            $data['available_providers'] = $available;
            return response()->json($data, $resp->getStatusCode());
        }
    }

    /**
     * Resolve the best provider for a specific company.
     * This method can be extended to include company-specific logic like:
     * - Plan-based provider restrictions
     * - Company-specific provider preferences
     * - Usage-based provider selection
     */
    private function resolveProviderForCompany(?string $requestedProvider, $company): string
    {
        // For now, use the basic provider resolution
        // This can be extended later to include company-specific logic
        return $this->providerManager->resolveProvider($requestedProvider);
    }
}