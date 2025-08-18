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
            $table->integer('credits')->default(0)->after('personal_company');
            $table->string('plan')->default('starter')->after('credits');
            $table->integer('monthly_credit_limit')->default(1000)->after('plan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['credits', 'plan', 'monthly_credit_limit']);
        });
    }
};
