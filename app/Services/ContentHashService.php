<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ContentHash;
use Illuminate\Support\Facades\Log;

class ContentHashService
{
    protected ContentProcessingService $contentProcessor;

    public function __construct(ContentProcessingService $contentProcessor)
    {
        $this->contentProcessor = $contentProcessor;
    }

    public function generateHash(string $content, string $type = 'sha256'): string
    {
        $normalizedContent = $this->contentProcessor->normalizeContent($content);

        switch ($type) {
            case 'sha256':
                return hash('sha256', $normalizedContent);
            case 'md5':
                return hash('md5', $normalizedContent);
            case 'sha1':
                return hash('sha1', $normalizedContent);
            default:
                throw new \InvalidArgumentException("Unsupported hash type: {$type}");
        }
    }

    public function findExactDuplicates(string $contentHash): \Illuminate\Database\Eloquent\Collection
    {
        return ContentHash::where('hash', $contentHash)
            ->with('article.source')
            ->get();
    }

    public function findSimilarContent(string $content, float $threshold = 0.8): array
    {
        $contentHash = $this->generateHash($content);
        $normalizedContent = $this->contentProcessor->normalizeContent($content);
        $contentWords = explode(' ', $normalizedContent);
        $contentLength = count($contentWords);

        $similarArticles = [];

        Article::with(['source', 'contentHash'])
            ->where('is_processed', true)
            ->chunk(100, function ($articles) use ($contentWords, $contentLength, $threshold, &$similarArticles) {
                foreach ($articles as $article) {
                    $similarity = $this->calculateContentSimilarity($contentWords, $contentLength, $article);

                    if ($similarity >= $threshold) {
                        $similarArticles[] = [
                            'article' => $article,
                            'similarity_score' => $similarity,
                            'match_type' => $similarity >= 0.95 ? 'near_exact' : 'similar',
                        ];
                    }
                }
            });

        usort($similarArticles, function ($a, $b) {
            return $b['similarity_score'] <=> $a['similarity_score'];
        });

        return $similarArticles;
    }

    public function calculateContentSimilarity(array $contentWords, int $contentLength, Article $article): float
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

        $wordOrderSimilarity = $this->calculateWordOrderSimilarity($contentWords, $articleWords);

        return ($jaccardSimilarity * 0.5) + ($lengthSimilarity * 0.2) + ($wordOrderSimilarity * 0.3);
    }

    protected function calculateWordOrderSimilarity(array $words1, array $words2): float
    {
        $commonWords = array_intersect($words1, $words2);

        if (count($commonWords) < 2) {
            return 0.0;
        }

        $positions1 = [];
        $positions2 = [];

        foreach ($commonWords as $word) {
            $positions1[] = array_search($word, $words1);
            $positions2[] = array_search($word, $words2);
        }

        $inversions = 0;
        $n = count($positions1);

        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                if (($positions1[$i] < $positions1[$j] && $positions2[$i] > $positions2[$j]) ||
                    ($positions1[$i] > $positions1[$j] && $positions2[$i] < $positions2[$j])) {
                    $inversions++;
                }
            }
        }

        $maxInversions = $n * ($n - 1) / 2;

        return $maxInversions > 0 ? 1 - ($inversions / $maxInversions) : 1.0;
    }

    public function updateSimilarityScores(ContentHash $contentHash): void
    {
        $article = $contentHash->article;
        $similarArticles = $this->findSimilarContent($article->content, 0.7);

        $similarHashes = [];

        foreach ($similarArticles as $similar) {
            $similarHashes[] = [
                'article_id' => $similar['article']->id,
                'similarity_score' => $similar['similarity_score'],
                'match_type' => $similar['match_type'],
            ];
        }

        $contentHash->update([
            'similarity_score' => count($similarHashes) > 0 ? max(array_column($similarHashes, 'similarity_score')) : null,
            'similar_hashes' => $similarHashes,
        ]);

        Log::info('Similarity scores updated', [
            'content_hash_id' => $contentHash->id,
            'similar_count' => count($similarHashes),
        ]);
    }

    public function detectDuplicates(Article $article): array
    {
        $contentHash = $this->generateHash($article->content);
        $exactDuplicates = $this->findExactDuplicates($contentHash);
        $similarContent = $this->findSimilarContent($article->content, 0.9);

        $duplicates = [];

        foreach ($exactDuplicates as $duplicate) {
            if ($duplicate->article_id !== $article->id) {
                $duplicates[] = [
                    'type' => 'exact',
                    'similarity_score' => 1.0,
                    'article' => $duplicate->article,
                ];
            }
        }

        foreach ($similarContent as $similar) {
            if ($similar['article']->id !== $article->id) {
                $duplicates[] = [
                    'type' => 'similar',
                    'similarity_score' => $similar['similarity_score'],
                    'article' => $similar['article'],
                ];
            }
        }

        return $duplicates;
    }

    public function markAsDuplicate(Article $article, array $duplicates): void
    {
        if (! empty($duplicates)) {
            $article->update(['is_duplicate' => true]);

            Log::info('Article marked as duplicate', [
                'article_id' => $article->id,
                'duplicate_count' => count($duplicates),
            ]);
        }
    }

    public function getHashStatistics(): array
    {
        $totalHashes = ContentHash::count();
        $hasSimilar = ContentHash::whereNotNull('similar_hashes')->count();
        $exactDuplicates = ContentHash::where('similarity_score', 1.0)->count();

        return [
            'total_hashes' => $totalHashes,
            'has_similar_content' => $hasSimilar,
            'exact_duplicates' => $exactDuplicates,
            'similarity_rate' => $totalHashes > 0 ? ($hasSimilar / $totalHashes) * 100 : 0,
            'duplicate_rate' => $totalHashes > 0 ? ($exactDuplicates / $totalHashes) * 100 : 0,
        ];
    }

    public function cleanupOrphanedHashes(): int
    {
        $orphanedHashes = ContentHash::whereDoesntHave('article')->delete();

        Log::info('Cleaned up orphaned content hashes', [
            'count' => $orphanedHashes,
        ]);

        return $orphanedHashes;
    }

    public function generateFingerprint(string $content): string
    {
        $normalizedContent = $this->contentProcessor->normalizeContent($content);
        $words = explode(' ', $normalizedContent);

        $stopWords = ['the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'a', 'an', 'is', 'are', 'was', 'were', 'be', 'been', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should'];

        $filteredWords = array_filter($words, function ($word) use ($stopWords) {
            return ! in_array($word, $stopWords) && strlen($word) > 2;
        });

        $fingerprint = array_slice($filteredWords, 0, 20);
        sort($fingerprint);

        return implode(' ', $fingerprint);
    }

    public function findByFingerprint(string $fingerprint): \Illuminate\Database\Eloquent\Collection
    {
        return Article::where('content', 'LIKE', '%'.$fingerprint.'%')
            ->with(['source', 'contentHash'])
            ->get();
    }
}
