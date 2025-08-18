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
        Schema::create('demo_templates', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('engine_type', ['unreal', 'playcanvas']);
            $table->string('repository_url', 500);
            $table->string('preview_image', 500)->nullable();
            $table->json('tags')->nullable();
            $table->enum('difficulty_level', ['beginner', 'intermediate', 'advanced'])->default('beginner');
            $table->unsignedInteger('estimated_setup_time')->default(300); // seconds
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index('engine_type');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('demo_templates');
    }
};
