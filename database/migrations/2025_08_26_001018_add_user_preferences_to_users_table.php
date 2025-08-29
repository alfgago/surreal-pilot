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
        Schema::table('users', function (Blueprint $table) {
            $table->json('preferences')->nullable()->after('current_company_id');
            $table->string('avatar_url')->nullable()->after('preferences');
            $table->text('bio')->nullable()->after('avatar_url');
            $table->string('timezone')->default('UTC')->after('bio');
            $table->boolean('email_notifications')->default(true)->after('timezone');
            $table->boolean('browser_notifications')->default(true)->after('email_notifications');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'preferences',
                'avatar_url',
                'bio',
                'timezone',
                'email_notifications',
                'browser_notifications'
            ]);
        });
    }
};