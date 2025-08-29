<?php

namespace App\Console\Commands;

use App\Services\RealtimeChatService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupRealtimeChat extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'chat:cleanup-realtime
                            {--force : Force cleanup without confirmation}';

    /**
     * The console command description.
     */
    protected $description = 'Clean up expired real-time chat data (typing indicators, connections)';

    /**
     * Execute the console command.
     */
    public function handle(RealtimeChatService $realtimeChatService): int
    {
        $this->info('Starting real-time chat cleanup...');

        try {
            // Clean up expired typing indicators
            $this->info('Cleaning up expired typing indicators...');
            $realtimeChatService->cleanupExpiredTyping();

            // Clean up expired connections
            $this->info('Cleaning up expired connections...');
            $realtimeChatService->cleanupExpiredConnections();

            $this->info('Real-time chat cleanup completed successfully.');
            
            Log::info('Real-time chat cleanup completed', [
                'command' => $this->signature,
                'timestamp' => now()->toISOString(),
            ]);

            return Command::SUCCESS;

        } catch (\Throwable $e) {
            $this->error('Failed to cleanup real-time chat data: ' . $e->getMessage());
            
            Log::error('Real-time chat cleanup failed', [
                'command' => $this->signature,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }
}