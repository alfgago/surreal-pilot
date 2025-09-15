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
        Schema::table('gdevelop_game_sessions', function (Blueprint $table) {
            $table->json('conversation_history')->nullable()->after('error_log');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gdevelop_game_sessions', function (Blueprint $table) {
            $table->dropColumn('conversation_history');
        });
    }
};
