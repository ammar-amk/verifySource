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
        Schema::table('crawl_jobs', function (Blueprint $table) {
            $table->timestamp('dispatched_at')->nullable()->after('completed_at');
            $table->index('dispatched_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crawl_jobs', function (Blueprint $table) {
            $table->dropIndex(['dispatched_at']);
            $table->dropColumn('dispatched_at');
        });
    }
};
