<?php

namespace App\Console\Commands;

use App\Services\GDevelopPerformanceMonitorService;
use App\Services\GDevelopCacheService;
use App\Services\GDevelopProcessPoolService;
use App\Services\GDevelopAsyncProcessingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GDevelopPerformanceMonitorCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'gdevelop:performance 
                            {action : Action to perform (stats|clear-cache|clear-metrics|pool-status|queue-status)}
                            {--format=table : Output format (table|json)}
                            {--detailed : Show detailed information}';

    /**
     * The console command description.
     */
    protected $description = 'Monitor and manage GDevelop performance optimization features';

    public function __construct(
        private GDevelopPerformanceMonitorService $performanceMonitor,
        private GDevelopCacheService $cacheService,
        private GDevelopProcessPoolService $processPool,
        private GDevelopAsyncProcessingService $asyncProcessing
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $action = $this->argument('action');
        $format = $this->option('format');
        $detailed = $this->option('detailed');

        try {
            switch ($action) {
                case 'stats':
                    return $this->showPerformanceStats($format, $detailed);
                case 'clear-cache':
                    return $this->clearCache();
                case 'clear-metrics':
                    return $this->clearMetrics();
                case 'pool-status':
                    return $this->showPoolStatus($format);
                case 'queue-status':
                    return $this->showQueueStatus($format);
                default:
                    $this->error("Unknown action: {$action}");
                    return 1;
            }
        } catch (\Exception $e) {
            $this->error("Command failed: " . $e->getMessage());
            Log::error('GDevelop performance command failed', [
                'action' => $action,
                'error' => $e->getMessage()
            ]);
            return 1;
        }
    }

    /**
     * Show performance statistics
     */
    private function showPerformanceStats(string $format, bool $detailed): int
    {
        $this->info('Gathering GDevelop performance statistics...');

        $stats = $this->performanceMonitor->getPerformanceStatistics();
        $cacheStats = $this->cacheService->getCacheStatistics();

        if ($format === 'json') {
            $this->line(json_encode([
                'performance' => $stats,
                'cache' => $cacheStats
            ], JSON_PRETTY_PRINT));
            return 0;
        }

        // Display performance statistics in table format
        $this->info('=== GDevelop Performance Statistics ===');
        
        if (isset($stats['preview_generation'])) {
            $this->displayOperationStats('Preview Generation', $stats['preview_generation'], $detailed);
        }

        if (isset($stats['export_generation'])) {
            $this->displayOperationStats('Export Generation', $stats['export_generation'], $detailed);
        }

        if (isset($stats['cli_execution'])) {
            $this->displayOperationStats('CLI Execution', $stats['cli_execution'], $detailed);
        }

        if (isset($stats['game_generation'])) {
            $this->displayOperationStats('Game Generation', $stats['game_generation'], $detailed);
        }

        // Display cache statistics
        $this->info('=== Cache Statistics ===');
        $this->displayCacheStats($cacheStats);

        // Display queue statistics if available
        if (isset($stats['queued_operations'])) {
            $this->info('=== Queue Statistics ===');
            $this->displayQueueStats($stats['queued_operations']);
        }

        // Display system performance if available
        if (isset($stats['system_performance'])) {
            $this->info('=== System Performance ===');
            $this->displaySystemStats($stats['system_performance']);
        }

        return 0;
    }

    /**
     * Display operation statistics
     */
    private function displayOperationStats(string $operationName, array $stats, bool $detailed): void
    {
        $this->info("--- {$operationName} ---");
        
        $tableData = [
            ['Metric', 'Value'],
            ['Total Operations', $stats['count'] ?? 0],
            ['Average Time', number_format($stats['average_time'] ?? 0, 3) . 's'],
            ['Min Time', number_format($stats['min_time'] ?? 0, 3) . 's'],
            ['Max Time', number_format($stats['max_time'] ?? 0, 3) . 's'],
            ['Success Rate', number_format($stats['success_rate'] ?? 0, 1) . '%'],
        ];

        $this->table(['Metric', 'Value'], array_slice($tableData, 1));
        $this->line('');
    }

    /**
     * Display cache statistics
     */
    private function displayCacheStats(array $cacheStats): void
    {
        if (empty($cacheStats)) {
            $this->warn('No cache statistics available');
            return;
        }

        $tableData = [];
        foreach ($cacheStats as $cacheType => $value) {
            $tableData[] = [ucfirst(str_replace('_', ' ', $cacheType)), $value];
        }

        $this->table(['Cache Type', 'Count'], $tableData);
        $this->line('');
    }

    /**
     * Display queue statistics
     */
    private function displayQueueStats(array $queueStats): void
    {
        $tableData = [
            ['Total Queued', $queueStats['total_queued'] ?? 0],
            ['Total Completed', $queueStats['total_completed'] ?? 0],
            ['Avg Processing Time', number_format($queueStats['average_processing_time'] ?? 0, 3) . 's'],
            ['Throughput/min', number_format($queueStats['throughput_per_minute'] ?? 0, 2)],
        ];

        $this->table(['Metric', 'Value'], $tableData);
        $this->line('');
    }

    /**
     * Display system performance statistics
     */
    private function displaySystemStats(array $systemStats): void
    {
        $tableData = [
            ['Memory Usage', $this->formatBytes($systemStats['memory_usage'] ?? 0)],
            ['Peak Memory', $this->formatBytes($systemStats['peak_memory_usage'] ?? 0)],
            ['CPU Load', number_format($systemStats['cpu_load'] ?? 0, 2)],
        ];

        $this->table(['Metric', 'Value'], $tableData);
        $this->line('');
    }

    /**
     * Clear all caches
     */
    private function clearCache(): int
    {
        $this->info('Clearing GDevelop caches...');
        
        $this->cacheService->clearAllCaches();
        
        $this->info('✓ All GDevelop caches cleared successfully');
        return 0;
    }

    /**
     * Clear performance metrics
     */
    private function clearMetrics(): int
    {
        $this->info('Clearing GDevelop performance metrics...');
        
        $this->performanceMonitor->clearOldMetrics();
        
        $this->info('✓ Performance metrics cleared successfully');
        return 0;
    }

    /**
     * Show process pool status
     */
    private function showPoolStatus(string $format): int
    {
        $this->info('Getting process pool status...');
        
        $poolStats = $this->processPool->getPoolStatistics();

        if ($format === 'json') {
            $this->line(json_encode($poolStats, JSON_PRETTY_PRINT));
            return 0;
        }

        $this->info('=== Process Pool Status ===');
        
        $tableData = [
            ['Pool Size', $poolStats['pool_size'] ?? 0],
            ['Max Pool Size', $poolStats['max_pool_size'] ?? 0],
            ['Active Processes', $poolStats['active_processes'] ?? 0],
            ['Available Processes', $poolStats['available_processes'] ?? 0],
            ['Process Timeout', ($poolStats['process_timeout'] ?? 0) . 's'],
        ];

        $this->table(['Metric', 'Value'], $tableData);
        
        return 0;
    }

    /**
     * Show queue status
     */
    private function showQueueStatus(string $format): int
    {
        $this->info('Getting queue status...');
        
        $queueStats = $this->asyncProcessing->getQueueStatistics();

        if ($format === 'json') {
            $this->line(json_encode($queueStats, JSON_PRETTY_PRINT));
            return 0;
        }

        $this->info('=== Queue Status ===');
        
        $tableData = [
            ['Export Queue Size', $queueStats['export_queue_size'] ?? 0],
            ['Preview Queue Size', $queueStats['preview_queue_size'] ?? 0],
            ['Total Queue Size', $queueStats['total_queue_size'] ?? 0],
            ['Failed Jobs', $queueStats['failed_jobs'] ?? 0],
            ['Avg Processing Time', number_format($queueStats['average_processing_time'] ?? 0, 3) . 's'],
            ['Queue Throughput', number_format($queueStats['queue_throughput'] ?? 0, 2) . '/min'],
        ];

        $this->table(['Metric', 'Value'], $tableData);
        
        return 0;
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}