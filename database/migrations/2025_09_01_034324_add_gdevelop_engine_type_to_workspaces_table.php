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
        Schema::table('workspaces', function (Blueprint $table) {
            // Update the engine_type enum to include 'gdevelop'
            $table->enum('engine_type', ['unreal', 'playcanvas', 'gdevelop'])->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            // Revert back to original enum values
            $table->enum('engine_type', ['unreal', 'playcanvas'])->change();
        });
    }
};
