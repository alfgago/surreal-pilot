<?php

namespace App\Console\Commands;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Game;
use App\Models\Workspace;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MigrateExistingWorkspacesToMultiChat extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'migrate:workspaces-to-multichat 
                            {--dry-run : Run migration in dry-run mode without making changes}
                            {--force : Force migration even if conversations already exist}';

    /**
     * The console command description.
     */
    protected $description = 'Migrate existing workspaces to support multi-chat functionality by creating default conversations';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        $this->info('Starting workspace migration to multi-chat functionality...');
        
        if ($dryRun) {
            $this->warn('Running in DRY-RUN mode - no changes will be made');
        }

        try {
            DB::beginTransaction();

            // Step 1: Get all existing workspaces
            $workspaces = Workspace::all();
            $this->info("Found {$workspaces->count()} workspaces to process");

            if ($workspaces->isEmpty()) {
                $this->info('No workspaces found. Migration complete.');
                return 0;
            }

            // Step 2: Check if conversations already exist globally
            $existingConversations = ChatConversation::count();
            if ($existingConversations > 0 && !$force) {
                $this->warn("Found {$existingConversations} existing conversations.");
                $this->info('Migration will skip workspaces that already have conversations.');
                $this->info('Use --force flag to create default conversations for all workspaces.');
            }

            $migratedCount = 0;
            $skippedCount = 0;

            foreach ($workspaces as $workspace) {
                $this->info("Processing workspace: {$workspace->name} (ID: {$workspace->id})");

                // Check if this workspace already has conversations
                $existingWorkspaceConversations = $workspace->conversations()->count();
                
                if ($existingWorkspaceConversations > 0 && !$force) {
                    $this->warn("  Skipping - workspace already has {$existingWorkspaceConversations} conversations");
                    $skippedCount++;
                    continue;
                }

                if (!$dryRun) {
                    // Create default conversation for this workspace
                    $conversation = $this->createDefaultConversation($workspace);
                    
                    // Migrate any existing chat history (from patches or other sources)
                    $this->migrateChatHistory($workspace, $conversation);
                    
                    // Update any existing games to reference the default conversation
                    $this->updateExistingGames($workspace, $conversation);
                    
                    $this->info("  ✓ Created default conversation (ID: {$conversation->id})");
                } else {
                    $this->info("  [DRY-RUN] Would create default conversation");
                }

                $migratedCount++;
            }

            if (!$dryRun) {
                // Step 3: Validate data integrity
                $this->validateDataIntegrity();
                
                DB::commit();
                $this->info("✓ Migration completed successfully!");
            } else {
                DB::rollBack();
                $this->info("✓ Dry-run completed successfully!");
            }

            $this->info("Summary:");
            $this->info("  - Workspaces processed: {$migratedCount}");
            $this->info("  - Workspaces skipped: {$skippedCount}");

            return 0;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Migration failed: " . $e->getMessage());
            Log::error('Workspace migration failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    /**
     * Create a default conversation for a workspace
     */
    private function createDefaultConversation(Workspace $workspace): ChatConversation
    {
        return ChatConversation::create([
            'workspace_id' => $workspace->id,
            'title' => 'Default Chat',
            'description' => 'Default conversation created during migration to multi-chat functionality',
            'created_at' => $workspace->created_at,
            'updated_at' => $workspace->updated_at,
        ]);
    }

    /**
     * Migrate existing chat history from patches or other sources
     */
    private function migrateChatHistory(Workspace $workspace, ChatConversation $conversation): void
    {
        // Check for existing patches that might contain chat history
        $patches = $workspace->patches()->orderBy('created_at')->get();
        
        if ($patches->isEmpty()) {
            $this->info("    No existing chat history found in patches");
            return;
        }

        $this->info("    Migrating {$patches->count()} patches as chat history");

        foreach ($patches as $patch) {
            try {
                // Extract envelope data which might contain chat messages
                $envelope = json_decode($patch->envelope_json, true);
                
                if (!$envelope || !isset($envelope['messages'])) {
                    continue;
                }

                // Create chat messages from patch envelope
                foreach ($envelope['messages'] as $message) {
                    if (!isset($message['role']) || !isset($message['content'])) {
                        continue;
                    }

                    ChatMessage::create([
                        'conversation_id' => $conversation->id,
                        'role' => $message['role'],
                        'content' => $message['content'],
                        'metadata' => [
                            'migrated_from_patch' => true,
                            'patch_id' => $patch->patch_id,
                            'tokens_used' => $patch->tokens_used,
                        ],
                        'created_at' => $patch->created_at,
                        'updated_at' => $patch->updated_at,
                    ]);
                }

            } catch (\Exception $e) {
                $this->warn("    Failed to migrate patch {$patch->patch_id}: " . $e->getMessage());
                continue;
            }
        }

        // Update conversation timestamp to match the latest patch
        $latestPatch = $patches->last();
        if ($latestPatch) {
            $conversation->update(['updated_at' => $latestPatch->updated_at]);
        }
    }

    /**
     * Update existing games to reference the default conversation
     */
    private function updateExistingGames(Workspace $workspace, ChatConversation $conversation): void
    {
        $games = $workspace->games()->whereNull('conversation_id')->get();
        
        if ($games->isEmpty()) {
            $this->info("    No existing games to update");
            return;
        }

        $this->info("    Updating {$games->count()} existing games");

        foreach ($games as $game) {
            $game->update([
                'conversation_id' => $conversation->id,
            ]);
        }
    }

    /**
     * Validate data integrity after migration
     */
    private function validateDataIntegrity(): void
    {
        $this->info("Validating data integrity...");

        // Check that all workspaces have at least one conversation
        $workspacesWithoutConversations = Workspace::doesntHave('conversations')->count();
        if ($workspacesWithoutConversations > 0) {
            throw new \Exception("Found {$workspacesWithoutConversations} workspaces without conversations");
        }

        // Check that all conversations belong to valid workspaces
        $orphanedConversations = ChatConversation::whereDoesntHave('workspace')->count();
        if ($orphanedConversations > 0) {
            throw new \Exception("Found {$orphanedConversations} orphaned conversations");
        }

        // Check that all chat messages belong to valid conversations
        $orphanedMessages = ChatMessage::whereDoesntHave('conversation')->count();
        if ($orphanedMessages > 0) {
            throw new \Exception("Found {$orphanedMessages} orphaned chat messages");
        }

        // Check that all games with conversation_id reference valid conversations
        $gamesWithInvalidConversations = Game::whereNotNull('conversation_id')
            ->whereDoesntHave('conversation')
            ->count();
        if ($gamesWithInvalidConversations > 0) {
            throw new \Exception("Found {$gamesWithInvalidConversations} games with invalid conversation references");
        }

        $this->info("  ✓ All data integrity checks passed");
    }
}