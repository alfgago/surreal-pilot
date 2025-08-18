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
        Schema::table('companies', function (Blueprint $table) {
            // Change credits from integer to decimal to support MCP surcharges
            $table->decimal('credits', 10, 2)->default(0)->change();
            $table->decimal('monthly_credit_limit', 10, 2)->default(1000)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // Revert back to integer
            $table->integer('credits')->default(0)->change();
            $table->integer('monthly_credit_limit')->default(1000)->change();
        });
    }
};