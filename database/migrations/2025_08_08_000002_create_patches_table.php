<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('patch_id')->index();
            $table->longText('envelope_json');
            $table->longText('diff_json_gz')->nullable();
            $table->unsignedInteger('tokens_used')->default(0);
            $table->boolean('success')->default(false);
            $table->json('timings')->nullable();
            $table->string('etag')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patches');
    }
};

