<?php

namespace App\Console\Commands;

use App\Services\OnDemandMcpManager;
use Illuminate\Console\Command;

class CleanupIdleMcpServers extends Command
{
    protected $signature = 'mcp:cleanup-idle';
    protected $description = 'Cleanup idle MCP servers to free resources';

    public function handle(OnDemandMcpManager $mcpManager): int
    {
        $this->info('Starting cleanup of idle MCP servers...');
        
        $cleaned = $mcpManager->cleanupIdleServers();
        
        if ($cleaned > 0) {
            $this->info("Cleaned up {$cleaned} idle MCP servers.");
        } else {
            $this->info('No idle servers found to cleanup.');
        }
        
        // Show current stats
        $stats = $mcpManager->getServerStats();
        $this->table(
            ['Metric', 'Value'],
            [
                ['Active Servers', $stats['active_servers']],
                ['Max Servers', $stats['max_servers']],
                ['Utilization', $stats['utilization'] . '%'],
                ['Available Slots', $stats['available_slots']],
            ]
        );

        return Command::SUCCESS;
    }
}