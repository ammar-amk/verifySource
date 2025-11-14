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
        Schema::table('articles', function (Blueprint $table) {
            $table->decimal('quality_score', 5, 2)->nullable()->after('content');
            $table->decimal('credibility_score', 5, 2)->nullable()->after('quality_score');
            $table->string('credibility_level', 50)->nullable()->after('credibility_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn(['quality_score', 'credibility_score', 'credibility_level']);
        });
    }
};
