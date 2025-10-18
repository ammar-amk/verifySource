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
        Schema::create('verification_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('verification_request_id')->constrained()->onDelete('cascade');
            $table->foreignId('article_id')->constrained()->onDelete('cascade');
            $table->decimal('similarity_score', 5, 4);
            $table->decimal('credibility_score', 3, 2);
            $table->timestamp('earliest_publication')->nullable();
            $table->string('match_type')->default('exact');
            $table->json('match_details')->nullable();
            $table->boolean('is_earliest_source')->default(false);
            $table->timestamps();
            
            $table->index(['verification_request_id', 'similarity_score'], 'vr_request_similarity_idx');
            $table->index(['article_id', 'is_earliest_source'], 'vr_article_earliest_idx');
            $table->index(['earliest_publication', 'credibility_score'], 'vr_publication_credibility_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('verification_results');
    }
};
