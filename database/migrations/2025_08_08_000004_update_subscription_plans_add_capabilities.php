<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->boolean('allow_unreal')->default(true)->after('allow_byo_keys');
            $table->boolean('allow_multiplayer')->default(false)->after('allow_unreal');
            $table->boolean('allow_advanced_publish')->default(false)->after('allow_multiplayer');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn(['allow_unreal', 'allow_multiplayer', 'allow_advanced_publish']);
        });
    }
};

