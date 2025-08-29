<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CreditManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BillingController extends Controller
{
    public function __construct(
        private CreditManager $creditManager
    ) {}

    /**
     * Get real-time credit balance for the current company.
     */
    public function balance(): JsonResponse
    {
        $user = Auth::user();
        $company = $user->currentCompany ?: $user->companies()->first();
        
        if (!$company) {
            return response()->json(['error' => 'No company found'], 404);
        }

        $balance = $this->creditManager->getRealTimeBalance($company);
        
        return response()->json($balance);
    }

    /**
     * Get usage analytics for a specific date range.
     */
    public function analytics(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
        ]);

        $user = Auth::user();
        $company = $user->currentCompany ?: $user->companies()->first();
        
        if (!$company) {
            return response()->json(['error' => 'No company found'], 404);
        }

        $from = \Carbon\Carbon::parse($request->from);
        $to = \Carbon\Carbon::parse($request->to);

        $analytics = $this->creditManager->getUsageAnalytics($company, $from, $to);
        $engineAnalytics = $this->creditManager->getEngineUsageAnalytics($company, $from, $to);

        return response()->json([
            'analytics' => $analytics,
            'engine_analytics' => $engineAnalytics,
        ]);
    }

    /**
     * Get recent transactions with pagination.
     */
    public function transactions(Request $request): JsonResponse
    {
        $request->validate([
            'limit' => 'integer|min:1|max:100',
            'offset' => 'integer|min:0',
        ]);

        $user = Auth::user();
        $company = $user->currentCompany ?: $user->companies()->first();
        
        if (!$company) {
            return response()->json(['error' => 'No company found'], 404);
        }

        $limit = $request->get('limit', 20);
        $offset = $request->get('offset', 0);

        $transactions = $company->creditTransactions()
            ->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        $total = $company->creditTransactions()->count();

        return response()->json([
            'transactions' => $transactions,
            'total' => $total,
            'has_more' => ($offset + $limit) < $total,
        ]);
    }

    /**
     * Get subscription status and billing information.
     */
    public function subscription(): JsonResponse
    {
        $user = Auth::user();
        $company = $user->currentCompany ?: $user->companies()->first();
        
        if (!$company) {
            return response()->json(['error' => 'No company found'], 404);
        }

        // Note: Subscription functionality requires database schema updates
        $currentSubscription = null;
        $subscriptionPlans = \App\Models\SubscriptionPlan::all();
        
        return response()->json([
            'current_subscription' => $currentSubscription,
            'available_plans' => $subscriptionPlans,
            'company_plan' => $company->plan,
        ]);
    }

    /**
     * Get billing summary with key metrics.
     */
    public function summary(): JsonResponse
    {
        $user = Auth::user();
        $company = $user->currentCompany ?: $user->companies()->first();
        
        if (!$company) {
            return response()->json(['error' => 'No company found'], 404);
        }

        $balanceSummary = $this->creditManager->getBalanceSummary($company);
        $currentMonthUsage = $this->creditManager->getCurrentMonthUsage($company);
        
        // Get usage trend (compare with previous month)
        $previousMonth = now()->subMonth();
        $previousMonthAnalytics = $this->creditManager->getUsageAnalytics(
            $company, 
            $previousMonth->startOfMonth(), 
            $previousMonth->endOfMonth()
        );

        $usageTrend = $currentMonthUsage - $previousMonthAnalytics['total_debits'];
        $usageTrendPercentage = $previousMonthAnalytics['total_debits'] > 0 
            ? ($usageTrend / $previousMonthAnalytics['total_debits']) * 100 
            : 0;

        return response()->json([
            'balance_summary' => $balanceSummary,
            'current_month_usage' => $currentMonthUsage,
            'usage_trend' => [
                'absolute' => $usageTrend,
                'percentage' => $usageTrendPercentage,
                'direction' => $usageTrend > 0 ? 'up' : ($usageTrend < 0 ? 'down' : 'stable'),
            ],
            'last_updated' => now()->toISOString(),
        ]);
    }
}