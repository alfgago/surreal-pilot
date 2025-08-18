<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CreditManager;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreditController extends Controller
{
    public function __construct(
        private CreditManager $creditManager
    ) {}

    /**
     * Get real-time credit balance for the authenticated user's company.
     */
    public function getRealTimeBalance(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $user->currentCompany;

        if (!$company) {
            return response()->json([
                'success' => false,
                'error' => 'No company associated with user',
            ], 400);
        }

        $balance = $this->creditManager->getRealTimeBalance($company);

        return response()->json([
            'success' => true,
            'data' => $balance,
        ]);
    }

    /**
     * Get credit usage analytics by engine type.
     */
    public function getEngineUsageAnalytics(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $user->currentCompany;

        if (!$company) {
            return response()->json([
                'success' => false,
                'error' => 'No company associated with user',
            ], 400);
        }

        $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'period' => 'nullable|in:week,month,quarter,year',
        ]);

        // Set default date range based on period or use provided dates
        if ($request->has('period')) {
            $period = $request->input('period');
            $to = Carbon::now();
            
            $from = match($period) {
                'week' => $to->copy()->subWeek(),
                'month' => $to->copy()->subMonth(),
                'quarter' => $to->copy()->subQuarter(),
                'year' => $to->copy()->subYear(),
                default => $to->copy()->subMonth(),
            };
        } else {
            $from = $request->has('from') 
                ? Carbon::parse($request->input('from'))
                : Carbon::now()->subMonth();
            
            $to = $request->has('to')
                ? Carbon::parse($request->input('to'))
                : Carbon::now();
        }

        $analytics = $this->creditManager->getEngineUsageAnalytics($company, $from, $to);

        return response()->json([
            'success' => true,
            'data' => [
                'analytics' => $analytics,
                'period' => [
                    'from' => $from->toISOString(),
                    'to' => $to->toISOString(),
                ],
            ],
        ]);
    }

    /**
     * Get detailed credit transaction history with engine filtering.
     */
    public function getTransactionHistory(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $user->currentCompany;

        if (!$company) {
            return response()->json([
                'success' => false,
                'error' => 'No company associated with user',
            ], 400);
        }

        $request->validate([
            'engine_type' => 'nullable|in:unreal,playcanvas',
            'limit' => 'nullable|integer|min:1|max:100',
            'offset' => 'nullable|integer|min:0',
        ]);

        $query = $company->creditTransactions()
            ->orderBy('created_at', 'desc');

        // Filter by engine type if specified
        if ($request->has('engine_type')) {
            $engineType = $request->input('engine_type');
            $query->whereJsonContains('metadata->engine_type', $engineType);
        }

        $limit = $request->input('limit', 50);
        $offset = $request->input('offset', 0);

        $transactions = $query->skip($offset)->take($limit)->get();
        $total = $query->count();

        return response()->json([
            'success' => true,
            'data' => [
                'transactions' => $transactions,
                'pagination' => [
                    'total' => $total,
                    'limit' => $limit,
                    'offset' => $offset,
                    'has_more' => ($offset + $limit) < $total,
                ],
            ],
        ]);
    }

    /**
     * Get MCP surcharge information for different engine types.
     */
    public function getMcpSurchargeInfo(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'surcharge_rates' => [
                    'unreal' => $this->creditManager->calculateMcpSurcharge('unreal'),
                    'playcanvas' => $this->creditManager->calculateMcpSurcharge('playcanvas'),
                ],
                'description' => 'MCP surcharge is applied per action for PlayCanvas operations',
            ],
        ]);
    }
}