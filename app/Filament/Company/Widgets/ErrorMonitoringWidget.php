<?php

namespace App\Filament\Company\Widgets;

use App\Services\ErrorMonitoringService;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;

class ErrorMonitoringWidget extends ChartWidget
{
    protected ?string $heading = 'API Error Monitoring';
    protected static ?int $sort = 4;
    protected int | string | array $columnSpan = 'full';



    protected function getData(): array
    {
        $company = Filament::getTenant();
        $from = now()->subDays(7);
        $to = now();

        $errorMonitoring = app(ErrorMonitoringService::class);
        $stats = $errorMonitoring->getCompanyErrorStats($company, $from, $to);
        $patterns = $errorMonitoring->getErrorPatterns($company, 7);

        // Prepare daily error data for chart
        $dailyData = [];
        $labels = [];

        for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
            $dateStr = $date->format('Y-m-d');
            $labels[] = $date->format('M j');
            $dailyData[] = $patterns['daily_distribution'][$dateStr] ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'API Errors',
                    'data' => $dailyData,
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                    'borderColor' => 'rgb(239, 68, 68)',
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
                    'ticks' => [
                        'precision' => 0,
                    ],
                ],
            ],
        ];
    }

    public function getDescription(): ?string
    {
        $company = Filament::getTenant();
        $errorMonitoring = app(ErrorMonitoringService::class);
        $stats = $errorMonitoring->getCompanyErrorStats($company, now()->subDays(7), now());

        $description = "Total errors in last 7 days: {$stats['total_errors']}";

        if ($stats['most_common_error']) {
            $description .= " | Most common: {$stats['most_common_error']}";
        }

        if ($stats['error_trend']['trend'] !== 'stable') {
            $trend = $stats['error_trend']['trend'];
            $percentage = abs($stats['error_trend']['change_percentage']);
            $description .= " | Trend: {$trend} ({$percentage}%)";
        }

        return $description;
    }
}
