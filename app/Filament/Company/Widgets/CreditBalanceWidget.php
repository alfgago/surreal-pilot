<?php

namespace App\Filament\Company\Widgets;

use App\Services\CreditManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CreditBalanceWidget extends BaseWidget
{
    protected ?string $pollingInterval = '30s';
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $company = Filament::getTenant();

        if (!$company) {
            return [];
        }

        $creditManager = app(CreditManager::class);
        $balanceSummary = $creditManager->getBalanceSummary($company);

        $currentCredits = Stat::make('Current Credits', number_format($balanceSummary['current_credits']))
            ->description('Available credits')
            ->descriptionIcon('heroicon-m-currency-dollar')
            ->color($balanceSummary['current_credits'] > 100 ? 'success' : ($balanceSummary['current_credits'] > 50 ? 'warning' : 'danger'));

        $monthlyUsage = Stat::make('Monthly Usage', number_format($balanceSummary['current_month_usage']))
            ->description('Credits used this month')
            ->descriptionIcon('heroicon-m-chart-bar')
            ->color($balanceSummary['is_approaching_limit'] ? 'warning' : 'primary');

        // Guard against missing subscriptions table/columns in some environments
        $activeSubscription = false;
        try {
            if (Schema::hasTable('subscriptions')) {
                if (Schema::hasColumn('subscriptions', 'company_id')) {
                    $activeSubscription = DB::table('subscriptions')
                        ->where('company_id', $company->id)
                        ->whereNull('ends_at')
                        ->exists();
                }
            }
        } catch (\Throwable $e) {
            $activeSubscription = false;
        }

        $subscriptionStatus = $activeSubscription ? 'Active Subscription' : 'No Active Subscription';
        $subscriptionColor = $activeSubscription ? 'success' : 'warning';

        $monthlyLimit = Stat::make('Subscription Status', $subscriptionStatus)
            ->description("Plan: " . ucfirst($balanceSummary['plan']))
            ->descriptionIcon($company->subscribed() ? 'heroicon-m-shield-check' : 'heroicon-m-exclamation-triangle')
            ->color($subscriptionColor);

        return [
            $currentCredits,
            $monthlyUsage,
            $monthlyLimit,
        ];
    }
}
