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
        // Domain trust scores and metrics
        Schema::create('domain_trust_scores', function (Blueprint $table) {
            $table->id();
            $table->string('domain', 255)->unique();
            $table->decimal('trust_score', 5, 2)->default(50.00); // 0-100 scale
            $table->json('trust_factors'); // Detailed trust indicators
            $table->json('risk_factors')->nullable(); // Risk indicators
            $table->decimal('domain_age_score', 5, 2)->default(0);
            $table->decimal('ssl_score', 5, 2)->default(0);
            $table->decimal('whois_score', 5, 2)->default(0);
            $table->decimal('security_score', 5, 2)->default(0);
            $table->boolean('is_trusted_source')->default(false);
            $table->boolean('is_government')->default(false);
            $table->boolean('is_academic')->default(false);
            $table->boolean('is_news_organization')->default(false);
            $table->string('classification', 50)->default('unknown');
            $table->timestamp('last_analyzed_at');
            $table->timestamps();
            
            $table->index(['trust_score', 'last_analyzed_at']);
            $table->index(['classification', 'trust_score']);
            $table->index(['is_trusted_source', 'trust_score']);
        });

        // Source credibility scores
        Schema::create('source_credibility_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained()->onDelete('cascade');
            $table->decimal('overall_score', 5, 2); // 0-100 scale
            $table->decimal('domain_trust_score', 5, 2);
            $table->decimal('content_quality_score', 5, 2);
            $table->decimal('bias_score', 5, 2);
            $table->decimal('external_validation_score', 5, 2);
            $table->decimal('historical_accuracy_score', 5, 2);
            $table->json('score_breakdown'); // Detailed component scores
            $table->json('scoring_factors'); // Factors that influenced the score
            $table->string('credibility_level', 50); // highly_credible, credible, etc.
            $table->text('score_explanation')->nullable();
            $table->integer('confidence_level')->default(50); // 0-100
            $table->timestamp('calculated_at');
            $table->timestamps();
            
            $table->index(['overall_score', 'calculated_at'], 'src_cred_score_date_idx');
            $table->index(['credibility_level', 'overall_score'], 'src_cred_level_score_idx');
            $table->index(['source_id', 'calculated_at'], 'src_cred_source_date_idx');
        });

        // Article credibility scores
        Schema::create('article_credibility_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->onDelete('cascade');
            $table->decimal('overall_score', 5, 2); // 0-100 scale
            $table->decimal('content_quality_score', 5, 2);
            $table->decimal('readability_score', 5, 2);
            $table->decimal('fact_density_score', 5, 2);
            $table->decimal('citation_score', 5, 2);
            $table->decimal('bias_score', 5, 2);
            $table->decimal('sentiment_neutrality', 5, 2);
            $table->json('quality_indicators'); // Positive quality factors
            $table->json('quality_detractors'); // Negative quality factors
            $table->json('bias_analysis'); // Detailed bias assessment
            $table->string('credibility_level', 50);
            $table->text('analysis_summary')->nullable();
            $table->timestamp('analyzed_at');
            $table->timestamps();
            
            $table->index(['overall_score', 'analyzed_at'], 'art_cred_score_date_idx');
            $table->index(['credibility_level', 'overall_score'], 'art_cred_level_score_idx');
            $table->index(['article_id', 'analyzed_at'], 'art_cred_article_date_idx');
        });

        // Bias detection results
        Schema::create('bias_detection_results', function (Blueprint $table) {
            $table->id();
            $table->morphs('content'); // Can be article, source, etc.
            $table->decimal('political_bias_score', 5, 2); // -100 to 100 (left to right)
            $table->decimal('emotional_bias_score', 5, 2); // 0-100 (neutral to highly emotional)
            $table->decimal('factual_reporting_score', 5, 2); // 0-100 (low to high factual)
            $table->string('political_leaning', 50)->nullable(); // left, center-left, center, etc.
            $table->string('bias_classification', 50); // minimal, moderate, high
            $table->json('detected_patterns'); // Specific bias patterns found
            $table->json('language_analysis'); // Sentiment, emotional words, etc.
            $table->json('confidence_metrics'); // Confidence in bias assessment
            $table->text('bias_explanation')->nullable();
            $table->timestamp('detected_at');
            $table->timestamps();
            
            $table->index(['political_bias_score', 'detected_at'], 'bias_pol_score_date_idx');
            $table->index(['bias_classification', 'detected_at'], 'bias_class_date_idx');
        });

        // External validation results
        Schema::create('external_validation_results', function (Blueprint $table) {
            $table->id();
            $table->morphs('content'); // Can be article, claim, etc.
            $table->string('validator_type', 50); // fact_check, wayback, news_cross_ref
            $table->string('validator_source', 255); // snopes.com, politifact.com, etc.
            $table->string('validation_status', 50); // verified, disputed, false, etc.
            $table->decimal('confidence_score', 5, 2); // 0-100
            $table->json('validation_details'); // Detailed results from validator
            $table->text('validator_explanation')->nullable();
            $table->string('source_url', 512)->nullable();
            $table->timestamp('validated_at');
            $table->timestamps();
            
            $table->index(['validation_status', 'confidence_score'], 'ext_val_status_conf_idx');
            $table->index(['validator_type', 'validated_at'], 'ext_val_type_date_idx');
        });

        // Historical accuracy tracking
        Schema::create('historical_accuracy_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained()->onDelete('cascade');
            $table->date('tracking_period'); // Monthly or weekly accuracy records
            $table->integer('total_articles');
            $table->integer('fact_checked_articles');
            $table->integer('accurate_articles');
            $table->integer('corrections_issued');
            $table->integer('retractions_issued');
            $table->decimal('accuracy_rate', 5, 2); // Percentage
            $table->decimal('correction_rate', 5, 2); // Percentage
            $table->decimal('retraction_rate', 5, 2); // Percentage
            $table->json('accuracy_details')->nullable();
            $table->timestamps();
            
            $table->unique(['source_id', 'tracking_period']);
            $table->index(['accuracy_rate', 'tracking_period'], 'hist_acc_rate_period_idx');
            $table->index(['source_id', 'tracking_period'], 'hist_acc_source_period_idx');
        });

        // Credibility score audit log
        Schema::create('credibility_score_audits', function (Blueprint $table) {
            $table->id();
            $table->morphs('scoreable'); // source, article, etc.
            $table->string('score_type', 50); // overall, domain_trust, content_quality, etc.
            $table->decimal('old_score', 5, 2)->nullable();
            $table->decimal('new_score', 5, 2);
            $table->json('scoring_factors'); // What influenced the score change
            $table->string('trigger', 100); // manual_review, automated_update, etc.
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->text('change_reason')->nullable();
            $table->timestamp('changed_at');
            $table->timestamps();
            
            $table->index(['score_type', 'changed_at']);
            $table->index(['trigger', 'changed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credibility_score_audits');
        Schema::dropIfExists('historical_accuracy_records');
        Schema::dropIfExists('external_validation_results');
        Schema::dropIfExists('bias_detection_results');
        Schema::dropIfExists('article_credibility_scores');
        Schema::dropIfExists('source_credibility_scores');
        Schema::dropIfExists('domain_trust_scores');
    }
};
