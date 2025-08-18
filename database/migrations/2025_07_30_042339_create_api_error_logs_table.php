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
        Schema::create('api_error_logs', function (Blueprint $table) {
            $table->id();
            $table->string('error_type')->index();
            $table->text('message');
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->json('context')->nullable();
            $table->string('request_id')->nullable()->index();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->text('url')->nullable();
            $table->string('method')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('set null');
            
            // Indexes for common queries
            $table->index(['company_id', 'created_at']);
            $table->index(['error_type', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_error_logs');
    }
};
