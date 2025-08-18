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
        Schema::create('multiplayer_sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('workspace_id')->constrained()->onDelete('cascade');
            $table->string('fargate_task_arn', 500)->nullable();
            $table->string('ngrok_url', 500)->nullable();
            $table->string('session_url', 500)->nullable();
            $table->enum('status', ['starting', 'active', 'stopping', 'stopped'])->default('starting');
            $table->unsignedInteger('max_players')->default(8);
            $table->unsignedInteger('current_players')->default(0);
            $table->timestamp('expires_at');
            $table->timestamps();
            
            $table->index('expires_at');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('multiplayer_sessions');
    }
};
