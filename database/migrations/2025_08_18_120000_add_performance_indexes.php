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
        Schema::table('chat_conversations', function (Blueprint $table) {
            // Add composite index for workspace queries with ordering
            $table->index(['workspace_id', 'updated_at', 'id'], 'idx_conversations_workspace_updated');
            
            // Add index for title searches
            $table->index('title', 'idx_conversations_title');
            
            // Add index for created_at for chronological queries
            $table->index('created_at', 'idx_conversations_created');
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            // Add composite index for conversation queries with ordering
            $table->index(['conversation_id', 'created_at', 'id'], 'idx_messages_conversation_created');
            
            // Add index for role-based queries
            $table->index('role', 'idx_messages_role');
        });

        // Add content prefix index using raw SQL for MySQL compatibility only
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE chat_messages ADD INDEX idx_messages_content_prefix (content(100))');
        }

        Schema::table('games', function (Blueprint $table) {
            // Add composite index for workspace queries with ordering
            $table->index(['workspace_id', 'updated_at', 'id'], 'idx_games_workspace_updated');
            
            // Add index for conversation-based queries
            $table->index('conversation_id', 'idx_games_conversation');
            
            // Add index for title searches
            $table->index('title', 'idx_games_title');
            
            // Add index for published games
            $table->index('published_url', 'idx_games_published');
            
            // Add index for games with previews
            $table->index('preview_url', 'idx_games_preview');
            
            // Add index for created_at for chronological queries
            $table->index('created_at', 'idx_games_created');
        });

        // Add cross-table indexes for company-wide queries
        Schema::table('workspaces', function (Blueprint $table) {
            // Add composite index for company queries with engine type
            $table->index(['company_id', 'engine_type', 'updated_at'], 'idx_workspaces_company_engine');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->dropIndex('idx_conversations_workspace_updated');
            $table->dropIndex('idx_conversations_title');
            $table->dropIndex('idx_conversations_created');
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropIndex('idx_messages_conversation_created');
            $table->dropIndex('idx_messages_role');
        });

        // Drop content prefix index using raw SQL for MySQL only
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE chat_messages DROP INDEX idx_messages_content_prefix');
        }

        Schema::table('games', function (Blueprint $table) {
            $table->dropIndex('idx_games_workspace_updated');
            $table->dropIndex('idx_games_conversation');
            $table->dropIndex('idx_games_title');
            $table->dropIndex('idx_games_published');
            $table->dropIndex('idx_games_preview');
            $table->dropIndex('idx_games_created');
        });

        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropIndex('idx_workspaces_company_engine');
        });
    }
};