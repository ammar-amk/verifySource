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
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained()->onDelete('cascade');
            $table->string('url')->unique();
            $table->string('title');
            $table->longText('content');
            $table->text('excerpt')->nullable();
            $table->string('author')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('crawled_at')->nullable();
            $table->string('content_hash', 64);
            $table->string('language', 5)->default('en');
            $table->json('metadata')->nullable();
            $table->boolean('is_processed')->default(false);
            $table->boolean('is_duplicate')->default(false);
            $table->timestamps();
            
            $table->index(['source_id', 'published_at'], 'articles_source_published_idx');
            $table->index(['content_hash'], 'articles_content_hash_idx');
            $table->index(['published_at', 'is_processed'], 'articles_published_processed_idx');
            $table->index(['is_duplicate', 'is_processed'], 'articles_duplicate_processed_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
