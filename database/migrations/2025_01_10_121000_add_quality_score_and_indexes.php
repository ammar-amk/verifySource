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
        // Add indexes for better performance
        try {
            Schema::table('articles', function (Blueprint $table) {
                $table->index(['quality_score'], 'articles_quality_score_idx');
            });
        } catch (\Exception $e) {
            // Index may already exist, continue
        }

        try {
            Schema::table('articles', function (Blueprint $table) {
                $table->index(['author'], 'articles_author_idx');
            });
        } catch (\Exception $e) {
            // Index may already exist, continue
        }

        try {
            Schema::table('sources', function (Blueprint $table) {
                $table->index(['name'], 'sources_name_idx');
            });
        } catch (\Exception $e) {
            // Index may already exist, continue
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            Schema::table('articles', function (Blueprint $table) {
                $table->dropIndex(['quality_score']);
            });
        } catch (\Exception $e) {
            // Index may not exist, continue
        }

        try {
            Schema::table('articles', function (Blueprint $table) {
                $table->dropIndex(['author']);
            });
        } catch (\Exception $e) {
            // Index may not exist, continue
        }

        try {
            Schema::table('sources', function (Blueprint $table) {
                $table->dropIndex(['name']);
            });
        } catch (\Exception $e) {
            // Index may not exist, continue
        }
    }
};