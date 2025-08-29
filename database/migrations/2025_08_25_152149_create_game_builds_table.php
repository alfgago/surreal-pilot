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
        Schema::create('game_builds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->onDelete('cascade');
            $table->string('version');
            $table->string('status'); // building, success, failed
            $table->text('build_log')->nullable();
            $table->string('build_url')->nullable(); // URL to the built game
            $table->string('commit_hash')->nullable(); // Git commit or change identifier
            $table->json('build_config')->nullable(); // Build configuration used
            $table->json('assets_manifest')->nullable(); // List of assets included
            $table->integer('file_count')->default(0);
            $table->bigInteger('total_size')->default(0); // Size in bytes
            $table->integer('build_duration')->nullable(); // Build time in seconds
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['game_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_builds');
    }
};