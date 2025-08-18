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
        Schema::create('workspaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->enum('engine_type', ['unreal', 'playcanvas']);
            $table->string('template_id')->nullable();
            $table->unsignedInteger('mcp_port')->nullable();
            $table->unsignedInteger('mcp_pid')->nullable();
            $table->text('preview_url')->nullable();
            $table->text('published_url')->nullable();
            $table->enum('status', ['initializing', 'ready', 'building', 'published', 'error'])->default('initializing');
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index(['company_id', 'engine_type']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workspaces');
    }
};
