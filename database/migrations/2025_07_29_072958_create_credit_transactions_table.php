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
        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->integer('amount');
            $table->enum('type', ['debit', 'credit']);
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index(['company_id', 'created_at']);
            $table->index(['company_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');
    }
};
