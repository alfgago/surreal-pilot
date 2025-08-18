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
        Schema::table('credit_transactions', function (Blueprint $table) {
            // Change amount from integer to decimal to support MCP surcharges
            $table->decimal('amount', 10, 2)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('credit_transactions', function (Blueprint $table) {
            // Revert back to integer
            $table->integer('amount')->change();
        });
    }
};