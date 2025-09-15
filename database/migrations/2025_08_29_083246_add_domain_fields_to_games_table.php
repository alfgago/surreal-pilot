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
            $table->string('custom_domain')->nullable()->after('published_url');
            $table->enum('domain_status', ['pending', 'active', 'failed'])->nullable()->after('custom_domain');
            $table->json('domain_config')->nullable()->after('domain_status');
            $table->json('thinking_history')->nullable()->after('domain_config');
            $table->json('game_mechanics')->nullable()->after('thinking_history');
            $table->integer('interaction_count')->default(0)->after('game_mechanics');
            
            // Add index for custom domain lookups
            $table->index('custom_domain');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropIndex(['custom_domain']);
            $table->dropColumn([
                'custom_domain',
                'domain_status',
                'domain_config',
                'thinking_history',
                'game_mechanics',
                'interaction_count'
            ]);
        });
    }
};
