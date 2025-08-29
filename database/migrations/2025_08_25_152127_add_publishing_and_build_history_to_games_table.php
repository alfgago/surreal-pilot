<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            // Publishing fields
            $table->string('status')->default('draft')->after('metadata'); // draft, published, archived
            $table->string('version')->default('1.0.0')->after('status');
            $table->json('tags')->nullable()->after('version');
            $table->integer('play_count')->default(0)->after('tags');
            $table->timestamp('last_played_at')->nullable()->after('play_count');
            $table->timestamp('published_at')->nullable()->after('last_played_at');
            
            // Sharing and access control
            $table->boolean('is_public')->default(false)->after('published_at');
            $table->string('share_token')->nullable()->unique()->after('is_public');
            $table->json('sharing_settings')->nullable()->after('share_token'); // embed options, permissions, etc.
            
            // Build and deployment
            $table->string('build_status')->default('none')->after('sharing_settings'); // none, building, success, failed
            $table->text('build_log')->nullable()->after('build_status');
            $table->timestamp('last_build_at')->nullable()->after('build_log');
            $table->json('deployment_config')->nullable()->after('last_build_at'); // deployment settings
            
            // Add indexes for performance
            $table->index(['status', 'updated_at']);
            $table->index(['is_public', 'published_at']);
            $table->index(['share_token']);
            $table->index(['build_status', 'last_build_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            // Drop indexes first, but only if they exist
            try {
                $table->dropIndex(['status', 'updated_at']);
            } catch (\Exception $e) {
                // Index doesn't exist, ignore
            }
            
            try {
                $table->dropIndex(['is_public', 'published_at']);
            } catch (\Exception $e) {
                // Index doesn't exist, ignore
            }
            
            try {
                $table->dropIndex(['share_token']);
            } catch (\Exception $e) {
                // Index doesn't exist, ignore
            }
            
            try {
                $table->dropIndex(['build_status', 'last_build_at']);
            } catch (\Exception $e) {
                // Index doesn't exist, ignore
            }
        });
        
        // Drop columns only if they exist
        Schema::table('games', function (Blueprint $table) {
            $columnsToCheck = [
                'status',
                'version',
                'tags',
                'play_count',
                'last_played_at',
                'published_at',
                'is_public',
                'share_token',
                'sharing_settings',
                'build_status',
                'build_log',
                'last_build_at',
                'deployment_config',
            ];
            
            $existingColumns = [];
            foreach ($columnsToCheck as $column) {
                if (Schema::hasColumn('games', $column)) {
                    $existingColumns[] = $column;
                }
            }
            
            if (!empty($existingColumns)) {
                $table->dropColumn($existingColumns);
            }
        });
    }
};