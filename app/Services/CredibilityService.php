<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Source;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CredibilityService
{
    protected array $config;

    public function __construct()
    {
        $this->config = config('verifysource.credibility');
    }

    /**
     * Assess the credibility of a source or article
     */
    public function assessCredibility(string $type, int $id, array $context = []): array
    {
        $cacheKey = "credibility:{$type}:{$id}:".hash('md5', serialize($context));

        // Check cache first (24 hours)
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
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

        try {
            if ($type === 'source') {
                $assessment = $this->assessSourceCredibility($id, $context);
            } elseif ($type === 'article') {
                $assessment = $this->assessArticleCredibility($id, $context);
            } else {
                throw new Exception("Unknown assessment type: {$type}");
            }

            // Cache for 24 hours
            Cache::put($cacheKey, $assessment, now()->addDay());

            return $assessment;

        } catch (Exception $e) {
            Log::error('Credibility assessment failed', [
                'type' => $type,
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            $assessment['error'] = $e->getMessage();

            return $assessment;
        }
    }

    /**
     * Assess credibility of a source
     */
    protected function assessSourceCredibility(int $sourceId, array $context): array
    {
        $source = Source::find($sourceId);
        if (! $source) {
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
        if (! $article) {
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
            if (! $domain) {
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
                    $factor['indicators'][] = 'Very new source (< 1 year)';
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
            $factor['indicators'][] = 'Error assessing domain authority: '.$e->getMessage();
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
            'washingtonpost.com', 'theguardian.com', 'wsj.com', 'npr.org',
        ];

        if (in_array($domain, $knownNewsDomains)) {
            $indicators[] = [
                'impact' => 0.4,
                'description' => 'Recognized major news organization',
            ];
        }

        // Check for academic/government domains
        if (preg_match('/\.(edu|gov|org)$/', $domain)) {
            $indicators[] = [
                'impact' => 0.2,
                'description' => 'Educational, government, or non-profit domain',
            ];
        }

        // Check for suspicious TLD patterns
        $suspiciousTlds = ['.tk', '.ml', '.ga', '.cf'];
        foreach ($suspiciousTlds as $tld) {
            if (str_ends_with($domain, $tld)) {
                $indicators[] = [
                    'impact' => -0.3,
                    'description' => 'Suspicious top-level domain',
                ];
                break;
            }
        }

        // Check for URL shorteners (should not be source domains)
        $shorteners = ['bit.ly', 'tinyurl.com', 't.co', 'goo.gl', 'short.link'];
        if (in_array($domain, $shorteners)) {
            $indicators[] = [
                'impact' => -0.5,
                'description' => 'URL shortener used as source domain',
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
            '/one weird trick/i',
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
            if (! empty($article->$field)) {
                $completedFields++;
            }
        }

        $completionRatio = $completedFields / count($metadataFields);
        $factor['score'] = $completionRatio;
        $factor['indicators'][] = "Metadata completion: {$completedFields}/".count($metadataFields).' fields';

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
            'data_available' => ! empty($context),
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
        if (isset($context['suspicious_patterns']) && ! empty($context['suspicious_patterns'])) {
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
        $variance = array_sum(array_map(fn ($x) => pow($x - $mean, 2), $values)) / count($values);

        return sqrt($variance);
    }
}
