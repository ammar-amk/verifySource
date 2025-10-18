<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ContentHash;
use App\Models\Source;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ContentProcessingService
{
    public function processArticle(Article $article): bool
    {
        try {
            $this->generateContentHash($article);
            $this->extractMetadata($article);
            $this->detectLanguage($article);
            $this->generateExcerpt($article);
            
            $article->update(['is_processed' => true]);
            
            Log::info("Article processed successfully", ['article_id' => $article->id]);
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to process article", [
                'article_id' => $article->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function generateContentHash(Article $article): void
    {
        $content = $this->normalizeContent($article->content);
        $hash = hash('sha256', $content);
        
        ContentHash::updateOrCreate(
            ['article_id' => $article->id],
            [
                'hash' => $hash,
                'hash_type' => 'sha256',
                'similarity_score' => null,
                'similar_hashes' => null,
            ]
        );
        
        $article->update(['content_hash' => $hash]);
    }

    public function extractMetadata(Article $article): void
    {
        $metadata = [
            'word_count' => str_word_count($article->content),
            'character_count' => strlen($article->content),
            'reading_time' => $this->calculateReadingTime($article->content),
            'extracted_at' => now()->toISOString(),
        ];

        $existingMetadata = $article->metadata ?? [];
        $article->update(['metadata' => array_merge($existingMetadata, $metadata)]);
    }

    public function detectLanguage(Article $article): void
    {
        $language = $this->detectLanguageFromContent($article->content);
        $article->update(['language' => $language]);
    }

    public function generateExcerpt(Article $article): void
    {
        if (empty($article->excerpt)) {
            $excerpt = $this->createExcerpt($article->content, 200);
            $article->update(['excerpt' => $excerpt]);
        }
    }

    public function normalizeContent(string $content): string
    {
        $content = strip_tags($content);
        $content = preg_replace('/\s+/', ' ', $content);
        $content = trim($content);
        $content = mb_strtolower($content, 'UTF-8');
        
        return $content;
    }

    public function calculateReadingTime(string $content): int
    {
        $wordCount = str_word_count($content);
        $wordsPerMinute = 200;
        
        return max(1, ceil($wordCount / $wordsPerMinute));
    }

    public function detectLanguageFromContent(string $content): string
    {
        $commonWords = [
            'en' => ['the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by'],
            'es' => ['el', 'la', 'de', 'que', 'y', 'a', 'en', 'un', 'es', 'se', 'no', 'te', 'lo'],
            'fr' => ['le', 'de', 'et', 'à', 'un', 'il', 'être', 'et', 'en', 'avoir', 'que', 'pour'],
            'de' => ['der', 'die', 'und', 'in', 'den', 'von', 'zu', 'das', 'mit', 'sich', 'des', 'auf'],
        ];

        $content = mb_strtolower($content, 'UTF-8');
        $scores = [];

        foreach ($commonWords as $lang => $words) {
            $score = 0;
            foreach ($words as $word) {
                $score += substr_count($content, ' ' . $word . ' ');
            }
            $scores[$lang] = $score;
        }

        $detectedLang = array_keys($scores, max($scores))[0];
        
        return $scores[$detectedLang] > 0 ? $detectedLang : 'en';
    }

    public function createExcerpt(string $content, int $length = 200): string
    {
        $content = strip_tags($content);
        $content = preg_replace('/\s+/', ' ', $content);
        
        if (strlen($content) <= $length) {
            return $content;
        }
        
        $excerpt = substr($content, 0, $length);
        $lastSpace = strrpos($excerpt, ' ');
        
        if ($lastSpace !== false) {
            $excerpt = substr($excerpt, 0, $lastSpace);
        }
        
        return $excerpt . '...';
    }

    public function findSimilarContent(string $contentHash, float $threshold = 0.8): array
    {
        $similarHashes = ContentHash::where('hash', $contentHash)
            ->orWhere('similarity_score', '>=', $threshold)
            ->with('article.source')
            ->get();

        return $similarHashes->map(function ($hash) {
            return [
                'article_id' => $hash->article_id,
                'similarity_score' => $hash->similarity_score,
                'article' => $hash->article,
            ];
        })->toArray();
    }

    public function markAsDuplicate(Article $article): void
    {
        $article->update(['is_duplicate' => true]);
        
        Log::info("Article marked as duplicate", [
            'article_id' => $article->id,
            'url' => $article->url
        ]);
    }

    public function validateContent(Article $article): array
    {
        $errors = [];
        
        if (empty($article->title)) {
            $errors[] = 'Title is required';
        }
        
        if (empty($article->content)) {
            $errors[] = 'Content is required';
        }
        
        if (strlen($article->content) < 100) {
            $errors[] = 'Content is too short (minimum 100 characters)';
        }
        
        if (strlen($article->content) > 100000) {
            $errors[] = 'Content is too long (maximum 100,000 characters)';
        }
        
        if (!filter_var($article->url, FILTER_VALIDATE_URL)) {
            $errors[] = 'Invalid URL format';
        }
        
        return $errors;
    }

    public function getContentStats(): array
    {
        return [
            'total_articles' => Article::count(),
            'processed_articles' => Article::where('is_processed', true)->count(),
            'duplicate_articles' => Article::where('is_duplicate', true)->count(),
            'unprocessed_articles' => Article::where('is_processed', false)->count(),
            'total_sources' => Source::count(),
            'active_sources' => Source::where('is_active', true)->count(),
        ];
    }
}
