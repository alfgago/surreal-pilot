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
        Schema::create('billing_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->string('type'); // credit_purchase, subscription_payment, refund, etc.
            $table->string('description');
            $table->integer('amount_cents');
            $table->string('currency', 3)->default('usd');
            $table->string('status'); // succeeded, failed, pending, refunded
            $table->string('stripe_payment_intent_id')->nullable();
            $table->string('stripe_invoice_id')->nullable();
            $table->string('stripe_subscription_id')->nullable();
            $table->integer('credits_added')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'type']);
            $table->index(['company_id', 'status']);
            $table->index('processed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_history');
    }
};