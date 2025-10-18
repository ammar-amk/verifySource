<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Exception;

class FactCheckApiService
{
    private array $config;
    private int $requestCount = 0;
    private int $lastRequestTime = 0;
    
    public function __construct()
    {
        $this->config = config('external_apis.fact_check_apis');
    }

    /**
     * Search for fact-checks using Google Fact Check Tools API
     */
    public function searchGoogleFactCheck(string $query, string $languageCode = 'en'): array
    {
        if (!config('external_apis.features.fact_check_apis')) {
            return [
                'success' => false,
                'message' => 'Fact-check APIs are disabled',
                'results' => [],
            ];
        }

        $googleConfig = $this->config['google_factcheck'];
        
        if (empty($googleConfig['api_key'])) {
            return [
                'success' => false,
                'message' => 'Google Fact Check API key not configured',
                'results' => [],
            ];
        }

        $cacheKey = "google_factcheck_" . md5($query . $languageCode);
        
        if (config('external_apis.global.enable_caching')) {
            $cached = Cache::get($cacheKey);
            if ($cached) {
                return $cached;
            }
        }

        try {
            $this->enforceRateLimit('google_factcheck');

            $response = Http::timeout($googleConfig['timeout'])
                ->retry(config('external_apis.global.max_retries'), config('external_apis.global.retry_delay') * 1000)
                ->get($googleConfig['base_url'], [
                    'key' => $googleConfig['api_key'],
                    'query' => $query,
                    'languageCode' => $languageCode,
                    'pageSize' => 10,
                ]);

            if (!$response->successful()) {
                throw new Exception("Google Fact Check API request failed: " . $response->status());
            }

            $data = $response->json();
            $result = $this->parseGoogleFactCheckResponse($data, $query);
            
            if (config('external_apis.global.enable_caching')) {
                Cache::put($cacheKey, $result, config('external_apis.global.cache_duration'));
            }

            return $result;

        } catch (Exception $e) {
            Log::warning('Google Fact Check API search failed', [
                'query' => $query,
                'language' => $languageCode,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'query' => $query,
                'results' => [],
            ];
        }
    }

    /**
     * Search for fact-checks on FactCheck.org (simplified scraping approach)
     */
    public function searchFactCheckOrg(string $query): array
    {
        if (!config('external_apis.features.fact_check_apis')) {
            return [
                'success' => false,
                'message' => 'Fact-check APIs are disabled',
                'results' => [],
            ];
        }

        $cacheKey = "factcheck_org_" . md5($query);
        
        if (config('external_apis.global.enable_caching')) {
            $cached = Cache::get($cacheKey);
            if ($cached) {
                return $cached;
            }
        }

        try {
            $this->enforceRateLimit('factcheck_org');

            // Use search endpoint with query
            $searchUrl = 'https://www.factcheck.org/search/';
            
            $response = Http::timeout($this->config['factcheck_org']['timeout'])
                ->withUserAgent(config('external_apis.global.user_agent'))
                ->retry(config('external_apis.global.max_retries'), config('external_apis.global.retry_delay') * 1000)
                ->get($searchUrl, [
                    's' => $query,
                ]);

            if (!$response->successful()) {
                throw new Exception("FactCheck.org request failed: " . $response->status());
            }

            $result = $this->parseFactCheckOrgResponse($response->body(), $query);
            
            if (config('external_apis.global.enable_caching')) {
                Cache::put($cacheKey, $result, config('external_apis.global.cache_duration'));
            }

            return $result;

        } catch (Exception $e) {
            Log::warning('FactCheck.org search failed', [
                'query' => $query,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'query' => $query,
                'results' => [],
            ];
        }
    }

    /**
     * Get comprehensive fact-check analysis for a claim
     */
    public function analyzeClaimForFactChecks(string $claim): array
    {
        $results = [
            'claim' => $claim,
            'sources' => [],
            'overall_assessment' => null,
            'confidence_score' => 0,
            'fact_checks_found' => 0,
        ];

        // Search Google Fact Check API
        $googleResults = $this->searchGoogleFactCheck($claim);
        if ($googleResults['success'] && !empty($googleResults['results'])) {
            $results['sources']['google_factcheck'] = $googleResults;
            $results['fact_checks_found'] += count($googleResults['results']);
        }

        // Search FactCheck.org
        $factCheckOrgResults = $this->searchFactCheckOrg($claim);
        if ($factCheckOrgResults['success'] && !empty($factCheckOrgResults['results'])) {
            $results['sources']['factcheck_org'] = $factCheckOrgResults;
            $results['fact_checks_found'] += count($factCheckOrgResults['results']);
        }

        // Analyze overall assessment
        if ($results['fact_checks_found'] > 0) {
            $results['overall_assessment'] = $this->calculateOverallAssessment($results['sources']);
            $results['confidence_score'] = $this->calculateConfidenceScore($results['sources'], $results['fact_checks_found']);
        }

        return $results;
    }

    /**
     * Parse Google Fact Check API response
     */
    private function parseGoogleFactCheckResponse(array $data, string $query): array
    {
        if (!isset($data['claims']) || empty($data['claims'])) {
            return [
                'success' => true,
                'query' => $query,
                'results' => [],
                'total_results' => 0,
            ];
        }

        $results = [];
        
        foreach ($data['claims'] as $claim) {
            $claimReviews = $claim['claimReview'] ?? [];
            
            foreach ($claimReviews as $review) {
                $results[] = [
                    'claim_text' => $claim['text'] ?? 'Unknown claim',
                    'claimant' => $claim['claimant'] ?? 'Unknown',
                    'claim_date' => $claim['claimDate'] ?? null,
                    'reviewer' => $review['publisher']['name'] ?? 'Unknown publisher',
                    'reviewer_site' => $review['publisher']['site'] ?? null,
                    'review_date' => $review['reviewDate'] ?? null,
                    'title' => $review['title'] ?? 'No title',
                    'url' => $review['url'] ?? null,
                    'rating' => $review['textualRating'] ?? 'No rating',
                    'language' => $review['languageCode'] ?? 'en',
                ];
            }
        }

        return [
            'success' => true,
            'query' => $query,
            'results' => $results,
            'total_results' => count($results),
        ];
    }

    /**
     * Parse FactCheck.org response (basic HTML parsing)
     */
    private function parseFactCheckOrgResponse(string $html, string $query): array
    {
        $results = [];
        
        // Simple regex to extract fact-check articles from search results
        // This is a basic implementation - in production, use a proper HTML parser
        preg_match_all('/<article[^>]*class="[^"]*search-result[^"]*"[^>]*>.*?<\/article>/s', $html, $articles);
        
        foreach ($articles[0] ?? [] as $articleHtml) {
            // Extract title
            preg_match('/<h3[^>]*>.*?<a[^>]*href="([^"]*)"[^>]*>(.*?)<\/a>.*?<\/h3>/s', $articleHtml, $titleMatches);
            
            // Extract date
            preg_match('/<time[^>]*datetime="([^"]*)"[^>]*>/s', $articleHtml, $dateMatches);
            
            // Extract excerpt
            preg_match('/<div[^>]*class="[^"]*excerpt[^"]*"[^>]*>(.*?)<\/div>/s', $articleHtml, $excerptMatches);
            
            if (!empty($titleMatches)) {
                $results[] = [
                    'title' => strip_tags($titleMatches[2] ?? ''),
                    'url' => $titleMatches[1] ?? '',
                    'date' => $dateMatches[1] ?? null,
                    'excerpt' => strip_tags($excerptMatches[1] ?? ''),
                    'source' => 'FactCheck.org',
                    'rating' => null, // Would need deeper parsing to extract rating
                ];
            }
        }

        return [
            'success' => true,
            'query' => $query,
            'results' => array_slice($results, 0, 10), // Limit to 10 results
            'total_results' => count($results),
        ];
    }

    /**
     * Calculate overall assessment from multiple sources
     */
    private function calculateOverallAssessment(array $sources): array
    {
        $ratings = [];
        $totalReviews = 0;
        
        foreach ($sources as $sourceResults) {
            if (!isset($sourceResults['results'])) continue;
            
            foreach ($sourceResults['results'] as $result) {
                if (!empty($result['rating'])) {
                    $normalizedRating = $this->normalizeRating($result['rating']);
                    if ($normalizedRating !== null) {
                        $ratings[] = $normalizedRating;
                        $totalReviews++;
                    }
                }
            }
        }

        if (empty($ratings)) {
            return [
                'status' => 'insufficient_data',
                'message' => 'No ratings found in fact-check sources',
                'average_score' => null,
                'total_reviews' => 0,
            ];
        }

        $averageScore = array_sum($ratings) / count($ratings);
        
        return [
            'status' => $this->determineOverallStatus($averageScore),
            'message' => $this->generateAssessmentMessage($averageScore, $totalReviews),
            'average_score' => round($averageScore, 2),
            'total_reviews' => $totalReviews,
            'individual_ratings' => $ratings,
        ];
    }

    /**
     * Normalize different rating systems to a 0-100 scale
     */
    private function normalizeRating(string $rating): ?float
    {
        $rating = strtolower(trim($rating));
        
        // True/False ratings
        if (in_array($rating, ['true', 'correct', 'accurate', 'verified'])) {
            return 100;
        }
        if (in_array($rating, ['false', 'incorrect', 'inaccurate', 'debunked', 'fake'])) {
            return 0;
        }
        
        // Partial ratings
        if (in_array($rating, ['mostly true', 'mostly correct', 'largely accurate'])) {
            return 80;
        }
        if (in_array($rating, ['mostly false', 'mostly incorrect', 'largely inaccurate'])) {
            return 20;
        }
        if (in_array($rating, ['half true', 'mixed', 'partially correct'])) {
            return 50;
        }
        
        // Pants on Fire / Four Pinocchios style
        if (in_array($rating, ['pants on fire', 'four pinocchios'])) {
            return 0;
        }
        
        // Numeric ratings (if any)
        if (is_numeric($rating)) {
            return min(100, max(0, floatval($rating)));
        }
        
        // Unknown rating
        return null;
    }

    /**
     * Determine overall status from average score
     */
    private function determineOverallStatus(float $score): string
    {
        if ($score >= 80) return 'likely_true';
        if ($score >= 60) return 'leaning_true';
        if ($score >= 40) return 'mixed';
        if ($score >= 20) return 'leaning_false';
        return 'likely_false';
    }

    /**
     * Generate assessment message
     */
    private function generateAssessmentMessage(float $score, int $totalReviews): string
    {
        $status = $this->determineOverallStatus($score);
        
        $messages = [
            'likely_true' => "Based on {$totalReviews} fact-check reviews, this claim appears to be largely accurate.",
            'leaning_true' => "Based on {$totalReviews} fact-check reviews, this claim appears to be mostly accurate.",
            'mixed' => "Based on {$totalReviews} fact-check reviews, this claim has mixed accuracy.",
            'leaning_false' => "Based on {$totalReviews} fact-check reviews, this claim appears to be mostly inaccurate.",
            'likely_false' => "Based on {$totalReviews} fact-check reviews, this claim appears to be largely inaccurate.",
        ];
        
        return $messages[$status] ?? "Unable to determine accuracy based on available fact-checks.";
    }

    /**
     * Calculate confidence score based on number and consistency of sources
     */
    private function calculateConfidenceScore(array $sources, int $totalFactChecks): float
    {
        $sourceCount = count($sources);
        $consistency = $this->calculateConsistency($sources);
        
        // Base confidence on number of sources and fact-checks
        $baseScore = min(100, ($sourceCount * 20) + ($totalFactChecks * 5));
        
        // Adjust for consistency
        $adjustedScore = $baseScore * $consistency;
        
        return round(min(100, max(0, $adjustedScore)), 1);
    }

    /**
     * Calculate consistency between different fact-checking sources
     */
    private function calculateConsistency(array $sources): float
    {
        $ratings = [];
        
        foreach ($sources as $sourceResults) {
            if (!isset($sourceResults['results'])) continue;
            
            foreach ($sourceResults['results'] as $result) {
                if (!empty($result['rating'])) {
                    $normalizedRating = $this->normalizeRating($result['rating']);
                    if ($normalizedRating !== null) {
                        $ratings[] = $normalizedRating;
                    }
                }
            }
        }

        if (count($ratings) < 2) {
            return 1.0; // Single source, assume consistent
        }

        $variance = $this->calculateVariance($ratings);
        $maxVariance = 2500; // Maximum possible variance (0-100 scale)
        
        return 1.0 - ($variance / $maxVariance);
    }

    /**
     * Calculate variance of ratings
     */
    private function calculateVariance(array $values): float
    {
        $mean = array_sum($values) / count($values);
        $squaredDiffs = array_map(function($value) use ($mean) {
            return pow($value - $mean, 2);
        }, $values);
        
        return array_sum($squaredDiffs) / count($values);
    }

    /**
     * Enforce rate limiting for different APIs
     */
    private function enforceRateLimit(string $apiName): void
    {
        if (!config('external_apis.global.enable_rate_limiting')) {
            return;
        }

        $now = time();
        $cacheKey = "rate_limit_{$apiName}";
        $requests = Cache::get($cacheKey, []);
        
        // Clean old requests (older than 1 hour)
        $requests = array_filter($requests, function($timestamp) use ($now) {
            return $now - $timestamp < 3600;
        });

        // Check current minute
        $currentMinute = floor($now / 60);
        $requestsThisMinute = array_filter($requests, function($timestamp) use ($currentMinute) {
            return floor($timestamp / 60) === $currentMinute;
        });

        // Get rate limit for this API
        $rateLimit = $this->getRateLimit($apiName);
        
        if (count($requestsThisMinute) >= $rateLimit) {
            $sleepTime = 60 - ($now % 60) + 1;
            sleep($sleepTime);
        }

        // Add current request
        $requests[] = $now;
        Cache::put($cacheKey, $requests, 3600); // Cache for 1 hour
    }

    /**
     * Get rate limit for specific API
     */
    private function getRateLimit(string $apiName): int
    {
        $limits = [
            'google_factcheck' => $this->config['google_factcheck']['rate_limit']['requests_per_day'] ?? 1000,
            'factcheck_org' => $this->config['factcheck_org']['rate_limit']['requests_per_minute'] ?? 30,
        ];

        return $limits[$apiName] ?? 60; // Default limit
    }

    /**
     * Health check for fact-checking APIs
     */
    public function healthCheck(): array
    {
        $status = [
            'google_factcheck' => ['status' => 'unknown', 'error' => null],
            'factcheck_org' => ['status' => 'unknown', 'error' => null],
        ];

        // Check Google Fact Check API
        try {
            if (!empty($this->config['google_factcheck']['api_key'])) {
                $response = Http::timeout(10)->get($this->config['google_factcheck']['base_url'], [
                    'key' => $this->config['google_factcheck']['api_key'],
                    'query' => 'test',
                    'pageSize' => 1,
                ]);
                $status['google_factcheck']['status'] = $response->successful() ? 'healthy' : 'unhealthy';
            } else {
                $status['google_factcheck']['status'] = 'not_configured';
            }
        } catch (Exception $e) {
            $status['google_factcheck']['status'] = 'unhealthy';
            $status['google_factcheck']['error'] = $e->getMessage();
        }

        // Check FactCheck.org
        try {
            $response = Http::timeout(10)->get('https://www.factcheck.org');
            $status['factcheck_org']['status'] = $response->successful() ? 'healthy' : 'unhealthy';
        } catch (Exception $e) {
            $status['factcheck_org']['status'] = 'unhealthy';
            $status['factcheck_org']['error'] = $e->getMessage();
        }

        return $status;
    }

    /**
     * Verify content using available fact-checking sources (alias method)
     */
    public function verifyContent(string $content): array
    {
        return $this->analyzeClaimForFactChecks($content);
    }
}