<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ErrorMonitoringService
{
    private const ERROR_CACHE_PREFIX = 'error_monitoring:';
    private const ERROR_RATE_WINDOW = 300; // 5 minutes
    private const ERROR_THRESHOLD = 10; // errors per window

    /**
     * Track an API error occurrence.
     */
    public function trackError(
        string $errorType,
        string $message,
        ?User $user = null,
        ?Company $company = null,
        array $context = []
    ): void {
        $errorData = [
            'error_type' => $errorType,
            'message' => $message,
            'user_id' => $user?->id,
            'company_id' => $company?->id,
            'context' => $context,
            'timestamp' => now()->toISOString(),
            'request_id' => request()->header('X-Request-ID') ?? uniqid(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'url' => request()->fullUrl(),
            'method' => request()->method(),
        ];

        // Log the error
        Log::error("API Error Tracked: {$errorType}", $errorData);

        // Store in database for analysis
        $this->storeErrorInDatabase($errorData);

        // Update error rate tracking
        $this->updateErrorRateTracking($errorType, $user, $company);

        // Check for error patterns and alerts
        $this->checkErrorPatterns($errorType, $user, $company);
    }

    /**
     * Get error statistics for a company.
     */
    public function getCompanyErrorStats(Company $company, Carbon $from, Carbon $to): array
    {
        $errors = DB::table('api_error_logs')
            ->where('company_id', $company->id)
            ->whereBetween('created_at', [$from, $to])
            ->select('error_type', DB::raw('COUNT(*) as count'), DB::raw('MAX(created_at) as last_occurrence'))
            ->groupBy('error_type')
            ->orderBy('count', 'desc')
            ->get();

        $totalErrors = $errors->sum('count');
        $errorsByType = $errors->pluck('count', 'error_type')->toArray();
        $recentErrors = $this->getRecentErrors($company, 24); // Last 24 hours

        return [
            'total_errors' => $totalErrors,
            'errors_by_type' => $errorsByType,
            'recent_errors' => $recentErrors,
            'error_rate' => $this->calculateErrorRate($company, $from, $to),
            'most_common_error' => $errors->first()?->error_type,
            'error_trend' => $this->getErrorTrend($company, $from, $to),
        ];
    }

    /**
     * Get system-wide error statistics.
     */
    public function getSystemErrorStats(Carbon $from, Carbon $to): array
    {
        $errors = DB::table('api_error_logs')
            ->whereBetween('created_at', [$from, $to])
            ->select(
                'error_type',
                DB::raw('COUNT(*) as count'),
                DB::raw('COUNT(DISTINCT company_id) as affected_companies'),
                DB::raw('MAX(created_at) as last_occurrence')
            )
            ->groupBy('error_type')
            ->orderBy('count', 'desc')
            ->get();

        $totalErrors = $errors->sum('count');
        $affectedCompanies = DB::table('api_error_logs')
            ->whereBetween('created_at', [$from, $to])
            ->distinct('company_id')
            ->count();

        return [
            'total_errors' => $totalErrors,
            'affected_companies' => $affectedCompanies,
            'errors_by_type' => $errors->toArray(),
            'error_rate_per_hour' => $this->calculateSystemErrorRate($from, $to),
            'critical_errors' => $this->getCriticalErrors($from, $to),
        ];
    }

    /**
     * Get recent errors for a company.
     */
    public function getRecentErrors(Company $company, int $hours = 24): array
    {
        return DB::table('api_error_logs')
            ->where('company_id', $company->id)
            ->where('created_at', '>=', now()->subHours($hours))
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->toArray();
    }

    /**
     * Check if a company is experiencing high error rates.
     */
    public function isHighErrorRate(Company $company): bool
    {
        $cacheKey = self::ERROR_CACHE_PREFIX . "rate:{$company->id}";
        $errorCount = Cache::get($cacheKey, 0);
        
        return $errorCount >= self::ERROR_THRESHOLD;
    }

    /**
     * Get error patterns and anomalies.
     */
    public function getErrorPatterns(Company $company, int $days = 7): array
    {
        $from = now()->subDays($days);
        $to = now();

        // Get hourly error distribution (SQLite compatible)
        $hourlyErrors = DB::table('api_error_logs')
            ->where('company_id', $company->id)
            ->whereBetween('created_at', [$from, $to])
            ->select(
                DB::raw("strftime('%H', created_at) as hour"),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->pluck('count', 'hour')
            ->toArray();

        // Get daily error distribution (SQLite compatible)
        $dailyErrors = DB::table('api_error_logs')
            ->where('company_id', $company->id)
            ->whereBetween('created_at', [$from, $to])
            ->select(
                DB::raw("strftime('%Y-%m-%d', created_at) as date"),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('count', 'date')
            ->toArray();

        // Detect anomalies
        $anomalies = $this->detectAnomalies($company, $days);

        return [
            'hourly_distribution' => $hourlyErrors,
            'daily_distribution' => $dailyErrors,
            'anomalies' => $anomalies,
            'peak_error_hour' => !empty($hourlyErrors) ? array_keys($hourlyErrors, max($hourlyErrors))[0] : null,
            'average_daily_errors' => count($dailyErrors) > 0 ? array_sum($dailyErrors) / count($dailyErrors) : 0,
        ];
    }

    /**
     * Store error in database for analysis.
     */
    private function storeErrorInDatabase(array $errorData): void
    {
        try {
            DB::table('api_error_logs')->insert([
                'error_type' => $errorData['error_type'],
                'message' => $errorData['message'],
                'user_id' => $errorData['user_id'],
                'company_id' => $errorData['company_id'],
                'context' => json_encode($errorData['context']),
                'request_id' => $errorData['request_id'],
                'ip_address' => $errorData['ip_address'],
                'user_agent' => $errorData['user_agent'],
                'url' => $errorData['url'],
                'method' => $errorData['method'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Don't let error logging break the application
            Log::error('Failed to store error in database', [
                'error' => $e->getMessage(),
                'original_error' => $errorData,
            ]);
        }
    }

    /**
     * Update error rate tracking in cache.
     */
    private function updateErrorRateTracking(string $errorType, ?User $user, ?Company $company): void
    {
        if ($company) {
            $cacheKey = self::ERROR_CACHE_PREFIX . "rate:{$company->id}";
            $currentCount = Cache::get($cacheKey, 0);
            Cache::put($cacheKey, $currentCount + 1, self::ERROR_RATE_WINDOW);
        }

        if ($user) {
            $cacheKey = self::ERROR_CACHE_PREFIX . "user_rate:{$user->id}";
            $currentCount = Cache::get($cacheKey, 0);
            Cache::put($cacheKey, $currentCount + 1, self::ERROR_RATE_WINDOW);
        }

        // Track system-wide error rate
        $systemCacheKey = self::ERROR_CACHE_PREFIX . "system_rate";
        $systemCount = Cache::get($systemCacheKey, 0);
        Cache::put($systemCacheKey, $systemCount + 1, self::ERROR_RATE_WINDOW);
    }

    /**
     * Check for error patterns and send alerts if needed.
     */
    private function checkErrorPatterns(string $errorType, ?User $user, ?Company $company): void
    {
        if ($company && $this->isHighErrorRate($company)) {
            $this->sendHighErrorRateAlert($company);
        }

        // Check for critical error types
        $criticalErrors = ['general_error', 'credit_transaction_error', 'provider_api_error'];
        if (in_array($errorType, $criticalErrors)) {
            $this->sendCriticalErrorAlert($errorType, $user, $company);
        }
    }

    /**
     * Calculate error rate for a company.
     */
    private function calculateErrorRate(Company $company, Carbon $from, Carbon $to): float
    {
        $hours = $from->diffInHours($to);
        if ($hours === 0) {
            return 0;
        }

        $errorCount = DB::table('api_error_logs')
            ->where('company_id', $company->id)
            ->whereBetween('created_at', [$from, $to])
            ->count();

        return round($errorCount / $hours, 2);
    }

    /**
     * Calculate system-wide error rate.
     */
    private function calculateSystemErrorRate(Carbon $from, Carbon $to): float
    {
        $hours = $from->diffInHours($to);
        if ($hours === 0) {
            return 0;
        }

        $errorCount = DB::table('api_error_logs')
            ->whereBetween('created_at', [$from, $to])
            ->count();

        return round($errorCount / $hours, 2);
    }

    /**
     * Get error trend data.
     */
    private function getErrorTrend(Company $company, Carbon $from, Carbon $to): array
    {
        $midpoint = $from->copy()->addSeconds($from->diffInSeconds($to) / 2);
        
        $firstHalfErrors = DB::table('api_error_logs')
            ->where('company_id', $company->id)
            ->whereBetween('created_at', [$from, $midpoint])
            ->count();

        $secondHalfErrors = DB::table('api_error_logs')
            ->where('company_id', $company->id)
            ->whereBetween('created_at', [$midpoint, $to])
            ->count();

        $trend = 'stable';
        if ($secondHalfErrors > $firstHalfErrors * 1.2) {
            $trend = 'increasing';
        } elseif ($secondHalfErrors < $firstHalfErrors * 0.8) {
            $trend = 'decreasing';
        }

        return [
            'trend' => $trend,
            'first_half_errors' => $firstHalfErrors,
            'second_half_errors' => $secondHalfErrors,
            'change_percentage' => $firstHalfErrors > 0 
                ? round((($secondHalfErrors - $firstHalfErrors) / $firstHalfErrors) * 100, 1)
                : 0,
        ];
    }

    /**
     * Get critical errors.
     */
    private function getCriticalErrors(Carbon $from, Carbon $to): array
    {
        $criticalErrorTypes = ['general_error', 'credit_transaction_error', 'provider_api_error'];

        return DB::table('api_error_logs')
            ->whereIn('error_type', $criticalErrorTypes)
            ->whereBetween('created_at', [$from, $to])
            ->select('error_type', DB::raw('COUNT(*) as count'))
            ->groupBy('error_type')
            ->orderBy('count', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Detect anomalies in error patterns.
     */
    private function detectAnomalies(Company $company, int $days): array
    {
        // Simple anomaly detection based on standard deviation (SQLite compatible)
        $dailyErrorCounts = DB::table('api_error_logs')
            ->where('company_id', $company->id)
            ->where('created_at', '>=', now()->subDays($days))
            ->select(
                DB::raw("strftime('%Y-%m-%d', created_at) as date"),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('count')
            ->toArray();

        if (count($dailyErrorCounts) < 3) {
            return [];
        }

        $mean = array_sum($dailyErrorCounts) / count($dailyErrorCounts);
        $variance = array_sum(array_map(fn($x) => pow($x - $mean, 2), $dailyErrorCounts)) / count($dailyErrorCounts);
        $stdDev = sqrt($variance);

        $anomalies = [];
        foreach ($dailyErrorCounts as $index => $count) {
            if (abs($count - $mean) > 2 * $stdDev) {
                $anomalies[] = [
                    'date' => now()->subDays($days - $index)->format('Y-m-d'),
                    'error_count' => $count,
                    'deviation' => round(abs($count - $mean) / $stdDev, 2),
                    'type' => $count > $mean ? 'spike' : 'drop',
                ];
            }
        }

        return $anomalies;
    }

    /**
     * Send high error rate alert.
     */
    private function sendHighErrorRateAlert(Company $company): void
    {
        $cacheKey = self::ERROR_CACHE_PREFIX . "alert_sent:{$company->id}";
        
        // Don't spam alerts - only send once per hour
        if (Cache::has($cacheKey)) {
            return;
        }

        Log::warning("High error rate detected for company {$company->name}", [
            'company_id' => $company->id,
            'company_name' => $company->name,
        ]);

        Cache::put($cacheKey, true, 3600); // 1 hour
    }

    /**
     * Send critical error alert.
     */
    private function sendCriticalErrorAlert(string $errorType, ?User $user, ?Company $company): void
    {
        Log::critical("Critical error occurred: {$errorType}", [
            'error_type' => $errorType,
            'user_id' => $user?->id,
            'company_id' => $company?->id,
            'company_name' => $company?->name,
        ]);
    }
}