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
            $table->string('credibility_level', 50)->nullable()->after('credibility_score');
            $table->decimal('trust_score', 5, 2)->nullable()->after('credibility_level');
            $table->timestamp('last_credibility_check')->nullable()->after('trust_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sources', function (Blueprint $table) {
            $table->dropColumn(['credibility_level', 'trust_score', 'last_credibility_check']);
        });
    }
};
