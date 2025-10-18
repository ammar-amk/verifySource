<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ContentHash;
use App\Models\Source;
use App\Models\VerificationRequest;
use App\Models\VerificationResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ContentVerificationService
{
    protected ContentProcessingService $contentProcessor;
    protected CredibilityService $credibilityService;

    public function __construct(
        ContentProcessingService $contentProcessor,
        CredibilityService $credibilityService
    ) {
        $this->contentProcessor = $contentProcessor;
        $this->credibilityService = $credibilityService;
    }

    public function verifyContent(VerificationRequest $request): array
    {
        try {
            $request->update(['status' => 'processing']);
            
            $content = $this->getContentFromRequest($request);
            $contentHash = hash('sha256', $this->contentProcessor->normalizeContent($content));
            
            $similarArticles = $this->findSimilarArticles($contentHash, $content);
            $results = $this->processVerificationResults($request, $similarArticles);
            
            $request->update([
                'status' => 'completed',
                'results' => $results,
                'confidence_score' => $this->calculateOverallConfidence($results),
                'processed_at' => now(),
            ]);
            
            Log::info("Content verification completed", [
                'request_id' => $request->id,
                'results_count' => count($results)
            ]);
            
            return $results;
        } catch (\Exception $e) {
            $request->update(['status' => 'failed']);
            
            Log::error("Content verification failed", [
                'request_id' => $request->id,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    protected function getContentFromRequest(VerificationRequest $request): string
    {
        if ($request->input_text) {
            return $request->input_text;
        }
        
        if ($request->input_url) {
            return $this->extractContentFromUrl($request->input_url);
        }
        
        throw new \InvalidArgumentException('No content provided for verification');
    }

    protected function extractContentFromUrl(string $url): string
    {
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'user_agent' => config('verifysource.crawler.user_agent'),
                ]
            ]);
            
            $content = file_get_contents($url, false, $context);
            
            if ($content === false) {
                throw new \Exception("Failed to fetch content from URL: {$url}");
            }
            
            return strip_tags($content);
        } catch (\Exception $e) {
            Log::warning("Failed to extract content from URL", [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            
            return '';
        }
    }

    protected function findSimilarArticles(string $contentHash, string $content): array
    {
        $exactMatches = $this->findExactMatches($contentHash);
        $similarMatches = $this->findSimilarMatches($content);
        
        return array_merge($exactMatches, $similarMatches);
    }

    protected function findExactMatches(string $contentHash): array
    {
        return Article::whereHas('contentHash', function ($query) use ($contentHash) {
            $query->where('hash', $contentHash);
        })
        ->with(['source', 'contentHash'])
        ->get()
        ->map(function ($article) {
            return [
                'article' => $article,
                'similarity_score' => 1.0,
                'match_type' => 'exact',
                'match_details' => [
                    'hash_match' => true,
                    'content_identical' => true,
                ]
            ];
        })->toArray();
    }

    protected function findSimilarMatches(string $content): array
    {
        $normalizedContent = $this->contentProcessor->normalizeContent($content);
        $contentWords = explode(' ', $normalizedContent);
        $contentLength = count($contentWords);
        
        $similarArticles = [];
        
        Article::with(['source', 'contentHash'])
            ->where('is_processed', true)
            ->where('is_duplicate', false)
            ->chunk(100, function ($articles) use ($contentWords, $contentLength, &$similarArticles) {
                foreach ($articles as $article) {
                    $similarity = $this->calculateSimilarity($contentWords, $contentLength, $article);
                    
                    if ($similarity >= config('verifysource.verification.similarity_threshold', 0.8)) {
                        $similarArticles[] = [
                            'article' => $article,
                            'similarity_score' => $similarity,
                            'match_type' => 'similar',
                            'match_details' => [
                                'word_overlap' => $similarity,
                                'content_similarity' => true,
                            ]
                        ];
                    }
                }
            });
        
        usort($similarArticles, function ($a, $b) {
            return $b['similarity_score'] <=> $a['similarity_score'];
        });
        
        return array_slice($similarArticles, 0, config('verifysource.verification.max_results', 10));
    }

    protected function calculateSimilarity(array $contentWords, int $contentLength, Article $article): float
    {
        $articleContent = $this->contentProcessor->normalizeContent($article->content);
        $articleWords = explode(' ', $articleContent);
        $articleLength = count($articleWords);
        
        $commonWords = array_intersect($contentWords, $articleWords);
        $commonCount = count($commonWords);
        
        if ($commonCount === 0) {
            return 0.0;
        }
        
        $jaccardSimilarity = $commonCount / ($contentLength + $articleLength - $commonCount);
        
        $lengthSimilarity = 1 - abs($contentLength - $articleLength) / max($contentLength, $articleLength);
        
        return ($jaccardSimilarity * 0.7) + ($lengthSimilarity * 0.3);
    }

    protected function processVerificationResults(VerificationRequest $request, array $similarArticles): array
    {
        $results = [];
        $earliestPublication = null;
        
        foreach ($similarArticles as $match) {
            $article = $match['article'];
            $similarityScore = $match['similarity_score'];
            
            // Get enhanced credibility assessment
            $credibilityAssessment = $this->credibilityService->getQuickCredibilityAssessment($article->source);
            $credibilityScore = $credibilityAssessment['overall_score'] ?? $article->source->credibility_score;
            
            // Calculate article-specific credibility if we have content
            $articleCredibility = null;
            if ($article->content) {
                try {
                    $articleCredibility = $this->credibilityService->calculateArticleCredibility($article);
                } catch (\Exception $e) {
                    Log::warning("Failed to calculate article credibility", [
                        'article_id' => $article->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            $verificationResult = VerificationResult::create([
                'verification_request_id' => $request->id,
                'article_id' => $article->id,
                'similarity_score' => $similarityScore,
                'credibility_score' => $credibilityScore,
                'earliest_publication' => $article->published_at,
                'match_type' => $match['match_type'],
                'match_details' => array_merge($match['match_details'], [
                    'credibility_assessment' => $credibilityAssessment,
                    'article_credibility' => $articleCredibility
                ]),
                'is_earliest_source' => false,
            ]);
            
            if ($earliestPublication === null || $article->published_at < $earliestPublication) {
                $earliestPublication = $article->published_at;
            }
            
            $results[] = [
                'id' => $verificationResult->id,
                'article' => [
                    'id' => $article->id,
                    'title' => $article->title,
                    'url' => $article->url,
                    'published_at' => $article->published_at,
                    'excerpt' => $article->excerpt,
                    'credibility_score' => $articleCredibility['overall_score'] ?? null,
                    'quality_indicators' => $articleCredibility['quality_indicators'] ?? null,
                ],
                'source' => [
                    'id' => $article->source->id,
                    'name' => $article->source->name,
                    'domain' => $article->source->domain,
                    'credibility_score' => $credibilityScore,
                    'credibility_level' => $credibilityAssessment['credibility_level'] ?? 'unknown',
                    'trust_indicators' => $credibilityAssessment['trust_indicators'] ?? [],
                ],
                'similarity_score' => $similarityScore,
                'credibility_score' => $credibilityScore,
                'match_type' => $match['match_type'],
                'match_details' => $verificationResult->match_details,
                'credibility_assessment' => $credibilityAssessment,
            ];
        }
        
        if ($earliestPublication) {
            VerificationResult::where('verification_request_id', $request->id)
                ->where('earliest_publication', $earliestPublication)
                ->update(['is_earliest_source' => true]);
        }
        
        return $results;
    }

    protected function calculateOverallConfidence(array $results): float
    {
        if (empty($results)) {
            return 0.0;
        }
        
        $totalScore = 0;
        $weightedSum = 0;
        $totalWeight = 0;
        
        foreach ($results as $result) {
            // Enhanced confidence calculation incorporating credibility
            $similarityScore = $result['similarity_score'] * 100; // Convert to 0-100 scale
            $credibilityScore = $result['credibility_score'];
            
            // Weight sources based on credibility level
            $credibilityLevel = $result['source']['credibility_level'] ?? 'medium';
            $weight = match($credibilityLevel) {
                'very_high' => 1.5,
                'high' => 1.2,
                'medium' => 1.0,
                'low' => 0.8,
                'very_low' => 0.6,
                default => 1.0
            };
            
            // Calculate weighted score (similarity 60%, credibility 40%)
            $overallScore = ($similarityScore * 0.6) + ($credibilityScore * 0.4);
            
            $weightedSum += $overallScore * $weight;
            $totalWeight += $weight;
        }
        
        $averageScore = $totalWeight > 0 ? $weightedSum / $totalWeight : 0.0;
        
        // Apply bonuses for exact matches and high-credibility sources
        $exactMatches = array_filter($results, fn($r) => $r['match_type'] === 'exact');
        if (!empty($exactMatches)) {
            $averageScore *= 1.1; // 10% bonus for exact matches
        }
        
        $highCredibilitySources = array_filter($results, fn($r) => ($r['credibility_score'] ?? 0) >= 80);
        if (!empty($highCredibilitySources)) {
            $averageScore *= 1.05; // 5% bonus for high-credibility sources
        }
        
        return min(100.0, max(0.0, $averageScore));
    }

    public function getVerificationStats(): array
    {
        return [
            'total_requests' => VerificationRequest::count(),
            'completed_requests' => VerificationRequest::where('status', 'completed')->count(),
            'pending_requests' => VerificationRequest::where('status', 'pending')->count(),
            'failed_requests' => VerificationRequest::where('status', 'failed')->count(),
            'average_confidence' => VerificationRequest::where('status', 'completed')
                ->avg('confidence_score') ?? 0,
            'total_results' => VerificationResult::count(),
            'exact_matches' => VerificationResult::where('match_type', 'exact')->count(),
            'similar_matches' => VerificationResult::where('match_type', 'similar')->count(),
        ];
    }
}
