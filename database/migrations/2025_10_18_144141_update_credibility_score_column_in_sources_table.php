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
        Schema::table('sources', function (Blueprint $table) {
            // Change credibility_score to support 0-100 range
            $table->decimal('credibility_score', 5, 2)->default(50.00)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sources', function (Blueprint $table) {
            // Revert to original 0-1 range
            $table->decimal('credibility_score', 3, 2)->default(0.50)->change();
        });
    }
};
