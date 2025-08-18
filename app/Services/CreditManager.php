<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CreditTransaction;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CreditManager
{
    /**
     * Deduct credits from a company's balance.
     *
     * @param Company $company
     * @param int|float $amount Total amount including tokens and surcharges
     * @param string $description
     * @param array $metadata
     * @return bool
     */
    public function deductCredits(Company $company, int|float $amount, string $description = 'AI API usage', array $metadata = []): bool
    {
        if (!$this->canAffordRequest($company, $amount)) {
            return false;
        }

        return DB::transaction(function () use ($company, $amount, $description, $metadata) {
            // Create debit transaction
            CreditTransaction::create([
                'company_id' => $company->id,
                'amount' => $amount,
                'type' => 'debit',
                'description' => $description,
                'metadata' => $metadata,
            ]);

            // Update company credits
            $company->decrement('credits', $amount);

            return true;
        });
    }

    /**
     * Add credits to a company's balance.
     *
     * @param Company $company
     * @param int $amount
     * @param string $reason
     * @param array $metadata
     * @return void
     */
    public function addCredits(Company $company, int $amount, string $reason, array $metadata = []): void
    {
        DB::transaction(function () use ($company, $amount, $reason, $metadata) {
            // Create credit transaction
            CreditTransaction::create([
                'company_id' => $company->id,
                'amount' => $amount,
                'type' => 'credit',
                'description' => $reason,
                'metadata' => $metadata,
            ]);

            // Update company credits
            $company->increment('credits', $amount);
        });
    }

    /**
     * Get usage analytics for a company within a date range.
     *
     * @param Company $company
     * @param Carbon $from
     * @param Carbon $to
     * @return array
     */
    public function getUsageAnalytics(Company $company, Carbon $from, Carbon $to): array
    {
        $transactions = $company->creditTransactions()
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at')
            ->get();

        $dailyUsage = [];
        $totalDebits = 0;
        $totalCredits = 0;

        foreach ($transactions as $transaction) {
            $date = $transaction->created_at->format('Y-m-d');
            
            if (!isset($dailyUsage[$date])) {
                $dailyUsage[$date] = ['debits' => 0, 'credits' => 0];
            }

            if ($transaction->type === 'debit') {
                $dailyUsage[$date]['debits'] += $transaction->amount;
                $totalDebits += $transaction->amount;
            } else {
                $dailyUsage[$date]['credits'] += $transaction->amount;
                $totalCredits += $transaction->amount;
            }
        }

        return [
            'daily_usage' => $dailyUsage,
            'total_debits' => $totalDebits,
            'total_credits' => $totalCredits,
            'net_usage' => $totalDebits - $totalCredits,
            'transaction_count' => $transactions->count(),
        ];
    }

    /**
     * Check if a company can afford a request with estimated cost.
     *
     * @param Company $company
     * @param int|float $estimatedCost
     * @return bool
     */
    public function canAffordRequest(Company $company, int|float $estimatedCost): bool
    {
        return $company->credits >= $estimatedCost;
    }

    /**
     * Get the current month's usage for a company.
     *
     * @param Company $company
     * @return float
     */
    public function getCurrentMonthUsage(Company $company): float
    {
        return (float) $company->creditTransactions()
            ->debits()
            ->forMonth(now()->month, now()->year)
            ->sum('amount');
    }

    /**
     * Get recent transactions for a company.
     *
     * @param Company $company
     * @param int $limit
     * @return Collection
     */
    public function getRecentTransactions(Company $company, int $limit = 50): Collection
    {
        return $company->creditTransactions()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Check if company is approaching credit limit.
     *
     * @param Company $company
     * @param float $threshold (percentage, e.g., 0.1 for 10%)
     * @return bool
     */
    public function isApproachingLimit(Company $company, float $threshold = 0.1): bool
    {
        // Prefer explicit company attribute if set; otherwise fall back to accessor (which may use plan)
        $explicitLimit = (float) ($company->getAttributes()['monthly_credit_limit'] ?? 0);
        $effectiveLimit = $explicitLimit > 0 ? $explicitLimit : (float) $company->monthly_credit_limit;

        if ($effectiveLimit <= 0) {
            return false;
        }

        $currentUsage = $this->getCurrentMonthUsage($company);
        // Trigger when usage is at least (1 - threshold) of limit. Use >= to consider equal as approaching.
        $warningThreshold = $effectiveLimit * (1 - $threshold);

        return $currentUsage >= $warningThreshold;
    }

    /**
     * Get credit balance summary for a company.
     *
     * @param Company $company
     * @return array
     */
    public function getBalanceSummary(Company $company): array
    {
        $currentUsage = $this->getCurrentMonthUsage($company);
        // Prefer explicit company monthly_credit_limit when present; otherwise use accessor (plan-based)
        $explicitLimit = (float) ($company->getAttributes()['monthly_credit_limit'] ?? 0);
        $effectiveLimit = $explicitLimit > 0 ? $explicitLimit : (float) $company->monthly_credit_limit;
        
        return [
            'current_credits' => $company->credits,
            'monthly_limit' => $effectiveLimit,
            'current_month_usage' => $currentUsage,
            'remaining_monthly_allowance' => max(0, $effectiveLimit - $currentUsage),
            'is_approaching_limit' => $this->isApproachingLimit($company),
            'plan' => $company->plan,
        ];
    }

    /**
     * Calculate MCP surcharge for PlayCanvas operations.
     *
     * @param string $engineType
     * @param int $actionCount
     * @return float
     */
    public function calculateMcpSurcharge(string $engineType, int $actionCount = 1): float
    {
        if ($engineType === 'playcanvas') {
            return 0.1 * $actionCount;
        }
        
        return 0.0;
    }

    /**
     * Deduct credits with MCP surcharge for engine-specific operations.
     *
     * @param Company $company
     * @param int $tokens
     * @param string $engineType
     * @param string $description
     * @param array $additionalMetadata
     * @return bool
     */
    public function deductCreditsWithMcpSurcharge(
        Company $company, 
        int $tokens, 
        string $engineType, 
        string $description = 'MCP Command',
        array $additionalMetadata = []
    ): bool {
        $mcpSurcharge = $this->calculateMcpSurcharge($engineType);
        $totalCost = $tokens + $mcpSurcharge;

        $metadata = array_merge([
            'engine_type' => $engineType,
            'base_tokens' => $tokens,
            'mcp_surcharge' => $mcpSurcharge,
            'total_cost' => $totalCost,
            'has_mcp_surcharge' => $mcpSurcharge > 0,
        ], $additionalMetadata);

        return $this->deductCredits($company, $totalCost, $description, $metadata);
    }

    /**
     * Get credit usage analytics by engine type.
     *
     * @param Company $company
     * @param Carbon $from
     * @param Carbon $to
     * @return array
     */
    public function getEngineUsageAnalytics(Company $company, Carbon $from, Carbon $to): array
    {
        $transactions = $company->creditTransactions()
            ->whereBetween('created_at', [$from, $to])
            ->where('type', 'debit')
            ->orderBy('created_at')
            ->get();

        $analytics = [
            'total_usage' => 0,
            'total_mcp_surcharges' => 0,
            'engine_breakdown' => [
                'unreal' => [
                    'usage' => 0,
                    'transactions' => 0,
                    'mcp_surcharge' => 0,
                ],
                'playcanvas' => [
                    'usage' => 0,
                    'transactions' => 0,
                    'mcp_surcharge' => 0,
                ],
                'other' => [
                    'usage' => 0,
                    'transactions' => 0,
                    'mcp_surcharge' => 0,
                ]
            ],
            'daily_breakdown' => [],
        ];

        foreach ($transactions as $transaction) {
            $date = $transaction->created_at->format('Y-m-d');
            $metadata = $transaction->metadata ?? [];
            $engineType = $metadata['engine_type'] ?? 'other';
            $mcpSurcharge = $metadata['mcp_surcharge'] ?? 0;

            // Initialize daily breakdown if not exists
            if (!isset($analytics['daily_breakdown'][$date])) {
                $analytics['daily_breakdown'][$date] = [
                    'total' => 0,
                    'unreal' => 0,
                    'playcanvas' => 0,
                    'other' => 0,
                    'mcp_surcharges' => 0,
                ];
            }

            // Update totals
            $analytics['total_usage'] += $transaction->amount;
            $analytics['total_mcp_surcharges'] += $mcpSurcharge;

            // Update engine breakdown
            if (isset($analytics['engine_breakdown'][$engineType])) {
                $analytics['engine_breakdown'][$engineType]['usage'] += $transaction->amount;
                $analytics['engine_breakdown'][$engineType]['transactions']++;
                $analytics['engine_breakdown'][$engineType]['mcp_surcharge'] += $mcpSurcharge;
            } else {
                $analytics['engine_breakdown']['other']['usage'] += $transaction->amount;
                $analytics['engine_breakdown']['other']['transactions']++;
                $analytics['engine_breakdown']['other']['mcp_surcharge'] += $mcpSurcharge;
            }

            // Update daily breakdown
            $analytics['daily_breakdown'][$date]['total'] += $transaction->amount;
            $analytics['daily_breakdown'][$date][$engineType] += $transaction->amount;
            $analytics['daily_breakdown'][$date]['mcp_surcharges'] += $mcpSurcharge;
        }

        return $analytics;
    }

    /**
     * Get real-time credit balance with pending operations.
     *
     * @param Company $company
     * @return array
     */
    public function getRealTimeBalance(Company $company): array
    {
        $company->refresh(); // Ensure we have the latest balance
        
        return [
            'current_credits' => $company->credits,
            'last_updated' => $company->updated_at->toISOString(),
            'balance_summary' => $this->getBalanceSummary($company),
        ];
    }
}