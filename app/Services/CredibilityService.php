<?php

namespace App\Services;

use App\Models\Source;
use App\Models\Article;
use App\Models\DomainTrustScore;
use App\Models\SourceCredibilityScore;
use App\Models\ArticleCredibilityScore;
use App\Models\BiasDetectionResult;
use App\Models\CredibilityScoreAudit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Exception;

class CredibilityService
{
    private DomainTrustService $domainTrustService;
    private ContentQualityService $contentQualityService;
    private BiasDetectionService $biasDetectionService;
    private ExternalApiService $externalApiService;
    
    public function __construct(
        DomainTrustService $domainTrustService,
        ContentQualityService $contentQualityService,
        BiasDetectionService $biasDetectionService,
        ExternalApiService $externalApiService
    ) {
        $this->domainTrustService = $domainTrustService;
        $this->contentQualityService = $contentQualityService;
        $this->biasDetectionService = $biasDetectionService;
        $this->externalApiService = $externalApiService;
    }
    
    /**
     * Calculate comprehensive credibility score for a source
     */
    public function calculateSourceCredibility(Source $source, array $options = []): array
    {
        $cacheKey = "source_credibility_{$source->id}";
        
        if (config('credibility.caching.enabled') && !($options['force_recalculate'] ?? false)) {
            $cached = Cache::get($cacheKey);
            if ($cached && is_array($cached)) {
                return $cached;
            }
        }

        try {
            // Get component scores
            $domainTrustScore = $this->domainTrustService->analyzeDomain($source->domain);
            $contentQualityScore = $this->calculateAverageContentQuality($source);
            $biasScore = $this->calculateAverageBiasScore($source);
            $externalValidationScore = $this->getExternalValidationScore($source);
            $historicalAccuracyScore = $this->calculateHistoricalAccuracy($source);

            // Calculate weighted overall score
            $weights = config('credibility.weights');
            $overallScore = 
                ($domainTrustScore->trust_score * $weights['domain_trust']) +
                ($contentQualityScore * $weights['content_quality']) +
                (100 - $biasScore * $weights['bias_assessment']) + // Lower bias is better
                ($externalValidationScore * $weights['external_validation']) +
                ($historicalAccuracyScore * $weights['historical_accuracy']);

            $overallScore = max(0, min(100, $overallScore));

            // Create or update credibility score record
            $credibilityScore = SourceCredibilityScore::updateOrCreate(
                ['source_id' => $source->id],
                [
                    'overall_score' => $overallScore,
                    'domain_trust_score' => $domainTrustScore->trust_score,
                    'content_quality_score' => $contentQualityScore,
                    'bias_score' => $biasScore,
                    'external_validation_score' => $externalValidationScore,
                    'historical_accuracy_score' => $historicalAccuracyScore,
                    'score_breakdown' => $this->generateScoreBreakdown([
                        'domain_trust' => $domainTrustScore->trust_score,
                        'content_quality' => $contentQualityScore,
                        'bias_assessment' => $biasScore,
                        'external_validation' => $externalValidationScore,
                        'historical_accuracy' => $historicalAccuracyScore,
                    ]),
                    'scoring_factors' => $this->collectScoringFactors($source, $domainTrustScore),
                    'credibility_level' => $this->getCredibilityLevel($overallScore),
                    'score_explanation' => $this->generateScoreExplanation($overallScore, $source),
                    'confidence_level' => $this->calculateConfidenceLevel($source),
                    'calculated_at' => now(),
                ]
            );

            // Log the scoring decision
            $this->logScoringDecision($source, $credibilityScore);

            // Cache the result
            if (config('credibility.caching.enabled')) {
                Cache::put($cacheKey, $credibilityScore->toArray(), config('credibility.caching.domain_scores_ttl'));
            }

            return [
                'overall_score' => $credibilityScore->overall_score,
                'domain_trust' => [
                    'overall_score' => $credibilityScore->domain_trust_score,
                ],
                'content_quality' => $credibilityScore->content_quality_score,
                'bias_assessment' => $credibilityScore->bias_score,
                'external_validation' => $credibilityScore->external_validation_score,
                'historical_accuracy' => $credibilityScore->historical_accuracy_score,
                'score_breakdown' => $credibilityScore->score_breakdown,
                'scoring_factors' => $credibilityScore->scoring_factors,
                'credibility_level' => $credibilityScore->credibility_level,
                'score_explanation' => $credibilityScore->score_explanation,
                'confidence_level' => $credibilityScore->confidence_level,
                'calculated_at' => $credibilityScore->calculated_at,
            ];

        } catch (Exception $e) {
            Log::error('Source credibility calculation failed', [
                'source_id' => $source->id,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Calculate credibility score for an article
     */
    public function calculateArticleCredibility(Article $article, array $options = []): array
    {
        $cacheKey = "article_credibility_{$article->id}";
        
        if (config('credibility.caching.enabled') && !($options['force_recalculate'] ?? false)) {
            $cached = Cache::get($cacheKey);
            if ($cached && is_array($cached)) {
                return $cached;
            }
        }

        try {
            // Analyze content quality
            $qualityAnalysis = $this->contentQualityService->analyzeContent($article->content, [
                'title' => $article->title,
                'url' => $article->url,
            ]);

            // Analyze bias
            $biasAnalysis = $this->biasDetectionService->analyzeBias($article->content, [
                'title' => $article->title,
            ]);

            // Get source credibility (affects article score)
            $sourceCredibility = $this->calculateSourceCredibility($article->source);

            // Calculate overall article score
            $overallScore = $this->calculateWeightedArticleScore($qualityAnalysis, $biasAnalysis, $sourceCredibility);

            // Create or update article credibility score
            $credibilityScore = ArticleCredibilityScore::updateOrCreate(
                ['article_id' => $article->id],
                [
                    'overall_score' => $overallScore,
                    'content_quality_score' => $qualityAnalysis['overall_quality_score'],
                    'readability_score' => $qualityAnalysis['readability_score'],
                    'fact_density_score' => $qualityAnalysis['fact_density_score'],
                    'citation_score' => $qualityAnalysis['citation_score'],
                    'bias_score' => $biasAnalysis['emotional_bias_score'],
                    'sentiment_neutrality' => $biasAnalysis['neutrality_score'],
                    'quality_indicators' => $qualityAnalysis['quality_indicators'],
                    'quality_detractors' => $qualityAnalysis['quality_detractors'],
                    'bias_analysis' => $biasAnalysis,
                    'credibility_level' => $this->getCredibilityLevel($overallScore),
                    'analysis_summary' => $this->generateArticleAnalysisSummary($qualityAnalysis, $biasAnalysis),
                    'analyzed_at' => now(),
                ]
            );

            // Store bias detection result
            BiasDetectionResult::updateOrCreate(
                ['content_type' => 'article', 'content_id' => $article->id],
                [
                    'political_bias_score' => $biasAnalysis['political_bias_score'],
                    'emotional_bias_score' => $biasAnalysis['emotional_bias_score'],
                    'factual_reporting_score' => $biasAnalysis['factual_reporting_score'],
                    'political_leaning' => $biasAnalysis['political_leaning'],
                    'bias_classification' => $biasAnalysis['bias_classification'],
                    'detected_patterns' => $biasAnalysis['detected_patterns'],
                    'language_analysis' => $biasAnalysis['language_analysis'],
                    'confidence_metrics' => $biasAnalysis['confidence_metrics'],
                    'bias_explanation' => $biasAnalysis['explanation'],
                    'detected_at' => now(),
                ]
            );

            // Cache the result
            if (config('credibility.caching.enabled')) {
                Cache::put($cacheKey, $credibilityScore->toArray(), config('credibility.caching.content_scores_ttl'));
            }

            return [
                'overall_score' => $credibilityScore->overall_score,
                'content_quality_score' => $credibilityScore->content_quality_score,
                'readability_score' => $credibilityScore->readability_score,
                'fact_density_score' => $credibilityScore->fact_density_score,
                'citation_score' => $credibilityScore->citation_score,
                'bias_score' => $credibilityScore->bias_score,
                'sentiment_neutrality' => $credibilityScore->sentiment_neutrality,
                'quality_indicators' => $credibilityScore->quality_indicators,
                'quality_detractors' => $credibilityScore->quality_detractors,
                'bias_analysis' => $credibilityScore->bias_analysis,
                'credibility_level' => $credibilityScore->credibility_level,
                'analysis_summary' => $credibilityScore->analysis_summary,
                'analyzed_at' => $credibilityScore->analyzed_at,
            ];

        } catch (Exception $e) {
            Log::error('Article credibility calculation failed', [
                'article_id' => $article->id,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Get quick credibility assessment without full calculation
     */
    public function getQuickCredibilityAssessment($url): array
    {
        try {
            $domain = parse_url($url, PHP_URL_HOST);
            
            // Check if we have cached domain trust score
            $domainTrust = DomainTrustScore::where('domain', $domain)->first();
            
            if (!$domainTrust) {
                // Quick domain analysis
                $domainTrust = $this->domainTrustService->quickDomainAnalysis($domain);
            }

            // Check external APIs for quick validation
            $externalValidation = $this->externalApiService->performQuickVerification($url);

            $trustScore = $domainTrust->trust_score ?? 50;
            $externalScore = $externalValidation['trust_score'] * 100;
            
            $quickScore = ($trustScore * 0.7) + ($externalScore * 0.3);

            return [
                'quick_score' => $quickScore,
                'credibility_level' => $this->getCredibilityLevel($quickScore),
                'domain_trust' => $trustScore,
                'external_validation' => $externalScore,
                'is_safe' => $externalValidation['safe'] ?? false,
                'warnings' => $externalValidation['warnings'] ?? [],
                'assessment_time' => now()->toISOString(),
            ];

        } catch (Exception $e) {
            Log::error('Quick credibility assessment failed', [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            
            return [
                'quick_score' => 50,
                'credibility_level' => 'unknown',
                'error' => 'Assessment failed',
            ];
        }
    }

    /**
     * Bulk update credibility scores for multiple sources
     */
    public function bulkUpdateCredibilityScores(array $sourceIds, array $options = []): array
    {
        $results = [
            'updated' => [],
            'failed' => [],
            'skipped' => [],
        ];

        foreach ($sourceIds as $sourceId) {
            try {
                $source = Source::find($sourceId);
                if (!$source) {
                    $results['failed'][] = ['id' => $sourceId, 'error' => 'Source not found'];
                    continue;
                }

                // Check if update is needed
                $existingScore = SourceCredibilityScore::where('source_id', $sourceId)->first();
                if ($existingScore && !$existingScore->isExpired() && !($options['force'] ?? false)) {
                    $results['skipped'][] = ['id' => $sourceId, 'reason' => 'Score not expired'];
                    continue;
                }

                $credibilityScore = $this->calculateSourceCredibility($source, $options);
                $results['updated'][] = [
                    'id' => $sourceId,
                    'score' => $credibilityScore->overall_score,
                    'level' => $credibilityScore->credibility_level,
                ];

            } catch (Exception $e) {
                $results['failed'][] = [
                    'id' => $sourceId,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }
    
    /**
     * Calculate average content quality for a source
     */
    private function calculateAverageContentQuality(Source $source): float
    {
        $articles = $source->articles()
            ->where('created_at', '>=', now()->subDays(30))
            ->limit(20)
            ->get();

        if ($articles->isEmpty()) {
            return 50.0; // Default neutral score
        }

        $totalScore = 0;
        $analyzedCount = 0;

        foreach ($articles as $article) {
            try {
                $quality = $this->contentQualityService->analyzeContent($article->content, [
                    'title' => $article->title,
                ]);
                $totalScore += $quality['overall_quality_score'];
                $analyzedCount++;
            } catch (Exception $e) {
                // Skip articles that can't be analyzed
                continue;
            }
        }

        return $analyzedCount > 0 ? $totalScore / $analyzedCount : 50.0;
    }

    /**
     * Calculate average bias score for a source
     */
    private function calculateAverageBiasScore(Source $source): float
    {
        $articles = $source->articles()
            ->where('created_at', '>=', now()->subDays(30))
            ->limit(20)
            ->get();

        if ($articles->isEmpty()) {
            return 50.0; // Default neutral bias
        }

        $totalBias = 0;
        $analyzedCount = 0;

        foreach ($articles as $article) {
            try {
                $bias = $this->biasDetectionService->analyzeBias($article->content);
                $totalBias += abs($bias['political_bias_score']) + $bias['emotional_bias_score'];
                $analyzedCount++;
            } catch (Exception $e) {
                continue;
            }
        }

        return $analyzedCount > 0 ? $totalBias / $analyzedCount : 50.0;
    }

    /**
     * Get external validation score for a source
     */
    private function getExternalValidationScore(Source $source): float
    {
        // This would integrate with fact-checking APIs and other validation services
        // For now, return a placeholder score based on known source lists
        
        $knownSources = config('credibility.known_sources');
        $domain = $source->domain;

        if (in_array($domain, $knownSources['highly_trusted'])) {
            return 95.0;
        }
        
        if (in_array($domain, $knownSources['trusted_news'])) {
            return 85.0;
        }
        
        if (in_array($domain, $knownSources['unreliable_sources'])) {
            return 10.0;
        }

        // Default score - would be enhanced with real external validation
        return 50.0;
    }

    /**
     * Calculate historical accuracy for a source
     */
    private function calculateHistoricalAccuracy(Source $source): float
    {
        // This would analyze corrections, retractions, and fact-check results over time
        // For now, return a placeholder score
        return 75.0;
    }

    /**
     * Calculate weighted article score
     */
    private function calculateWeightedArticleScore(array $qualityAnalysis, array $biasAnalysis, array $sourceCredibility): float
    {
        $contentWeight = 0.4;
        $biasWeight = 0.3;
        $sourceWeight = 0.3;

        $contentScore = $qualityAnalysis['overall_quality_score'];
        $biasScore = 100 - $biasAnalysis['emotional_bias_score']; // Lower bias is better
        $sourceScore = $sourceCredibility['overall_score'];

        return ($contentScore * $contentWeight) + ($biasScore * $biasWeight) + ($sourceScore * $sourceWeight);
    }

    /**
     * Generate score breakdown for transparency
     */
    private function generateScoreBreakdown(array $scores): array
    {
        $weights = config('credibility.weights');
        $breakdown = [];

        foreach ($scores as $component => $score) {
            $breakdown[$component] = [
                'raw_score' => $score,
                'weight' => $weights[$component] ?? 0,
                'weighted_score' => $score * ($weights[$component] ?? 0),
            ];
        }

        return $breakdown;
    }

    /**
     * Collect factors that influenced the scoring
     */
    private function collectScoringFactors(Source $source, DomainTrustScore $domainTrust): array
    {
        $factors = [];

        // Domain trust factors
        $factors['domain_trust'] = $domainTrust->trust_factors;
        
        if ($domainTrust->risk_factors) {
            $factors['risk_factors'] = $domainTrust->risk_factors;
        }

        // Source-specific factors
        $factors['source_age'] = $source->created_at->diffInDays(now());
        $factors['article_count'] = $source->articles()->count();
        $factors['recent_activity'] = $source->articles()->where('created_at', '>=', now()->subDays(30))->count();

        return $factors;
    }

    /**
     * Get credibility level based on score
     */
    private function getCredibilityLevel(float $score): string
    {
        $thresholds = config('credibility.thresholds');

        if ($score >= $thresholds['highly_credible']) return 'highly_credible';
        if ($score >= $thresholds['credible']) return 'credible';
        if ($score >= $thresholds['moderately_credible']) return 'moderately_credible';
        if ($score >= $thresholds['low_credibility']) return 'low_credibility';
        
        return 'not_credible';
    }

    /**
     * Generate human-readable score explanation
     */
    private function generateScoreExplanation(float $score, Source $source): string
    {
        $level = $this->getCredibilityLevel($score);
        
        $explanations = [
            'highly_credible' => "This source demonstrates excellent credibility with high trust indicators, quality content, and minimal bias.",
            'credible' => "This source shows good credibility with solid trust factors and generally reliable reporting.",
            'moderately_credible' => "This source has moderate credibility with some concerns about bias or content quality.",
            'low_credibility' => "This source has significant credibility issues and should be viewed with caution.",
            'not_credible' => "This source lacks credibility and may spread misinformation or unreliable content.",
        ];

        return $explanations[$level] ?? "Credibility assessment unavailable.";
    }

    /**
     * Calculate confidence level for the scoring
     */
    private function calculateConfidenceLevel(Source $source): int
    {
        $confidence = 50; // Base confidence

        // More articles = higher confidence
        $articleCount = $source->articles()->count();
        if ($articleCount > 100) $confidence += 20;
        elseif ($articleCount > 50) $confidence += 15;
        elseif ($articleCount > 10) $confidence += 10;

        // Domain age affects confidence
        $domainTrust = DomainTrustScore::where('domain', $source->domain)->first();
        if ($domainTrust && $domainTrust->domain_age_score > 70) {
            $confidence += 15;
        }

        // External validation boosts confidence
        if (in_array($source->domain, config('credibility.known_sources.highly_trusted'))) {
            $confidence += 20;
        }

        return min(100, max(0, $confidence));
    }

    /**
     * Generate article analysis summary
     */
    private function generateArticleAnalysisSummary(array $qualityAnalysis, array $biasAnalysis): string
    {
        $summary = [];
        
        if ($qualityAnalysis['overall_quality_score'] >= 80) {
            $summary[] = "High-quality content with good structure and factual reporting";
        } elseif ($qualityAnalysis['overall_quality_score'] >= 60) {
            $summary[] = "Adequate content quality with room for improvement";
        } else {
            $summary[] = "Content quality concerns detected";
        }

        if ($biasAnalysis['bias_classification'] === 'minimal') {
            $summary[] = "minimal bias detected";
        } elseif ($biasAnalysis['bias_classification'] === 'moderate') {
            $summary[] = "moderate bias present";
        } else {
            $summary[] = "significant bias concerns";
        }

        return implode(', ', $summary);
    }

    /**
     * Log scoring decisions for audit trail
     */
    private function logScoringDecision(Source $source, SourceCredibilityScore $credibilityScore): void
    {
        if (!config('credibility.logging.log_scoring_decisions')) {
            return;
        }

        Log::info('Source credibility score calculated', [
            'source_id' => $source->id,
            'domain' => $source->domain,
            'overall_score' => $credibilityScore->overall_score,
            'credibility_level' => $credibilityScore->credibility_level,
            'confidence_level' => $credibilityScore->confidence_level,
        ]);
    }

    /**
     * Health check for credibility service
     */
    public function healthCheck(): array
    {
        try {
            // Check database connectivity
            $domainCount = DomainTrustScore::count();
            $sourceScoreCount = SourceCredibilityScore::count();
            
            // Check service dependencies
            $domainTrustHealthy = $this->domainTrustService->healthCheck();
            $contentQualityHealthy = $this->contentQualityService->healthCheck();
            $biasDetectionHealthy = $this->biasDetectionService->healthCheck();
            
            return [
                'status' => 'healthy',
                'database' => [
                    'domain_trust_scores' => $domainCount,
                    'source_credibility_scores' => $sourceScoreCount,
                ],
                'services' => [
                    'domain_trust' => $domainTrustHealthy['status'] ?? 'unknown',
                    'content_quality' => $contentQualityHealthy['status'] ?? 'unknown',
                    'bias_detection' => $biasDetectionHealthy['status'] ?? 'unknown',
                ],
            ];
        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Assess credibility of a source (Legacy method - deprecated)
     */
    protected function assessSourceCredibility(int $sourceId, array $context): array
    {
        $source = Source::find($sourceId);
        if (!$source) {
            throw new Exception("Source not found: {$sourceId}");
        }
        
        $assessment = [
            'overall_score' => 0.0,
            'confidence' => 0.0,
            'factors' => [],
            'warnings' => [],
            'recommendations' => [],
            'evidence' => [],
            'last_assessed' => now()->toISOString(),
        ];
        
        // Factor 1: Domain authority and age
        $domainFactor = $this->assessDomainAuthority($source);
        $assessment['factors']['domain_authority'] = $domainFactor;
        
        // Factor 2: Publication consistency
        $consistencyFactor = $this->assessPublicationConsistency($source);
        $assessment['factors']['publication_consistency'] = $consistencyFactor;
        
        // Factor 3: Content quality patterns
        $qualityFactor = $this->assessContentQuality($source);
        $assessment['factors']['content_quality'] = $qualityFactor;
        
        // Factor 4: Editorial standards
        $editorialFactor = $this->assessEditorialStandards($source);
        $assessment['factors']['editorial_standards'] = $editorialFactor;
        
        // Factor 5: Transparency and accountability
        $transparencyFactor = $this->assessTransparency($source);
        $assessment['factors']['transparency'] = $transparencyFactor;
        
        // Factor 6: External validation
        $validationFactor = $this->assessExternalValidation($source);
        $assessment['factors']['external_validation'] = $validationFactor;
        
        // Calculate weighted overall score
        $weights = $this->config['source_weights'];
        $assessment['overall_score'] = 
            ($domainFactor['score'] * $weights['domain_authority']) +
            ($consistencyFactor['score'] * $weights['publication_consistency']) +
            ($qualityFactor['score'] * $weights['content_quality']) +
            ($editorialFactor['score'] * $weights['editorial_standards']) +
            ($transparencyFactor['score'] * $weights['transparency']) +
            ($validationFactor['score'] * $weights['external_validation']);
        
        // Calculate confidence based on available data
        $assessment['confidence'] = $this->calculateAssessmentConfidence($assessment['factors']);
        
        // Generate warnings and recommendations
        $assessment['warnings'] = $this->generateSourceWarnings($assessment);
        $assessment['recommendations'] = $this->generateSourceRecommendations($assessment);
        $assessment['evidence'] = $this->compileSourceEvidence($assessment);
        
        return $assessment;
    }
    
    /**
     * Assess credibility of an individual article
     */
    protected function assessArticleCredibility(int $articleId, array $context): array
    {
        $article = Article::with('source')->find($articleId);
        if (!$article) {
            throw new Exception("Article not found: {$articleId}");
        }
        
        $assessment = [
            'overall_score' => 0.0,
            'confidence' => 0.0,
            'factors' => [],
            'warnings' => [],
            'recommendations' => [],
            'evidence' => [],
            'last_assessed' => now()->toISOString(),
        ];
        
        // Factor 1: Source credibility (inherited)
        $sourceFactor = $this->inheritSourceCredibility($article->source);
        $assessment['factors']['source_credibility'] = $sourceFactor;
        
        // Factor 2: Content indicators
        $contentFactor = $this->assessArticleContent($article);
        $assessment['factors']['content_indicators'] = $contentFactor;
        
        // Factor 3: Metadata quality
        $metadataFactor = $this->assessArticleMetadata($article);
        $assessment['factors']['metadata_quality'] = $metadataFactor;
        
        // Factor 4: Verification context (from provenance analysis)
        $verificationFactor = $this->assessVerificationContext($article, $context);
        $assessment['factors']['verification_context'] = $verificationFactor;
        
        // Factor 5: Temporal consistency
        $temporalFactor = $this->assessTemporalConsistency($article, $context);
        $assessment['factors']['temporal_consistency'] = $temporalFactor;
        
        // Calculate weighted overall score
        $weights = $this->config['article_weights'];
        $assessment['overall_score'] = 
            ($sourceFactor['score'] * $weights['source_credibility']) +
            ($contentFactor['score'] * $weights['content_indicators']) +
            ($metadataFactor['score'] * $weights['metadata_quality']) +
            ($verificationFactor['score'] * $weights['verification_context']) +
            ($temporalFactor['score'] * $weights['temporal_consistency']);
        
        // Calculate confidence
        $assessment['confidence'] = $this->calculateAssessmentConfidence($assessment['factors']);
        
        // Generate warnings and recommendations
        $assessment['warnings'] = $this->generateArticleWarnings($assessment, $article);
        $assessment['recommendations'] = $this->generateArticleRecommendations($assessment, $article);
        $assessment['evidence'] = $this->compileArticleEvidence($assessment, $article);
        
        return $assessment;
    }
    
    /**
     * Assess domain authority and age
     */
    protected function assessDomainAuthority(Source $source): array
    {
        $factor = [
            'score' => 0.5, // Start with neutral
            'indicators' => [],
            'data_available' => false,
        ];
        
        try {
            $domain = parse_url($source->url, PHP_URL_HOST);
            if (!$domain) {
                $factor['indicators'][] = 'Invalid or missing domain';
                return $factor;
            }
            
            $factor['data_available'] = true;
            
            // Check domain age (if available in source metadata)
            if ($source->created_at) {
                $ageYears = now()->diffInYears($source->created_at);
                if ($ageYears >= 10) {
                    $factor['score'] += 0.3;
                    $factor['indicators'][] = "Established source ({$ageYears} years)";
                } elseif ($ageYears >= 5) {
                    $factor['score'] += 0.2;
                    $factor['indicators'][] = "Mature source ({$ageYears} years)";
                } elseif ($ageYears < 1) {
                    $factor['score'] -= 0.2;
                    $factor['indicators'][] = "Very new source (< 1 year)";
                }
            }
            
            // Check domain indicators
            $authorityIndicators = $this->checkDomainAuthorityIndicators($domain);
            foreach ($authorityIndicators as $indicator) {
                $factor['score'] += $indicator['impact'];
                $factor['indicators'][] = $indicator['description'];
            }
            
            // Clamp score
            $factor['score'] = max(0.0, min(1.0, $factor['score']));
            
        } catch (Exception $e) {
            $factor['indicators'][] = "Error assessing domain authority: " . $e->getMessage();
        }
        
        return $factor;
    }
    
    /**
     * Check domain authority indicators
     */
    protected function checkDomainAuthorityIndicators(string $domain): array
    {
        $indicators = [];
        
        // Check if it's a known news domain
        $knownNewsDomains = [
            'reuters.com', 'ap.org', 'bbc.com', 'cnn.com', 'nytimes.com',
            'washingtonpost.com', 'theguardian.com', 'wsj.com', 'npr.org'
        ];
        
        if (in_array($domain, $knownNewsDomains)) {
            $indicators[] = [
                'impact' => 0.4,
                'description' => 'Recognized major news organization'
            ];
        }
        
        // Check for academic/government domains
        if (preg_match('/\.(edu|gov|org)$/', $domain)) {
            $indicators[] = [
                'impact' => 0.2,
                'description' => 'Educational, government, or non-profit domain'
            ];
        }
        
        // Check for suspicious TLD patterns
        $suspiciousTlds = ['.tk', '.ml', '.ga', '.cf'];
        foreach ($suspiciousTlds as $tld) {
            if (str_ends_with($domain, $tld)) {
                $indicators[] = [
                    'impact' => -0.3,
                    'description' => 'Suspicious top-level domain'
                ];
                break;
            }
        }
        
        // Check for URL shorteners (should not be source domains)
        $shorteners = ['bit.ly', 'tinyurl.com', 't.co', 'goo.gl', 'short.link'];
        if (in_array($domain, $shorteners)) {
            $indicators[] = [
                'impact' => -0.5,
                'description' => 'URL shortener used as source domain'
            ];
        }
        
        return $indicators;
    }
    
    /**
     * Assess publication consistency
     */
    protected function assessPublicationConsistency(Source $source): array
    {
        $factor = [
            'score' => 0.5,
            'indicators' => [],
            'data_available' => false,
        ];
        
        // Get recent articles to analyze patterns
        $recentArticles = Article::where('source_id', $source->id)
            ->where('published_at', '>=', now()->subMonths(6))
            ->orderBy('published_at', 'desc')
            ->limit(100)
            ->get();
        
        if ($recentArticles->isEmpty()) {
            $factor['indicators'][] = 'No recent articles available for analysis';
            return $factor;
        }
        
        $factor['data_available'] = true;
        
        // Analyze publication frequency
        $frequencyScore = $this->analyzePublicationFrequency($recentArticles);
        $factor['score'] += $frequencyScore['impact'];
        $factor['indicators'] = array_merge($factor['indicators'], $frequencyScore['indicators']);
        
        // Analyze content diversity
        $diversityScore = $this->analyzeContentDiversity($recentArticles);
        $factor['score'] += $diversityScore['impact'];
        $factor['indicators'] = array_merge($factor['indicators'], $diversityScore['indicators']);
        
        // Analyze quality consistency
        $qualityScore = $this->analyzeQualityConsistency($recentArticles);
        $factor['score'] += $qualityScore['impact'];
        $factor['indicators'] = array_merge($factor['indicators'], $qualityScore['indicators']);
        
        // Clamp score
        $factor['score'] = max(0.0, min(1.0, $factor['score']));
        
        return $factor;
    }
    
    /**
     * Analyze publication frequency patterns
     */
    protected function analyzePublicationFrequency($articles): array
    {
        $result = ['impact' => 0.0, 'indicators' => []];
        
        if ($articles->count() < 10) {
            $result['impact'] = -0.1;
            $result['indicators'][] = 'Limited publication history';
            return $result;
        }
        
        // Calculate average articles per day
        $daySpan = $articles->first()->published_at->diffInDays($articles->last()->published_at);
        $dailyAverage = $daySpan > 0 ? $articles->count() / $daySpan : 0;
        
        if ($dailyAverage > 20) {
            $result['impact'] = -0.2;
            $result['indicators'][] = 'Unusually high publication frequency (possible content farm)';
        } elseif ($dailyAverage > 5) {
            $result['impact'] = 0.1;
            $result['indicators'][] = 'High publication frequency (active news source)';
        } elseif ($dailyAverage >= 1) {
            $result['impact'] = 0.2;
            $result['indicators'][] = 'Regular publication schedule';
        } elseif ($dailyAverage > 0.1) {
            $result['impact'] = 0.1;
            $result['indicators'][] = 'Moderate publication frequency';
        } else {
            $result['impact'] = -0.1;
            $result['indicators'][] = 'Very low publication frequency';
        }
        
        return $result;
    }
    
    /**
     * Analyze content diversity
     */
    protected function analyzeContentDiversity($articles): array
    {
        $result = ['impact' => 0.0, 'indicators' => []];
        
        // Analyze title diversity (simple keyword extraction)
        $allWords = [];
        foreach ($articles as $article) {
            $words = str_word_count(strtolower($article->title), 1);
            $allWords = array_merge($allWords, $words);
        }
        
        $uniqueWords = count(array_unique($allWords));
        $totalWords = count($allWords);
        $diversityRatio = $totalWords > 0 ? $uniqueWords / $totalWords : 0;
        
        if ($diversityRatio > 0.7) {
            $result['impact'] = 0.2;
            $result['indicators'][] = 'High content diversity';
        } elseif ($diversityRatio > 0.5) {
            $result['impact'] = 0.1;
            $result['indicators'][] = 'Moderate content diversity';
        } else {
            $result['impact'] = -0.1;
            $result['indicators'][] = 'Low content diversity (possible topic focus or repetition)';
        }
        
        return $result;
    }
    
    /**
     * Analyze quality consistency
     */
    protected function analyzeQualityConsistency($articles): array
    {
        $result = ['impact' => 0.0, 'indicators' => []];
        
        $qualityScores = $articles->where('quality_score', '>', 0)->pluck('quality_score');
        
        if ($qualityScores->isEmpty()) {
            $result['indicators'][] = 'No quality scores available';
            return $result;
        }
        
        $avgQuality = $qualityScores->avg();
        $stdDev = $this->calculateStandardDeviation($qualityScores->toArray());
        
        if ($avgQuality > 80) {
            $result['impact'] += 0.2;
            $result['indicators'][] = 'High average content quality';
        } elseif ($avgQuality < 50) {
            $result['impact'] -= 0.2;
            $result['indicators'][] = 'Low average content quality';
        }
        
        if ($stdDev < 10) {
            $result['impact'] += 0.1;
            $result['indicators'][] = 'Consistent quality across articles';
        } elseif ($stdDev > 25) {
            $result['impact'] -= 0.1;
            $result['indicators'][] = 'Inconsistent quality across articles';
        }
        
        return $result;
    }
    
    /**
     * Assess content quality for source
     */
    protected function assessContentQuality(Source $source): array
    {
        // This would analyze the overall content quality patterns
        // For now, return a basic assessment
        return [
            'score' => 0.6,
            'indicators' => ['Content quality assessment requires more data'],
            'data_available' => false,
        ];
    }
    
    /**
     * Assess editorial standards
     */
    protected function assessEditorialStandards(Source $source): array
    {
        return [
            'score' => 0.5,
            'indicators' => ['Editorial standards assessment requires manual review'],
            'data_available' => false,
        ];
    }
    
    /**
     * Assess transparency and accountability
     */
    protected function assessTransparency(Source $source): array
    {
        return [
            'score' => 0.5,
            'indicators' => ['Transparency assessment requires website analysis'],
            'data_available' => false,
        ];
    }
    
    /**
     * Assess external validation
     */
    protected function assessExternalValidation(Source $source): array
    {
        return [
            'score' => 0.5,
            'indicators' => ['External validation requires third-party data sources'],
            'data_available' => false,
        ];
    }
    
    /**
     * Inherit source credibility for article assessment
     */
    protected function inheritSourceCredibility(Source $source): array
    {
        if ($source->credibility_score) {
            return [
                'score' => $source->credibility_score,
                'indicators' => ['Inherited from source credibility assessment'],
                'data_available' => true,
            ];
        }
        
        return [
            'score' => 0.5,
            'indicators' => ['Source credibility not yet assessed'],
            'data_available' => false,
        ];
    }
    
    /**
     * Assess article content indicators
     */
    protected function assessArticleContent(Article $article): array
    {
        $factor = [
            'score' => 0.5,
            'indicators' => [],
            'data_available' => true,
        ];
        
        // Check content length
        $contentLength = strlen($article->content ?? '');
        if ($contentLength > 2000) {
            $factor['score'] += 0.1;
            $factor['indicators'][] = 'Substantial content length';
        } elseif ($contentLength < 300) {
            $factor['score'] -= 0.2;
            $factor['indicators'][] = 'Very short content';
        }
        
        // Check for clickbait patterns in title
        $clickbaitPatterns = [
            '/you won\'t believe/i',
            '/shocking/i',
            '/\d+ reasons? why/i',
            '/doctors hate/i',
            '/one weird trick/i'
        ];
        
        foreach ($clickbaitPatterns as $pattern) {
            if (preg_match($pattern, $article->title)) {
                $factor['score'] -= 0.3;
                $factor['indicators'][] = 'Potential clickbait title detected';
                break;
            }
        }
        
        // Use quality score if available
        if ($article->quality_score > 0) {
            $qualityImpact = ($article->quality_score - 50) / 100; // Convert 0-100 to -0.5 to 0.5
            $factor['score'] += $qualityImpact;
            $factor['indicators'][] = "Content quality score: {$article->quality_score}";
        }
        
        $factor['score'] = max(0.0, min(1.0, $factor['score']));
        
        return $factor;
    }
    
    /**
     * Assess article metadata quality
     */
    protected function assessArticleMetadata(Article $article): array
    {
        $factor = [
            'score' => 0.5,
            'indicators' => [],
            'data_available' => true,
        ];
        
        // Check for complete metadata
        $metadataFields = ['title', 'url', 'published_at', 'author'];
        $completedFields = 0;
        
        foreach ($metadataFields as $field) {
            if (!empty($article->$field)) {
                $completedFields++;
            }
        }
        
        $completionRatio = $completedFields / count($metadataFields);
        $factor['score'] = $completionRatio;
        $factor['indicators'][] = "Metadata completion: {$completedFields}/" . count($metadataFields) . " fields";
        
        if ($article->author) {
            $factor['indicators'][] = 'Author information available';
        } else {
            $factor['indicators'][] = 'Missing author information';
        }
        
        return $factor;
    }
    
    /**
     * Assess verification context from provenance analysis
     */
    protected function assessVerificationContext(Article $article, array $context): array
    {
        $factor = [
            'score' => 0.5,
            'indicators' => [],
            'data_available' => !empty($context),
        ];
        
        if (empty($context)) {
            $factor['indicators'][] = 'No verification context available';
            return $factor;
        }
        
        // Check if this article was identified as original source
        if (isset($context['original_source']) && 
            $context['original_source']['article_id'] === $article->id) {
            $factor['score'] += 0.3;
            $factor['indicators'][] = 'Identified as likely original source';
        }
        
        // Check propagation patterns
        if (isset($context['propagation_pattern'])) {
            $pattern = $context['propagation_pattern']['pattern_type'];
            if (in_array($pattern, ['organic_propagation', 'gradual_spread'])) {
                $factor['score'] += 0.1;
                $factor['indicators'][] = 'Natural propagation pattern detected';
            } elseif ($pattern === 'viral_burst') {
                $factor['score'] -= 0.1;
                $factor['indicators'][] = 'Rapid viral spread (requires verification)';
            }
        }
        
        // Check for suspicious patterns
        if (isset($context['suspicious_patterns']) && !empty($context['suspicious_patterns'])) {
            $factor['score'] -= 0.2;
            $factor['indicators'][] = 'Suspicious propagation patterns detected';
        }
        
        $factor['score'] = max(0.0, min(1.0, $factor['score']));
        
        return $factor;
    }
    
    /**
     * Assess temporal consistency
     */
    protected function assessTemporalConsistency(Article $article, array $context): array
    {
        $factor = [
            'score' => 0.7, // Default good score
            'indicators' => [],
            'data_available' => true,
        ];
        
        // Check publication time reasonableness
        $publishedAt = $article->published_at;
        if ($publishedAt->isFuture()) {
            $factor['score'] -= 0.5;
            $factor['indicators'][] = 'Future publication date detected';
        }
        
        if ($publishedAt->isPast() && $publishedAt->lt(now()->subYears(50))) {
            $factor['score'] -= 0.2;
            $factor['indicators'][] = 'Unusually old publication date';
        }
        
        // Check against creation/crawl time
        if ($article->created_at && $publishedAt->gt($article->created_at)) {
            $factor['score'] -= 0.3;
            $factor['indicators'][] = 'Publication date after content discovery';
        }
        
        return $factor;
    }
    
    /**
     * Calculate assessment confidence based on available data
     */
    protected function calculateAssessmentConfidence(array $factors): float
    {
        $totalFactors = count($factors);
        $availableData = 0;
        
        foreach ($factors as $factor) {
            if ($factor['data_available'] ?? false) {
                $availableData++;
            }
        }
        
        return $totalFactors > 0 ? ($availableData / $totalFactors) : 0.0;
    }
    
    /**
     * Generate source warnings
     */
    protected function generateSourceWarnings(array $assessment): array
    {
        $warnings = [];
        
        if ($assessment['overall_score'] < 0.3) {
            $warnings[] = [
                'severity' => 'high',
                'message' => 'Low overall credibility score - exercise extreme caution',
            ];
        }
        
        if ($assessment['confidence'] < 0.5) {
            $warnings[] = [
                'severity' => 'medium',
                'message' => 'Limited data available for comprehensive assessment',
            ];
        }
        
        return $warnings;
    }
    
    /**
     * Generate article warnings
     */
    protected function generateArticleWarnings(array $assessment, Article $article): array
    {
        $warnings = [];
        
        if ($assessment['overall_score'] < 0.4) {
            $warnings[] = [
                'severity' => 'high',
                'message' => 'Multiple credibility concerns identified',
            ];
        }
        
        return $warnings;
    }
    
    /**
     * Generate source recommendations
     */
    protected function generateSourceRecommendations(array $assessment): array
    {
        $recommendations = [];
        
        if ($assessment['overall_score'] < 0.6) {
            $recommendations[] = 'Cross-reference with other sources before trusting content';
        }
        
        if ($assessment['confidence'] < 0.7) {
            $recommendations[] = 'Gather additional source information for better assessment';
        }
        
        return $recommendations;
    }
    
    /**
     * Generate article recommendations
     */
    protected function generateArticleRecommendations(array $assessment, Article $article): array
    {
        $recommendations = [];
        
        if ($assessment['overall_score'] < 0.5) {
            $recommendations[] = 'Verify claims through independent sources';
            $recommendations[] = 'Check for more recent or authoritative coverage';
        }
        
        return $recommendations;
    }
    
    /**
     * Compile source evidence
     */
    protected function compileSourceEvidence(array $assessment): array
    {
        $evidence = [];
        
        foreach ($assessment['factors'] as $name => $factor) {
            if ($factor['data_available']) {
                $evidence[] = [
                    'factor' => $name,
                    'score' => $factor['score'],
                    'indicators' => $factor['indicators'],
                ];
            }
        }
        
        return $evidence;
    }
    
    /**
     * Compile article evidence
     */
    protected function compileArticleEvidence(array $assessment, Article $article): array
    {
        return $this->compileSourceEvidence($assessment);
    }
    
    /**
     * Calculate standard deviation
     */
    protected function calculateStandardDeviation(array $values): float
    {
        if (count($values) < 2) {
            return 0.0;
        }
        
        $mean = array_sum($values) / count($values);
        $variance = array_sum(array_map(fn($x) => pow($x - $mean, 2), $values)) / count($values);
        
        return sqrt($variance);
    }
}