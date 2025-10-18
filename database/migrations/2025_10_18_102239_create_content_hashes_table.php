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
        Schema::create('content_hashes', function (Blueprint $table) {
            $table->id();
            $table->string('hash', 64)->unique();
            $table->foreignId('article_id')->constrained()->onDelete('cascade');
            $table->string('hash_type')->default('sha256');
            $table->decimal('similarity_score', 5, 4)->nullable();
            $table->json('similar_hashes')->nullable();
            $table->timestamps();
            
            $table->index(['hash']);
            $table->index(['hash_type', 'similarity_score']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_hashes');
    }
};
