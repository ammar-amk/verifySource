<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class NewsApiService
{
    private array $config;
    
    public function __construct()
    {
        $this->config = config('external_apis.news_apis');
    }

    /**
     * Search NewsAPI.org for articles
     */
    public function searchNewsApi(string $query, array $options = []): array
    {
        if (!config('external_apis.features.news_apis')) {
            return [
                'success' => false,
                'message' => 'News APIs are disabled',
                'articles' => [],
            ];
        }

        $newsApiConfig = $this->config['newsapi'];
        
        if (empty($newsApiConfig['api_key'])) {
            return [
                'success' => false,
                'message' => 'NewsAPI key not configured',
                'articles' => [],
            ];
        }

        $cacheKey = "newsapi_" . md5($query . serialize($options));
        
        if (config('external_apis.global.enable_caching')) {
            $cached = Cache::get($cacheKey);
            if ($cached) {
                return $cached;
            }
        }

        try {
            $this->enforceRateLimit('newsapi');

            $params = array_merge([
                'q' => $query,
                'apiKey' => $newsApiConfig['api_key'],
                'language' => 'en',
                'sortBy' => 'relevancy',
                'pageSize' => 20,
            ], $options);

            $response = Http::timeout($newsApiConfig['timeout'])
                ->retry(config('external_apis.global.max_retries'), config('external_apis.global.retry_delay') * 1000)
                ->get($newsApiConfig['base_url'] . '/everything', $params);

            if (!$response->successful()) {
                throw new Exception("NewsAPI request failed: " . $response->status());
            }

            $data = $response->json();
            $result = $this->parseNewsApiResponse($data, $query);
            
            if (config('external_apis.global.enable_caching')) {
                Cache::put($cacheKey, $result, config('external_apis.global.cache_duration'));
            }

            return $result;

        } catch (Exception $e) {
            Log::warning('NewsAPI search failed', [
                'query' => $query,
                'options' => $options,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'query' => $query,
                'articles' => [],
            ];
        }
    }

    /**
     * Search Guardian API for articles
     */
    public function searchGuardianApi(string $query, array $options = []): array
    {
        if (!config('external_apis.features.news_apis')) {
            return [
                'success' => false,
                'message' => 'News APIs are disabled',
                'articles' => [],
            ];
        }

        $guardianConfig = $this->config['guardian'];
        
        if (empty($guardianConfig['api_key'])) {
            return [
                'success' => false,
                'message' => 'Guardian API key not configured',
                'articles' => [],
            ];
        }

        $cacheKey = "guardian_" . md5($query . serialize($options));
        
        if (config('external_apis.global.enable_caching')) {
            $cached = Cache::get($cacheKey);
            if ($cached) {
                return $cached;
            }
        }

        try {
            $this->enforceRateLimit('guardian');

            $params = array_merge([
                'q' => $query,
                'api-key' => $guardianConfig['api_key'],
                'show-fields' => 'headline,byline,firstPublicationDate,body,thumbnail,short-url',
                'show-tags' => 'keyword',
                'order-by' => 'relevance',
                'page-size' => 20,
            ], $options);

            $response = Http::timeout($guardianConfig['timeout'])
                ->retry(config('external_apis.global.max_retries'), config('external_apis.global.retry_delay') * 1000)
                ->get($guardianConfig['base_url'] . '/search', $params);

            if (!$response->successful()) {
                throw new Exception("Guardian API request failed: " . $response->status());
            }

            $data = $response->json();
            $result = $this->parseGuardianApiResponse($data, $query);
            
            if (config('external_apis.global.enable_caching')) {
                Cache::put($cacheKey, $result, config('external_apis.global.cache_duration'));
            }

            return $result;

        } catch (Exception $e) {
            Log::warning('Guardian API search failed', [
                'query' => $query,
                'options' => $options,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'query' => $query,
                'articles' => [],
            ];
        }
    }

    /**
     * Cross-reference content with multiple news sources
     */
    public function crossReferenceContent(string $title, ?string $content = null, array $options = []): array
    {
        $results = [
            'title' => $title,
            'sources' => [],
            'total_matches' => 0,
            'confidence_score' => 0,
            'earliest_publication' => null,
            'latest_publication' => null,
        ];

        // Search NewsAPI
        $newsApiResults = $this->searchNewsApi($title, $options);
        if ($newsApiResults['success']) {
            $results['sources']['newsapi'] = $newsApiResults;
            $results['total_matches'] += count($newsApiResults['articles']);
        }

        // Search Guardian API
        $guardianResults = $this->searchGuardianApi($title, $options);
        if ($guardianResults['success']) {
            $results['sources']['guardian'] = $guardianResults;
            $results['total_matches'] += count($guardianResults['articles']);
        }

        // Analyze publication dates
        $this->analyzePublicationDates($results);
        
        // Calculate confidence score
        $results['confidence_score'] = $this->calculateCrossReferenceConfidence($results, $content);

        return $results;
    }

    /**
     * Verify article authenticity by checking multiple sources
     */
    public function verifyArticleAuthenticity(string $title, string $url, ?string $content = null): array
    {
        $verification = [
            'url' => $url,
            'title' => $title,
            'verified' => false,
            'confidence' => 0,
            'sources_found' => 0,
            'identical_matches' => 0,
            'similar_matches' => 0,
            'evidence' => [],
        ];

        // Extract domain from URL for analysis
        $domain = parse_url($url, PHP_URL_HOST);
        
        // Search for the exact title
        $crossReference = $this->crossReferenceContent($title);
        
        if ($crossReference['total_matches'] > 0) {
            $verification['sources_found'] = $crossReference['total_matches'];
            
            // Analyze matches
            $this->analyzeMatches($verification, $crossReference, $domain, $content);
        }

        // Calculate overall confidence
        $verification['confidence'] = $this->calculateVerificationConfidence($verification);
        $verification['verified'] = $verification['confidence'] > 0.7;

        return $verification;
    }

    /**
     * Parse NewsAPI response
     */
    private function parseNewsApiResponse(array $data, string $query): array
    {
        if ($data['status'] !== 'ok' || empty($data['articles'])) {
            return [
                'success' => true,
                'query' => $query,
                'articles' => [],
                'total_results' => 0,
            ];
        }

        $articles = [];
        
        foreach ($data['articles'] as $article) {
            $articles[] = [
                'title' => $article['title'] ?? 'No title',
                'description' => $article['description'] ?? null,
                'content' => $article['content'] ?? null,
                'url' => $article['url'] ?? null,
                'url_to_image' => $article['urlToImage'] ?? null,
                'published_at' => $article['publishedAt'] ? 
                    Carbon::parse($article['publishedAt'])->toDateTimeString() : null,
                'source' => [
                    'name' => $article['source']['name'] ?? 'Unknown',
                    'id' => $article['source']['id'] ?? null,
                ],
                'author' => $article['author'] ?? null,
                'api_source' => 'newsapi',
            ];
        }

        return [
            'success' => true,
            'query' => $query,
            'articles' => $articles,
            'total_results' => $data['totalResults'] ?? count($articles),
        ];
    }

    /**
     * Parse Guardian API response
     */
    private function parseGuardianApiResponse(array $data, string $query): array
    {
        if ($data['response']['status'] !== 'ok' || empty($data['response']['results'])) {
            return [
                'success' => true,
                'query' => $query,
                'articles' => [],
                'total_results' => 0,
            ];
        }

        $articles = [];
        
        foreach ($data['response']['results'] as $result) {
            $fields = $result['fields'] ?? [];
            
            $articles[] = [
                'title' => $fields['headline'] ?? $result['webTitle'] ?? 'No title',
                'description' => null, // Guardian doesn't provide description in this format
                'content' => $fields['body'] ?? null,
                'url' => $result['webUrl'] ?? null,
                'url_to_image' => $fields['thumbnail'] ?? null,
                'published_at' => isset($fields['firstPublicationDate']) ? 
                    Carbon::parse($fields['firstPublicationDate'])->toDateTimeString() : null,
                'source' => [
                    'name' => 'The Guardian',
                    'id' => 'guardian',
                ],
                'author' => $fields['byline'] ?? null,
                'section' => $result['sectionName'] ?? null,
                'tags' => $result['tags'] ?? [],
                'api_source' => 'guardian',
            ];
        }

        return [
            'success' => true,
            'query' => $query,
            'articles' => $articles,
            'total_results' => $data['response']['total'] ?? count($articles),
        ];
    }

    /**
     * Analyze publication dates across sources
     */
    private function analyzePublicationDates(array &$results): void
    {
        $dates = [];
        
        foreach ($results['sources'] as $source) {
            if (!isset($source['articles'])) continue;
            
            foreach ($source['articles'] as $article) {
                if (!empty($article['published_at'])) {
                    $dates[] = Carbon::parse($article['published_at']);
                }
            }
        }

        if (!empty($dates)) {
            $results['earliest_publication'] = min($dates)->toDateTimeString();
            $results['latest_publication'] = max($dates)->toDateTimeString();
        }
    }

    /**
     * Calculate cross-reference confidence score
     */
    private function calculateCrossReferenceConfidence(array $results, ?string $content): float
    {
        $score = 0;
        
        // Base score on number of sources
        $sourceCount = count($results['sources']);
        $score += min(50, $sourceCount * 10);
        
        // Add score for total matches
        $score += min(30, $results['total_matches'] * 3);
        
        // Bonus for multiple different APIs finding results
        if ($sourceCount > 1 && $results['total_matches'] > 1) {
            $score += 20;
        }

        // Bonus for date consistency (if dates are close together)
        if ($results['earliest_publication'] && $results['latest_publication']) {
            $earliestDate = Carbon::parse($results['earliest_publication']);
            $latestDate = Carbon::parse($results['latest_publication']);
            $daysDiff = $earliestDate->diffInDays($latestDate);
            
            if ($daysDiff <= 1) {
                $score += 15; // Very consistent timing
            } elseif ($daysDiff <= 7) {
                $score += 10; // Reasonably consistent
            }
        }

        return min(100, $score) / 100;
    }

    /**
     * Analyze matches for verification
     */
    private function analyzeMatches(array &$verification, array $crossReference, string $domain, ?string $content): void
    {
        foreach ($crossReference['sources'] as $sourceType => $sourceData) {
            if (!isset($sourceData['articles'])) continue;
            
            foreach ($sourceData['articles'] as $article) {
                $articleDomain = $article['url'] ? parse_url($article['url'], PHP_URL_HOST) : null;
                
                // Check for identical domain (same source)
                if ($articleDomain === $domain) {
                    $verification['identical_matches']++;
                    $verification['evidence'][] = "Found identical article on same domain: {$article['url']}";
                } else {
                    // Check for similar content
                    $similarity = $this->calculateContentSimilarity($verification['title'], $article['title'], $content, $article['content']);
                    
                    if ($similarity > 0.8) {
                        $verification['similar_matches']++;
                        $verification['evidence'][] = "Found similar article on {$articleDomain}: {$article['title']}";
                    }
                }
            }
        }
    }

    /**
     * Calculate content similarity between two articles
     */
    private function calculateContentSimilarity(string $title1, string $title2, ?string $content1, ?string $content2): float
    {
        // Simple title similarity using Levenshtein distance
        $titleSimilarity = 1 - (levenshtein(strtolower($title1), strtolower($title2)) / max(strlen($title1), strlen($title2)));
        
        // Content similarity (if both available)
        $contentSimilarity = 0;
        if ($content1 && $content2) {
            // Simple word overlap calculation
            $words1 = array_unique(str_word_count(strtolower($content1), 1));
            $words2 = array_unique(str_word_count(strtolower($content2), 1));
            
            $intersection = count(array_intersect($words1, $words2));
            $union = count(array_unique(array_merge($words1, $words2)));
            
            $contentSimilarity = $union > 0 ? $intersection / $union : 0;
        }

        // Weight title more heavily if no content available
        return $content1 && $content2 ? 
            ($titleSimilarity * 0.4 + $contentSimilarity * 0.6) : 
            $titleSimilarity;
    }

    /**
     * Calculate verification confidence
     */
    private function calculateVerificationConfidence(array $verification): float
    {
        $score = 0;
        
        // Sources found
        $score += min(30, $verification['sources_found'] * 5);
        
        // Identical matches (same domain republishing)
        $score += min(20, $verification['identical_matches'] * 10);
        
        // Similar matches (cross-publication)
        $score += min(50, $verification['similar_matches'] * 15);
        
        return min(100, $score) / 100;
    }

    /**
     * Enforce rate limiting
     */
    private function enforceRateLimit(string $apiName): void
    {
        if (!config('external_apis.global.enable_rate_limiting')) {
            return;
        }

        $now = time();
        $cacheKey = "news_rate_limit_{$apiName}";
        $requests = Cache::get($cacheKey, []);
        
        // Clean old requests
        $requests = array_filter($requests, function($timestamp) use ($now) {
            return $now - $timestamp < 86400; // 24 hours
        });

        // Check daily limit
        $dailyLimit = $this->getDailyLimit($apiName);
        
        if (count($requests) >= $dailyLimit) {
            throw new Exception("Daily rate limit exceeded for {$apiName}");
        }

        // Add current request
        $requests[] = $now;
        Cache::put($cacheKey, $requests, 86400); // Cache for 24 hours
    }

    /**
     * Get daily limit for API
     */
    private function getDailyLimit(string $apiName): int
    {
        $limits = [
            'newsapi' => $this->config['newsapi']['rate_limit']['requests_per_day'] ?? 500,
            'guardian' => $this->config['guardian']['rate_limit']['requests_per_day'] ?? 5000,
        ];

        return $limits[$apiName] ?? 1000;
    }

    /**
     * Health check for news APIs
     */
    public function healthCheck(): array
    {
        $status = [
            'newsapi' => ['status' => 'unknown', 'error' => null],
            'guardian' => ['status' => 'unknown', 'error' => null],
        ];

        // Check NewsAPI
        try {
            if (!empty($this->config['newsapi']['api_key'])) {
                $response = Http::timeout(10)->get($this->config['newsapi']['base_url'] . '/everything', [
                    'q' => 'test',
                    'apiKey' => $this->config['newsapi']['api_key'],
                    'pageSize' => 1,
                ]);
                $status['newsapi']['status'] = $response->successful() ? 'healthy' : 'unhealthy';
            } else {
                $status['newsapi']['status'] = 'not_configured';
            }
        } catch (Exception $e) {
            $status['newsapi']['status'] = 'unhealthy';
            $status['newsapi']['error'] = $e->getMessage();
        }

        // Check Guardian API
        try {
            if (!empty($this->config['guardian']['api_key'])) {
                $response = Http::timeout(10)->get($this->config['guardian']['base_url'] . '/search', [
                    'q' => 'test',
                    'api-key' => $this->config['guardian']['api_key'],
                    'page-size' => 1,
                ]);
                $status['guardian']['status'] = $response->successful() ? 'healthy' : 'unhealthy';
            } else {
                $status['guardian']['status'] = 'not_configured';
            }
        } catch (Exception $e) {
            $status['guardian']['status'] = 'unhealthy';
            $status['guardian']['error'] = $e->getMessage();
        }

        return $status;
    }

    /**
     * Alias method for crossReferenceContent (for backwards compatibility)
     */
    public function crossReference(string $title, ?string $url = null): array
    {
        return $this->crossReferenceContent($title, null, [
            'source_url' => $url
        ]);
    }
}