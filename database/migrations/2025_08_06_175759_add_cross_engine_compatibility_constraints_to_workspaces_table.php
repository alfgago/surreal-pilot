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
        Schema::table('workspaces', function (Blueprint $table) {
            // Add unique constraint to prevent MCP port conflicts
            $table->unique(['mcp_port'], 'unique_mcp_port');
            
            // Add index for engine type isolation queries
            $table->index(['engine_type', 'status'], 'idx_engine_status');
        });
        
        Schema::table('demo_templates', function (Blueprint $table) {
            // Add index for engine type filtering
            $table->index(['engine_type', 'is_active'], 'idx_engine_active');
        });
        
        // Add constraints to multiplayer_sessions table
        Schema::table('multiplayer_sessions', function (Blueprint $table) {
            // Add foreign key constraint with cascade delete to ensure session cleanup
            $table->foreign('workspace_id')
                  ->references('id')
                  ->on('workspaces')
                  ->onDelete('cascade')
                  ->name('fk_multiplayer_workspace');
        });
        
        // Note: CHECK constraints are handled at the application level in model validation
        // since SQLite has limited support for adding CHECK constraints via ALTER TABLE
        // The model validation in Workspace and DemoTemplate models provides the same protection
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            // Drop unique constraint/indexes only if supported by driver
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->dropUnique('unique_mcp_port');
                $table->dropIndex('idx_engine_status');
            }
        });
        
        Schema::table('demo_templates', function (Blueprint $table) {
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->dropIndex('idx_engine_active');
            }
        });
        
        Schema::table('multiplayer_sessions', function (Blueprint $table) {
            // SQLite does not support dropping foreign keys by name; skip on SQLite
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->dropForeign('fk_multiplayer_workspace');
            }
        });
    }
};