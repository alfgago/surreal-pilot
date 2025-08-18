<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->text('openai_api_key_enc')->nullable()->after('playcanvas_project_id');
            $table->text('anthropic_api_key_enc')->nullable()->after('openai_api_key_enc');
            $table->text('gemini_api_key_enc')->nullable()->after('anthropic_api_key_enc');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['openai_api_key_enc', 'anthropic_api_key_enc', 'gemini_api_key_enc']);
        });
    }
};

