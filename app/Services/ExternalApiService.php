<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Exception;

class ExternalApiService
{
    private WaybackMachineService $waybackService;
    private FactCheckApiService $factCheckService;
    private NewsApiService $newsApiService;
    private UrlValidationService $urlValidationService;
    
    public function __construct(
        WaybackMachineService $waybackService,
        FactCheckApiService $factCheckService,
        NewsApiService $newsApiService,
        UrlValidationService $urlValidationService
    ) {
        $this->waybackService = $waybackService;
        $this->factCheckService = $factCheckService;
        $this->newsApiService = $newsApiService;
        $this->urlValidationService = $urlValidationService;
    }

    /**
     * Comprehensive external verification for content and URLs
     */
    public function performComprehensiveVerification(array $data): array
    {
        $verification = [
            'url' => $data['url'] ?? null,
            'title' => $data['title'] ?? null,
            'content' => $data['content'] ?? null,
            'timestamp' => now()->toISOString(),
            'external_checks' => [],
            'overall_assessment' => [],
            'confidence_score' => 0,
            'warnings' => [],
            'errors' => [],
        ];

        try {
            // 1. URL Validation and Reputation Check
            if ($verification['url']) {
                $verification['external_checks']['url_validation'] = $this->performUrlValidation($verification['url']);
            }

            // 2. Historical Verification with Wayback Machine
            if ($verification['url']) {
                $verification['external_checks']['wayback_machine'] = $this->performWaybackVerification($verification['url']);
            }

            // 3. Fact-Check API Verification
            if ($verification['title'] || $verification['content']) {
                $verification['external_checks']['fact_checking'] = $this->performFactCheckVerification($data);
            }

            // 4. News Cross-Reference Verification
            if ($verification['title']) {
                $verification['external_checks']['news_cross_reference'] = $this->performNewsCrossReference($data);
            }

            // 5. Calculate Overall Assessment
            $verification['overall_assessment'] = $this->calculateOverallAssessment($verification['external_checks']);
            $verification['confidence_score'] = $this->calculateConfidenceScore($verification['external_checks']);
            
            // 6. Collect Warnings and Recommendations
            $this->collectWarningsAndRecommendations($verification);

        } catch (Exception $e) {
            Log::error('Comprehensive external verification failed', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            
            $verification['errors'][] = 'External verification process failed: ' . $e->getMessage();
        }

        return $verification;
    }

    /**
     * Quick verification for real-time checks
     */
    public function performQuickVerification(string $url, ?string $title = null): array
    {
        $verification = [
            'url' => $url,
            'title' => $title,
            'timestamp' => now()->toISOString(),
            'quick_checks' => [],
            'trust_score' => 0,
            'safe' => false,
            'warnings' => [],
        ];

        try {
            // URL validation and basic checks
            $urlCheck = $this->urlValidationService->validateUrl($url);
            $verification['quick_checks']['url_validation'] = $urlCheck;

            // Trust source check
            $trustCheck = $this->urlValidationService->isTrustedNewsSource($url);
            $verification['quick_checks']['source_trust'] = $trustCheck;

            // Suspicious pattern detection
            $suspiciousCheck = $this->urlValidationService->detectSuspiciousPatterns($url);
            $verification['quick_checks']['suspicious_patterns'] = $suspiciousCheck;

            // Quick Wayback availability check
            if (config('external_apis.features.wayback_machine')) {
                $waybackCheck = $this->waybackService->checkAvailability($url);
                $verification['quick_checks']['wayback_availability'] = $waybackCheck;
            }

            // Calculate quick scores
            $verification['trust_score'] = $this->calculateQuickTrustScore($verification['quick_checks']);
            $verification['safe'] = $urlCheck['safe'] && !$suspiciousCheck['suspicious'];
            
            // Collect quick warnings
            $this->collectQuickWarnings($verification);

        } catch (Exception $e) {
            Log::error('Quick verification failed', [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            
            $verification['warnings'][] = 'Quick verification failed: ' . $e->getMessage();
        }

        return $verification;
    }

    /**
     * Verify timestamp and historical accuracy
     */
    public function verifyTimestamp(string $url, string $claimedDate): array
    {
        try {
            $waybackVerification = $this->waybackService->verifyTimestamp($url, $claimedDate);
            
            return [
                'url' => $url,
                'claimed_date' => $claimedDate,
                'verification' => $waybackVerification,
                'accurate' => $waybackVerification['timestamp_accurate'] ?? false,
                'confidence' => $waybackVerification['confidence'] ?? 0,
            ];
        } catch (Exception $e) {
            Log::error('Timestamp verification failed', [
                'url' => $url,
                'claimed_date' => $claimedDate,
                'error' => $e->getMessage()
            ]);
            
            return [
                'url' => $url,
                'claimed_date' => $claimedDate,
                'accurate' => false,
                'confidence' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get enhanced metadata for content
     */
    public function getEnhancedMetadata(array $data): array
    {
        $metadata = [
            'url' => $data['url'] ?? null,
            'enhanced_data' => [],
            'external_sources' => [],
            'credibility_indicators' => [],
        ];

        try {
            $url = $metadata['url'];
            
            if ($url) {
                // URL and domain analysis
                $urlAnalysis = $this->urlValidationService->validateUrl($url);
                $trustAnalysis = $this->urlValidationService->isTrustedNewsSource($url);
                
                $metadata['enhanced_data']['domain_analysis'] = [
                    'reputation_score' => $urlAnalysis['reputation_score'],
                    'trusted_source' => $trustAnalysis['trusted'],
                    'source_category' => $trustAnalysis['category'],
                    'trust_confidence' => $trustAnalysis['confidence'],
                ];

                // Historical presence
                if (config('external_apis.features.wayback_machine')) {
                    $waybackData = $this->waybackService->getSnapshotSummary($url);
                    $metadata['enhanced_data']['historical_presence'] = $waybackData;
                }

                // Cross-reference with news APIs
                if (isset($data['title']) && config('external_apis.features.news_apis')) {
                    $newsData = $this->newsApiService->crossReference($data['title'], $url);
                    $metadata['external_sources']['news_references'] = $newsData;
                }
            }

            // Content fact-checking (if content provided)
            if (isset($data['content']) && config('external_apis.features.fact_checking')) {
                $factCheckData = $this->factCheckService->verifyContent($data['content']);
                $metadata['external_sources']['fact_checks'] = $factCheckData;
            }

            // Generate credibility indicators
            $metadata['credibility_indicators'] = $this->generateCredibilityIndicators($metadata);

        } catch (Exception $e) {
            Log::error('Enhanced metadata retrieval failed', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
        }

        return $metadata;
    }

    /**
     * Perform URL validation
     */
    private function performUrlValidation(string $url): array
    {
        try {
            return $this->urlValidationService->validateUrl($url);
        } catch (Exception $e) {
            return [
                'valid' => false,
                'safe' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Perform Wayback Machine verification
     */
    private function performWaybackVerification(string $url): array
    {
        try {
            if (!config('external_apis.features.wayback_machine')) {
                return ['enabled' => false, 'message' => 'Wayback Machine integration disabled'];
            }

            $availability = $this->waybackService->checkAvailability($url);
            $snapshots = [];
            
            if ($availability['available']) {
                $snapshots = $this->waybackService->getSnapshots($url, 5); // Get last 5 snapshots
            }

            return [
                'enabled' => true,
                'availability' => $availability,
                'snapshots' => $snapshots,
                'historical_verification' => $availability['available'],
            ];
        } catch (Exception $e) {
            return [
                'enabled' => true,
                'error' => $e->getMessage(),
                'historical_verification' => false,
            ];
        }
    }

    /**
     * Perform fact-check verification
     */
    private function performFactCheckVerification(array $data): array
    {
        try {
            if (!config('external_apis.features.fact_checking')) {
                return ['enabled' => false, 'message' => 'Fact-checking APIs disabled'];
            }

            $query = $data['title'] ?? $data['content'] ?? '';
            if (empty($query)) {
                return ['enabled' => true, 'message' => 'No content to fact-check'];
            }

            return $this->factCheckService->verifyContent($query);
        } catch (Exception $e) {
            return [
                'enabled' => true,
                'error' => $e->getMessage(),
                'verification_status' => 'error',
            ];
        }
    }

    /**
     * Perform news cross-reference verification
     */
    private function performNewsCrossReference(array $data): array
    {
        try {
            if (!config('external_apis.features.news_apis')) {
                return ['enabled' => false, 'message' => 'News APIs disabled'];
            }

            $title = $data['title'] ?? '';
            $url = $data['url'] ?? '';
            
            if (empty($title)) {
                return ['enabled' => true, 'message' => 'No title provided for cross-reference'];
            }

            return $this->newsApiService->crossReference($title, $url);
        } catch (Exception $e) {
            return [
                'enabled' => true,
                'error' => $e->getMessage(),
                'cross_reference_status' => 'error',
            ];
        }
    }

    /**
     * Calculate overall assessment
     */
    private function calculateOverallAssessment(array $checks): array
    {
        $assessment = [
            'credibility' => 'unknown',
            'authenticity' => 'unknown',
            'trustworthiness' => 'unknown',
            'historical_accuracy' => 'unknown',
            'factors' => [],
        ];

        // URL validation factors
        if (isset($checks['url_validation'])) {
            $urlCheck = $checks['url_validation'];
            if ($urlCheck['safe'] && $urlCheck['reputation_score'] > 70) {
                $assessment['trustworthiness'] = 'high';
                $assessment['factors'][] = 'URL from reputable source';
            } elseif (!$urlCheck['safe']) {
                $assessment['trustworthiness'] = 'low';
                $assessment['factors'][] = 'URL safety concerns detected';
            }
        }

        // Wayback verification factors
        if (isset($checks['wayback_machine']['availability']['available'])) {
            if ($checks['wayback_machine']['availability']['available']) {
                $assessment['historical_accuracy'] = 'verifiable';
                $assessment['factors'][] = 'Content has historical archive presence';
            }
        }

        // Fact-checking factors
        if (isset($checks['fact_checking']['overall_rating'])) {
            $rating = $checks['fact_checking']['overall_rating'];
            if ($rating > 80) {
                $assessment['authenticity'] = 'high';
                $assessment['factors'][] = 'High fact-check verification score';
            } elseif ($rating < 40) {
                $assessment['authenticity'] = 'low';
                $assessment['factors'][] = 'Low fact-check verification score';
            } else {
                $assessment['authenticity'] = 'moderate';
            }
        }

        // News cross-reference factors
        if (isset($checks['news_cross_reference']['verification']['authenticity_score'])) {
            $authScore = $checks['news_cross_reference']['verification']['authenticity_score'];
            if ($authScore > 0.8) {
                $assessment['credibility'] = 'high';
                $assessment['factors'][] = 'High cross-reference verification';
            } elseif ($authScore < 0.4) {
                $assessment['credibility'] = 'low';
                $assessment['factors'][] = 'Low cross-reference verification';
            } else {
                $assessment['credibility'] = 'moderate';
            }
        }

        return $assessment;
    }

    /**
     * Calculate overall confidence score
     */
    private function calculateConfidenceScore(array $checks): float
    {
        $scores = [];
        $weights = [];

        // URL validation score (weight: 20%)
        if (isset($checks['url_validation']['reputation_score'])) {
            $scores[] = $checks['url_validation']['reputation_score'];
            $weights[] = 0.2;
        }

        // Fact-checking score (weight: 40%)
        if (isset($checks['fact_checking']['overall_rating'])) {
            $scores[] = $checks['fact_checking']['overall_rating'];
            $weights[] = 0.4;
        }

        // News cross-reference score (weight: 30%)
        if (isset($checks['news_cross_reference']['verification']['authenticity_score'])) {
            $crossRefScore = $checks['news_cross_reference']['verification']['authenticity_score'] * 100;
            $scores[] = $crossRefScore;
            $weights[] = 0.3;
        }

        // Wayback verification (weight: 10%)
        if (isset($checks['wayback_machine']['availability']['available'])) {
            $waybackScore = $checks['wayback_machine']['availability']['available'] ? 80 : 50;
            $scores[] = $waybackScore;
            $weights[] = 0.1;
        }

        // Calculate weighted average
        if (empty($scores)) {
            return 0;
        }

        $weightedSum = 0;
        $totalWeight = 0;
        
        for ($i = 0; $i < count($scores); $i++) {
            $weightedSum += $scores[$i] * $weights[$i];
            $totalWeight += $weights[$i];
        }

        return $totalWeight > 0 ? ($weightedSum / $totalWeight) / 100 : 0;
    }

    /**
     * Calculate quick trust score
     */
    private function calculateQuickTrustScore(array $checks): float
    {
        $score = 0;

        if (isset($checks['url_validation']['reputation_score'])) {
            $score += $checks['url_validation']['reputation_score'] * 0.4;
        }

        if (isset($checks['source_trust']['trusted']) && $checks['source_trust']['trusted']) {
            $score += 40;
        }

        if (isset($checks['suspicious_patterns']['suspicious']) && $checks['suspicious_patterns']['suspicious']) {
            $score -= $checks['suspicious_patterns']['risk_level'] * 0.5;
        }

        return max(0, min(100, $score)) / 100;
    }

    /**
     * Collect warnings and recommendations
     */
    private function collectWarningsAndRecommendations(array &$verification): void
    {
        $checks = $verification['external_checks'];

        // URL validation warnings
        if (isset($checks['url_validation']['warnings'])) {
            $verification['warnings'] = array_merge(
                $verification['warnings'],
                $checks['url_validation']['warnings']
            );
        }

        // Fact-checking warnings
        if (isset($checks['fact_checking']['warnings'])) {
            $verification['warnings'] = array_merge(
                $verification['warnings'],
                $checks['fact_checking']['warnings']
            );
        }

        // Cross-reference warnings
        if (isset($checks['news_cross_reference']['warnings'])) {
            $verification['warnings'] = array_merge(
                $verification['warnings'],
                $checks['news_cross_reference']['warnings']
            );
        }

        // Add overall recommendations
        if ($verification['confidence_score'] < 0.3) {
            $verification['warnings'][] = 'Low confidence in content verification - exercise caution';
        }
    }

    /**
     * Collect quick warnings
     */
    private function collectQuickWarnings(array &$verification): void
    {
        $checks = $verification['quick_checks'];

        if (isset($checks['url_validation']['warnings'])) {
            $verification['warnings'] = array_merge(
                $verification['warnings'],
                $checks['url_validation']['warnings']
            );
        }

        if (isset($checks['suspicious_patterns']['warnings'])) {
            $verification['warnings'] = array_merge(
                $verification['warnings'],
                $checks['suspicious_patterns']['warnings']
            );
        }

        if ($verification['trust_score'] < 0.3) {
            $verification['warnings'][] = 'Low trust score for this source';
        }
    }

    /**
     * Generate credibility indicators
     */
    private function generateCredibilityIndicators(array $metadata): array
    {
        $indicators = [
            'positive' => [],
            'negative' => [],
            'neutral' => [],
        ];

        // Domain analysis indicators
        if (isset($metadata['enhanced_data']['domain_analysis'])) {
            $domain = $metadata['enhanced_data']['domain_analysis'];
            
            if ($domain['trusted_source']) {
                $indicators['positive'][] = 'Source is from a trusted news organization';
            }
            
            if ($domain['reputation_score'] > 80) {
                $indicators['positive'][] = 'Domain has excellent reputation';
            } elseif ($domain['reputation_score'] < 40) {
                $indicators['negative'][] = 'Domain has poor reputation';
            }
        }

        // Historical presence indicators
        if (isset($metadata['enhanced_data']['historical_presence'])) {
            $historical = $metadata['enhanced_data']['historical_presence'];
            
            if ($historical['total_snapshots'] > 10) {
                $indicators['positive'][] = 'Content has been archived multiple times';
            }
            
            if (isset($historical['first_snapshot']) && $historical['first_snapshot']) {
                $indicators['positive'][] = 'Content has historical presence in archives';
            }
        }

        // News reference indicators
        if (isset($metadata['external_sources']['news_references'])) {
            $newsRefs = $metadata['external_sources']['news_references'];
            
            if (isset($newsRefs['similar_articles']) && count($newsRefs['similar_articles']) > 3) {
                $indicators['positive'][] = 'Multiple news sources report similar content';
            }
        }

        return $indicators;
    }

    /**
     * Health check for all external API services
     */
    public function healthCheck(): array
    {
        $health = [
            'overall_status' => 'healthy',
            'services' => [],
            'timestamp' => now()->toISOString(),
        ];

        try {
            // Check each service
            $health['services']['wayback_machine'] = $this->waybackService->healthCheck();
            $health['services']['fact_check_api'] = $this->factCheckService->healthCheck();
            $health['services']['news_api'] = $this->newsApiService->healthCheck();
            $health['services']['url_validation'] = $this->urlValidationService->healthCheck();

            // Determine overall status
            $unhealthyServices = collect($health['services'])
                ->where('status', '!=', 'healthy')
                ->count();

            if ($unhealthyServices > 0) {
                $health['overall_status'] = $unhealthyServices >= count($health['services']) / 2 
                    ? 'unhealthy' 
                    : 'degraded';
            }

        } catch (Exception $e) {
            $health['overall_status'] = 'unhealthy';
            $health['error'] = $e->getMessage();
        }

        return $health;
    }
}