<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPlanCapability
{
    public function handle(Request $request, Closure $next, string $capability): Response
    {
        $user = $request->user();
        $company = $user?->currentCompany;
        if (!$company) {
            return app(\App\Services\ApiErrorHandler::class)->handleCompanyNotFound([
                'middleware' => 'CheckPlanCapability',
            ]);
        }

        $plan = $company->subscriptionPlan; // relation on Company model
        if (!$plan) {
            return response()->json(['error' => 'no_plan'], 402);
        }

        $allowed = match ($capability) {
            'unreal' => (bool) $plan->allow_unreal,
            'multiplayer' => (bool) $plan->allow_multiplayer,
            'advanced_publish' => (bool) $plan->allow_advanced_publish,
            'byo_keys' => (bool) $plan->allow_byo_keys,
            default => true,
        };

        if (!$allowed) {
            return response()->json([
                'error' => 'plan_capability_required',
                'capability' => $capability,
                'plan' => $plan->slug,
            ], 403);
        }

        return $next($request);
    }
}

