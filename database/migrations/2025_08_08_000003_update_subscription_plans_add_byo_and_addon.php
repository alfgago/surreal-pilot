<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->boolean('allow_byo_keys')->default(false)->after('stripe_price_id');
            $table->integer('addon_price_cents')->nullable()->after('allow_byo_keys');
            $table->integer('addon_credits_per_unit')->nullable()->after('addon_price_cents');
            $table->json('features')->nullable()->after('addon_credits_per_unit');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn(['allow_byo_keys', 'addon_price_cents', 'addon_credits_per_unit', 'features']);
        });
    }
};

