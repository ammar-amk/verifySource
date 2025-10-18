<?php

namespace App\Services;

use App\Models\Article;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

class ContentMatchingService
{
    protected MeilisearchService $meilisearch;
    protected QdrantService $qdrant;
    protected array $config;
    
    public function __construct(
        MeilisearchService $meilisearch,
        QdrantService $qdrant
    ) {
        $this->meilisearch = $meilisearch;
        $this->qdrant = $qdrant;
        $this->config = config('verifysource.search');
    }
    
    /**
     * Find matching content using both search engines
     */
    public function findMatches(string $content, array $options = []): array
    {
        $cacheKey = 'content_matches:' . hash('sha256', $content . serialize($options));
        
        // Check cache first
        if ($this->config['options']['cache_duration'] ?? 0 > 0) {
            $cached = Cache::get($cacheKey);
            if ($cached) {
                return $cached;
            }
        }
        
        $results = [
            'query' => $content,
            'search_engine' => $this->config['default_engine'],
            'meilisearch' => null,
            'qdrant' => null,
            'hybrid' => null,
            'matches' => [],
            'duplicate_likelihood' => 0.0,
            'processing_time' => 0,
        ];
        
        $startTime = microtime(true);
        
        try {
            // Perform searches based on configured engine
            switch ($this->config['default_engine']) {
                case 'meilisearch':
                    $results['meilisearch'] = $this->searchWithMeilisearch($content, $options);
                    $results['matches'] = $results['meilisearch']['results'] ?? [];
                    break;
                    
                case 'qdrant':
                    $results['qdrant'] = $this->searchWithQdrant($content, $options);
                    $results['matches'] = $this->formatQdrantResults($results['qdrant']['results'] ?? []);
                    break;
                    
                case 'hybrid':
                default:
                    $results = $this->performHybridSearch($content, $options, $results);
                    break;
            }
            
            // Calculate duplicate likelihood
            $results['duplicate_likelihood'] = $this->calculateDuplicateLikelihood($results['matches']);
            
            // Sort and limit results
            $results['matches'] = $this->rankAndLimitResults($results['matches'], $options);
            
            $results['processing_time'] = round((microtime(true) - $startTime) * 1000, 2);
            
            // Cache results if configured
            if ($this->config['options']['cache_duration'] ?? 0 > 0) {
                Cache::put($cacheKey, $results, now()->addSeconds($this->config['options']['cache_duration']));
            }
            
            return $results;
            
        } catch (Exception $e) {
            Log::error('Content matching failed: ' . $e->getMessage());
            
            $results['error'] = $e->getMessage();
            $results['processing_time'] = round((microtime(true) - $startTime) * 1000, 2);
            
            return $results;
        }
    }
    
    /**
     * Perform hybrid search combining both engines
     */
    protected function performHybridSearch(string $content, array $options, array $results): array
    {
        // Run both searches concurrently if possible
        $meilisearchResults = $this->searchWithMeilisearch($content, $options);
        $qdrantResults = $this->searchWithQdrant($content, $options);
        
        $results['meilisearch'] = $meilisearchResults;
        $results['qdrant'] = $qdrantResults;
        
        // Combine and weight results
        $hybridMatches = $this->combineSearchResults(
            $meilisearchResults['results'] ?? [],
            $qdrantResults['results'] ?? [],
            $options
        );
        
        $results['hybrid'] = [
            'success' => true,
            'combined_results' => count($hybridMatches),
            'meilisearch_weight' => $this->config['options']['hybrid_weights']['meilisearch'],
            'qdrant_weight' => $this->config['options']['hybrid_weights']['qdrant'],
        ];
        
        $results['matches'] = $hybridMatches;
        
        return $results;
    }
    
    /**
     * Search using Meilisearch
     */
    protected function searchWithMeilisearch(string $content, array $options): array
    {
        if (!$this->meilisearch->isAvailable()) {
            return ['success' => false, 'error' => 'Meilisearch not available'];
        }
        
        $searchOptions = array_merge([
            'limit' => $options['limit'] ?? $this->config['options']['default_limit'],
            'attributesToRetrieve' => ['id', 'title', 'url', 'source_id', 'source_name', 'published_at', 'quality_score', 'excerpt'],
            'attributesToHighlight' => ['title', 'content', 'excerpt'],
        ], $options['meilisearch'] ?? []);
        
        return $this->meilisearch->searchArticles($content, $searchOptions);
    }
    
    /**
     * Search using Qdrant
     */
    protected function searchWithQdrant(string $content, array $options): array
    {
        if (!$this->qdrant->isAvailable()) {
            return ['success' => false, 'error' => 'Qdrant not available'];
        }
        
        $searchOptions = array_merge([
            'limit' => $options['limit'] ?? $this->config['options']['default_limit'],
            'score_threshold' => $options['score_threshold'] ?? $this->config['options']['similarity_threshold'],
        ], $options['qdrant'] ?? []);
        
        return $this->qdrant->searchSimilarArticles($content, $searchOptions);
    }
    
    /**
     * Combine results from both search engines
     */
    protected function combineSearchResults(array $meilisearchResults, array $qdrantResults, array $options): array
    {
        $combined = [];
        $meilisearchWeight = $this->config['options']['hybrid_weights']['meilisearch'];
        $qdrantWeight = $this->config['options']['hybrid_weights']['qdrant'];
        
        // Process Meilisearch results
        foreach ($meilisearchResults as $result) {
            $articleId = $result['id'];
            $combined[$articleId] = [
                'id' => $articleId,
                'title' => $result['title'],
                'url' => $result['url'],
                'source_id' => $result['source_id'],
                'source_name' => $result['source_name'],
                'published_at' => $result['published_at'],
                'quality_score' => $result['quality_score'],
                'excerpt' => $result['excerpt'] ?? '',
                'meilisearch_score' => $this->normalizeMeilisearchScore($result),
                'qdrant_score' => 0,
                'hybrid_score' => 0,
                'match_type' => 'text',
                'highlighted' => $result['_formatted'] ?? null,
            ];
        }
        
        // Process Qdrant results and merge
        foreach ($qdrantResults as $result) {
            $articleId = $result['payload']['article_id'];
            $semanticScore = $result['score'] ?? 0;
            
            if (isset($combined[$articleId])) {
                // Article found in both results - update scores
                $combined[$articleId]['qdrant_score'] = $semanticScore;
                $combined[$articleId]['match_type'] = 'hybrid';
            } else {
                // Article only in semantic search
                $combined[$articleId] = [
                    'id' => $articleId,
                    'title' => $result['payload']['title'],
                    'url' => $result['payload']['url'],
                    'source_id' => $result['payload']['source_id'],
                    'source_name' => $result['payload']['source_name'],
                    'published_at' => $result['payload']['published_at'],
                    'quality_score' => $result['payload']['quality_score'],
                    'excerpt' => '',
                    'meilisearch_score' => 0,
                    'qdrant_score' => $semanticScore,
                    'hybrid_score' => 0,
                    'match_type' => 'semantic',
                ];
            }
        }
        
        // Calculate hybrid scores
        foreach ($combined as &$result) {
            $result['hybrid_score'] = 
                ($result['meilisearch_score'] * $meilisearchWeight) +
                ($result['qdrant_score'] * $qdrantWeight);
        }
        
        return array_values($combined);
    }
    
    /**
     * Format Qdrant results to match standard format
     */
    protected function formatQdrantResults(array $qdrantResults): array
    {
        $formatted = [];
        
        foreach ($qdrantResults as $result) {
            $payload = $result['payload'] ?? [];
            $formatted[] = [
                'id' => $payload['article_id'],
                'title' => $payload['title'],
                'url' => $payload['url'],
                'source_id' => $payload['source_id'],
                'source_name' => $payload['source_name'],
                'published_at' => $payload['published_at'],
                'quality_score' => $payload['quality_score'],
                'excerpt' => '',
                'score' => $result['score'] ?? 0,
                'match_type' => 'semantic',
            ];
        }
        
        return $formatted;
    }
    
    /**
     * Normalize Meilisearch score (they don't provide relevance scores directly)
     */
    protected function normalizeMeilisearchScore(array $result): float
    {
        // Meilisearch doesn't provide relevance scores, so we estimate based on:
        // - Position in results (higher = better)
        // - Quality score of the article
        // - Presence of highlighted terms
        
        $baseScore = 0.5; // Default relevance
        
        // Boost based on quality score
        if (isset($result['quality_score']) && $result['quality_score'] > 0) {
            $baseScore += ($result['quality_score'] / 100) * 0.3;
        }
        
        // Boost if there are highlighted terms
        if (isset($result['_formatted']) && !empty($result['_formatted'])) {
            $baseScore += 0.2;
        }
        
        return min($baseScore, 1.0);
    }
    
    /**
     * Calculate duplicate likelihood based on match scores
     */
    protected function calculateDuplicateLikelihood(array $matches): float
    {
        if (empty($matches)) {
            return 0.0;
        }
        
        $topScore = 0.0;
        $totalScore = 0.0;
        $highScoreCount = 0;
        
        foreach ($matches as $match) {
            $score = $match['hybrid_score'] ?? $match['score'] ?? 0;
            $totalScore += $score;
            
            if ($score > $topScore) {
                $topScore = $score;
            }
            
            if ($score >= 0.8) {
                $highScoreCount++;
            }
        }
        
        $avgScore = $totalScore / count($matches);
        
        // Likelihood based on:
        // - Top score (50%)
        // - Average score (30%)
        // - Number of high-scoring matches (20%)
        $likelihood = 
            ($topScore * 0.5) + 
            ($avgScore * 0.3) + 
            (min($highScoreCount / 3, 1.0) * 0.2);
            
        return min($likelihood, 1.0);
    }
    
    /**
     * Rank and limit search results
     */
    protected function rankAndLimitResults(array $matches, array $options): array
    {
        // Sort by hybrid score or individual engine score
        usort($matches, function($a, $b) {
            $scoreA = $a['hybrid_score'] ?? $a['score'] ?? 0;
            $scoreB = $b['hybrid_score'] ?? $b['score'] ?? 0;
            
            if ($scoreA == $scoreB) {
                // Secondary sort by quality score
                return ($b['quality_score'] ?? 0) <=> ($a['quality_score'] ?? 0);
            }
            
            return $scoreB <=> $scoreA;
        });
        
        $limit = $options['limit'] ?? $this->config['options']['default_limit'];
        $maxLimit = $this->config['options']['max_limit'];
        
        $limit = min($limit, $maxLimit);
        
        return array_slice($matches, 0, $limit);
    }
    
    /**
     * Check if content is likely a duplicate of existing articles
     */
    public function isDuplicateContent(string $content, float $threshold = null): array
    {
        $threshold = $threshold ?? config('verifysource.content.duplicate_threshold', 0.8);
        
        $matches = $this->findMatches($content, [
            'limit' => 5,
            'score_threshold' => $threshold,
        ]);
        
        $isDuplicate = $matches['duplicate_likelihood'] >= $threshold;
        
        return [
            'is_duplicate' => $isDuplicate,
            'likelihood' => $matches['duplicate_likelihood'],
            'threshold' => $threshold,
            'matches' => $matches['matches'],
            'top_match' => $matches['matches'][0] ?? null,
        ];
    }
    
    /**
     * Find potential source of content
     */
    public function findContentSource(string $content): array
    {
        $matches = $this->findMatches($content, [
            'limit' => 10,
        ]);
        
        if (empty($matches['matches'])) {
            return [
                'found_source' => false,
                'confidence' => 0.0,
            ];
        }
        
        // Group by source and find the most likely original source
        $sourceScores = [];
        $earliestBySource = [];
        
        foreach ($matches['matches'] as $match) {
            $sourceId = $match['source_id'];
            $publishedAt = $match['published_at'];
            $score = $match['hybrid_score'] ?? $match['score'] ?? 0;
            
            if (!isset($sourceScores[$sourceId])) {
                $sourceScores[$sourceId] = 0;
                $earliestBySource[$sourceId] = $publishedAt;
            }
            
            $sourceScores[$sourceId] += $score;
            
            if ($publishedAt && (!$earliestBySource[$sourceId] || $publishedAt < $earliestBySource[$sourceId])) {
                $earliestBySource[$sourceId] = $publishedAt;
            }
        }
        
        // Find the source with highest combined score
        arsort($sourceScores);
        $topSourceId = array_key_first($sourceScores);
        
        if (!$topSourceId) {
            return [
                'found_source' => false,
                'confidence' => 0.0,
            ];
        }
        
        // Get the earliest article from the top source
        $sourceMatches = array_filter($matches['matches'], fn($m) => $m['source_id'] == $topSourceId);
        $earliestMatch = array_reduce($sourceMatches, function($earliest, $current) {
            if (!$earliest || ($current['published_at'] && $current['published_at'] < $earliest['published_at'])) {
                return $current;
            }
            return $earliest;
        });
        
        return [
            'found_source' => true,
            'confidence' => $matches['duplicate_likelihood'],
            'original_article' => $earliestMatch,
            'source_id' => $topSourceId,
            'source_name' => $earliestMatch['source_name'] ?? 'Unknown',
            'all_matches' => $matches['matches'],
            'total_matches_from_source' => count($sourceMatches),
        ];
    }
    
    /**
     * Get comprehensive search statistics
     */
    public function getSearchStats(): array
    {
        return [
            'meilisearch' => $this->meilisearch->getServerInfo(),
            'qdrant' => $this->qdrant->getServerInfo(),
            'configuration' => [
                'default_engine' => $this->config['default_engine'],
                'hybrid_weights' => $this->config['options']['hybrid_weights'],
                'similarity_threshold' => $this->config['options']['similarity_threshold'],
            ],
        ];
    }
}