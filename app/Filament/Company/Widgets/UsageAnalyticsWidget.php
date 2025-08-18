<?php

namespace App\Filament\Company\Widgets;

use App\Services\CreditManager;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;

class UsageAnalyticsWidget extends ChartWidget
{
    protected ?string $heading = 'Credit Usage Analytics';
    protected ?string $pollingInterval = '60s';
    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $company = Filament::getTenant();
        
        if (!$company) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        $creditManager = app(CreditManager::class);
        $from = now()->subDays(30);
        $to = now();
        
        $analytics = $creditManager->getUsageAnalytics($company, $from, $to);
        
        // Prepare data for the last 30 days
        $labels = [];
        $usageData = [];
        $creditsData = [];
        
        for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
            $dateKey = $date->format('Y-m-d');
            $labels[] = $date->format('M j');
            
            $dailyUsage = $analytics['daily_usage'][$dateKey] ?? ['debits' => 0, 'credits' => 0];
            $usageData[] = $dailyUsage['debits'];
            $creditsData[] = $dailyUsage['credits'];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Credits Used',
                    'data' => $usageData,
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                    'borderColor' => 'rgb(239, 68, 68)',
                    'borderWidth' => 2,
                    'fill' => true,
                ],
                [
                    'label' => 'Credits Added',
                    'data' => $creditsData,
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                    'borderColor' => 'rgb(34, 197, 94)',
                    'borderWidth' => 2,
                    'fill' => true,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Credits',
                    ],
                ],
                'x' => [
                    'title' => [
                        'display' => true,
                        'text' => 'Date',
                    ],
                ],
            ],
        ];
    }
}