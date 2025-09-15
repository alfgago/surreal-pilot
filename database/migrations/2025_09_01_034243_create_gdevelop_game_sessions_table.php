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
        Schema::create('gdevelop_game_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->uuid('session_id')->unique();
            $table->string('game_title')->nullable();
            $table->longText('game_json')->nullable();
            $table->json('assets_manifest')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('last_modified')->useCurrent();
            $table->text('preview_url')->nullable();
            $table->text('export_url')->nullable();
            $table->enum('status', ['active', 'archived', 'error', 'exported'])->default('active');
            $table->text('error_log')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['workspace_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index('session_id');
            $table->index('last_modified');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gdevelop_game_sessions');
    }
};
