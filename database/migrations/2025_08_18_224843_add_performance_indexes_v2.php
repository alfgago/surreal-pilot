<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check and add indexes only if they don't exist
        $this->addIndexIfNotExists('chat_conversations', 'idx_conversations_workspace_updated', ['workspace_id', 'updated_at', 'id']);
        $this->addIndexIfNotExists('chat_conversations', 'idx_conversations_title', ['title']);
        $this->addIndexIfNotExists('chat_conversations', 'idx_conversations_created', ['created_at']);
        
        $this->addIndexIfNotExists('chat_messages', 'idx_messages_conversation_created', ['conversation_id', 'created_at', 'id']);
        $this->addIndexIfNotExists('chat_messages', 'idx_messages_role', ['role']);
        
        // Skip content prefix index for SQLite as it doesn't support partial indexes in the same way
        if (DB::getDriverName() === 'mysql' && !$this->indexExists('chat_messages', 'idx_messages_content_prefix')) {
            DB::statement('ALTER TABLE chat_messages ADD INDEX idx_messages_content_prefix (content(100))');
        }
        
        $this->addIndexIfNotExists('games', 'idx_games_workspace_updated', ['workspace_id', 'updated_at', 'id']);
        $this->addIndexIfNotExists('games', 'idx_games_conversation', ['conversation_id']);
        $this->addIndexIfNotExists('games', 'idx_games_title', ['title']);
        $this->addIndexIfNotExists('games', 'idx_games_published', ['published_url']);
        $this->addIndexIfNotExists('games', 'idx_games_preview', ['preview_url']);
        $this->addIndexIfNotExists('games', 'idx_games_created', ['created_at']);
        
        $this->addIndexIfNotExists('workspaces', 'idx_workspaces_company_engine', ['company_id', 'engine_type', 'updated_at']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropIndexIfExists('chat_conversations', 'idx_conversations_workspace_updated');
        $this->dropIndexIfExists('chat_conversations', 'idx_conversations_title');
        $this->dropIndexIfExists('chat_conversations', 'idx_conversations_created');
        
        $this->dropIndexIfExists('chat_messages', 'idx_messages_conversation_created');
        $this->dropIndexIfExists('chat_messages', 'idx_messages_role');
        
        // Drop content prefix index using raw SQL for MySQL only
        if (DB::getDriverName() === 'mysql' && $this->indexExists('chat_messages', 'idx_messages_content_prefix')) {
            DB::statement('ALTER TABLE chat_messages DROP INDEX idx_messages_content_prefix');
        }
        
        $this->dropIndexIfExists('games', 'idx_games_workspace_updated');
        $this->dropIndexIfExists('games', 'idx_games_conversation');
        $this->dropIndexIfExists('games', 'idx_games_title');
        $this->dropIndexIfExists('games', 'idx_games_published');
        $this->dropIndexIfExists('games', 'idx_games_preview');
        $this->dropIndexIfExists('games', 'idx_games_created');
        
        $this->dropIndexIfExists('workspaces', 'idx_workspaces_company_engine');
    }

    /**
     * Check if an index exists on a table.
     */
    private function indexExists(string $table, string $indexName): bool
    {
        if (DB::getDriverName() === 'mysql') {
            $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
            return count($indexes) > 0;
        } else {
            // For SQLite, check pragma index_list
            $indexes = DB::select("PRAGMA index_list({$table})");
            foreach ($indexes as $index) {
                if ($index->name === $indexName) {
                    return true;
                }
            }
            return false;
        }
    }

    /**
     * Add an index if it doesn't exist.
     */
    private function addIndexIfNotExists(string $table, string $indexName, array $columns): void
    {
        if (!$this->indexExists($table, $indexName)) {
            Schema::table($table, function (Blueprint $table) use ($indexName, $columns) {
                $table->index($columns, $indexName);
            });
        }
    }

    /**
     * Drop an index if it exists.
     */
    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if ($this->indexExists($table, $indexName)) {
            Schema::table($table, function (Blueprint $table) use ($indexName) {
                $table->dropIndex($indexName);
            });
        }
    }
};