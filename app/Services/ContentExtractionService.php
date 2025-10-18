<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Source;
use App\Services\ContentHashService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ContentExtractionService
{
    protected ContentHashService $contentHashService;

    public function __construct(ContentHashService $contentHashService)
    {
        $this->contentHashService = $contentHashService;
    }

    public function processScrapedContent(array $scrapedData, Source $source): ?Article
    {
        try {
            Log::info("Processing scraped content", [
                'source_id' => $source->id,
                'url' => $scrapedData['url'] ?? 'unknown'
            ]);

            // Clean and normalize the content
            $cleanedData = $this->cleanContent($scrapedData);
            
            // Check if this content already exists
            if ($this->isDuplicateContent($cleanedData, $source)) {
                Log::info("Duplicate content detected, skipping", [
                    'url' => $cleanedData['url']
                ]);
                return null;
            }

            // Create the article
            $article = $this->createArticle($cleanedData, $source);
            
            // Generate content hash
            $this->contentHashService->generateHash($article);

            Log::info("Content processed successfully", [
                'article_id' => $article->id,
                'title' => $article->title
            ]);

            return $article;

        } catch (\Exception $e) {
            Log::error("Error processing scraped content", [
                'error' => $e->getMessage(),
                'source_id' => $source->id,
                'url' => $scrapedData['url'] ?? 'unknown'
            ]);

            return null;
        }
    }

    protected function cleanContent(array $scrapedData): array
    {
        $cleaned = [];

        // Clean URL
        $cleaned['url'] = $this->normalizeUrl($scrapedData['url'] ?? '');

        // Clean title
        $cleaned['title'] = $this->cleanTitle($scrapedData['title'] ?? '');

        // Clean content
        $cleaned['content'] = $this->cleanTextContent($scrapedData['content'] ?? '');

        // Clean excerpt
        $cleaned['excerpt'] = $this->cleanTextContent($scrapedData['excerpt'] ?? '');
        if (empty($cleaned['excerpt']) && !empty($cleaned['content'])) {
            $cleaned['excerpt'] = $this->generateExcerpt($cleaned['content']);
        }

        // Clean author
        $cleaned['author'] = $this->cleanAuthor($scrapedData['author'] ?? '');

        // Parse and normalize published date
        $cleaned['published_at'] = $this->parsePublishedDate($scrapedData['published_at'] ?? null);

        // Detect language
        $cleaned['language'] = $this->detectLanguage($scrapedData, $cleaned);

        // Process metadata
        $cleaned['metadata'] = $this->processMetadata($scrapedData);

        return $cleaned;
    }

    protected function normalizeUrl(string $url): string
    {
        if (empty($url)) {
            return '';
        }

        // Remove query parameters that don't affect content
        $parsed = parse_url($url);
        if (!$parsed) {
            return $url;
        }

        $cleanUrl = ($parsed['scheme'] ?? 'http') . '://' . ($parsed['host'] ?? '');
        
        if (isset($parsed['port'])) {
            $cleanUrl .= ':' . $parsed['port'];
        }
        
        if (isset($parsed['path'])) {
            $cleanUrl .= $parsed['path'];
        }

        // Keep important query parameters
        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $queryParams);
            $keepParams = ['p', 'id', 'article', 'post', 'page'];
            $filteredParams = array_intersect_key($queryParams, array_flip($keepParams));
            
            if (!empty($filteredParams)) {
                $cleanUrl .= '?' . http_build_query($filteredParams);
            }
        }

        return $cleanUrl;
    }

    protected function cleanTitle(string $title): string
    {
        if (empty($title)) {
            return '';
        }

        // Remove common site suffixes
        $suffixes = [
            ' - ' . '[^-]*$',
            ' | ' . '[^|]*$',
            ' :: ' . '[^:]*$',
            ' » ' . '[^»]*$',
        ];

        foreach ($suffixes as $suffix) {
            $title = preg_replace('/' . $suffix . '/', '', $title);
        }

        // Clean up whitespace and special characters
        $title = preg_replace('/\s+/', ' ', $title);
        $title = trim($title);

        // Remove excessive punctuation
        $title = preg_replace('/[!]{2,}/', '!', $title);
        $title = preg_replace('/[?]{2,}/', '?', $title);

        return $title;
    }

    protected function cleanTextContent(string $content): string
    {
        if (empty($content)) {
            return '';
        }

        // Remove excessive whitespace
        $content = preg_replace('/\s+/', ' ', $content);
        
        // Remove repeated newlines
        $content = preg_replace('/\n{3,}/', "\n\n", $content);
        
        // Remove common boilerplate text
        $boilerplate = [
            '/Sign up for.*?newsletter/i',
            '/Follow us on.*?social media/i',
            '/Click here to.*?subscribe/i',
            '/Advertisement/i',
            '/Sponsored content/i',
            '/Related articles?:/i',
            '/Read more:/i',
            '/Continue reading/i',
        ];

        foreach ($boilerplate as $pattern) {
            $content = preg_replace($pattern, '', $content);
        }

        return trim($content);
    }

    protected function cleanAuthor(string $author): string
    {
        if (empty($author)) {
            return '';
        }

        // Remove common prefixes
        $prefixes = ['By ', 'Author: ', 'Written by ', 'Posted by '];
        foreach ($prefixes as $prefix) {
            if (str_starts_with($author, $prefix)) {
                $author = substr($author, strlen($prefix));
            }
        }

        // Clean up whitespace
        $author = preg_replace('/\s+/', ' ', $author);
        $author = trim($author);

        // Remove email addresses and social handles
        $author = preg_replace('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', '', $author);
        $author = preg_replace('/@\w+/', '', $author);

        return trim($author);
    }

    protected function parsePublishedDate(?string $dateString): ?Carbon
    {
        if (empty($dateString)) {
            return null;
        }

        try {
            // Try parsing ISO 8601 format first
            if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $dateString)) {
                return Carbon::parse($dateString);
            }

            // Try common date formats
            $formats = [
                'Y-m-d H:i:s',
                'Y-m-d',
                'M j, Y',
                'F j, Y',
                'j M Y',
                'j F Y',
                'd/m/Y',
                'm/d/Y',
                'Y/m/d',
            ];

            foreach ($formats as $format) {
                try {
                    return Carbon::createFromFormat($format, $dateString);
                } catch (\Exception $e) {
                    continue;
                }
            }

            // Try Carbon's flexible parsing
            return Carbon::parse($dateString);

        } catch (\Exception $e) {
            Log::warning("Failed to parse date", [
                'date_string' => $dateString,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    protected function detectLanguage(array $scrapedData, array $cleanedData): string
    {
        // Use explicit language from scraped data if available
        if (!empty($scrapedData['language'])) {
            return strtolower(substr($scrapedData['language'], 0, 2));
        }

        // Simple language detection based on content
        $text = ($cleanedData['title'] ?? '') . ' ' . ($cleanedData['content'] ?? '');
        $text = strtolower($text);

        $languages = [
            'en' => ['the', 'and', 'that', 'have', 'for', 'not', 'with', 'you', 'this', 'but'],
            'es' => ['que', 'de', 'no', 'la', 'el', 'en', 'un', 'es', 'se', 'le'],
            'fr' => ['que', 'de', 'je', 'est', 'pas', 'le', 'vous', 'la', 'tu', 'il'],
            'de' => ['der', 'die', 'und', 'in', 'den', 'von', 'zu', 'das', 'mit', 'sich'],
            'it' => ['che', 'di', 'da', 'in', 'un', 'il', 'del', 'non', 'sono', 'una'],
        ];

        $scores = [];
        
        foreach ($languages as $lang => $keywords) {
            $score = 0;
            foreach ($keywords as $keyword) {
                $score += substr_count($text, ' ' . $keyword . ' ');
            }
            $scores[$lang] = $score;
        }

        arsort($scores);
        $detectedLang = array_key_first($scores);

        return $detectedLang ?: 'en'; // Default to English
    }

    protected function processMetadata(array $scrapedData): array
    {
        $metadata = [];

        // Store original metadata
        if (!empty($scrapedData['meta_description'])) {
            $metadata['meta_description'] = $scrapedData['meta_description'];
        }

        if (!empty($scrapedData['meta_keywords'])) {
            $metadata['meta_keywords'] = $scrapedData['meta_keywords'];
        }

        if (!empty($scrapedData['canonical_url'])) {
            $metadata['canonical_url'] = $scrapedData['canonical_url'];
        }

        // Store images info
        if (!empty($scrapedData['images'])) {
            $metadata['images'] = array_slice($scrapedData['images'], 0, 5); // Store first 5 images
        }

        // Store important links
        if (!empty($scrapedData['links'])) {
            $externalLinks = array_filter($scrapedData['links'], function ($link) {
                return $link['is_external'] ?? false;
            });
            $metadata['external_links'] = array_slice($externalLinks, 0, 10);
        }

        // Store feed links
        if (!empty($scrapedData['feed_links'])) {
            $metadata['feed_links'] = $scrapedData['feed_links'];
        }

        // Store social media links
        if (!empty($scrapedData['social_media'])) {
            $metadata['social_media'] = $scrapedData['social_media'];
        }

        // Store Schema.org data
        if (!empty($scrapedData['schema_org'])) {
            $metadata['schema_org'] = $scrapedData['schema_org'];
        }

        return $metadata;
    }

    protected function isDuplicateContent(array $cleanedData, Source $source): bool
    {
        // Check by URL first
        if (Article::where('url', $cleanedData['url'])->exists()) {
            return true;
        }

        // Check by title and source
        if (!empty($cleanedData['title'])) {
            $existingByTitle = Article::where('source_id', $source->id)
                ->where('title', $cleanedData['title'])
                ->exists();
            
            if ($existingByTitle) {
                return true;
            }
        }

        // Check by content similarity (basic check)
        if (!empty($cleanedData['content']) && strlen($cleanedData['content']) > 100) {
            $contentPreview = substr($cleanedData['content'], 0, 200);
            
            $similarArticles = Article::where('source_id', $source->id)
                ->where('content', 'LIKE', '%' . substr($contentPreview, 50, 100) . '%')
                ->exists();
                
            if ($similarArticles) {
                return true;
            }
        }

        return false;
    }

    protected function createArticle(array $cleanedData, Source $source): Article
    {
        $articleData = [
            'source_id' => $source->id,
            'url' => $cleanedData['url'],
            'title' => $cleanedData['title'],
            'content' => $cleanedData['content'],
            'excerpt' => $cleanedData['excerpt'],
            'author' => $cleanedData['author'],
            'published_at' => $cleanedData['published_at'],
            'crawled_at' => now(),
            'language' => $cleanedData['language'],
            'metadata' => $cleanedData['metadata'],
            'is_processed' => false,
            'is_duplicate' => false,
        ];

        return Article::create($articleData);
    }

    protected function generateExcerpt(string $content, int $length = 160): string
    {
        $content = strip_tags($content);
        $content = preg_replace('/\s+/', ' ', $content);
        $content = trim($content);

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

    public function extractArticleUrls(array $scrapedData, Source $source): array
    {
        $urls = [];
        $baseUrl = $source->url;
        $baseDomain = parse_url($baseUrl, PHP_URL_HOST);

        // Extract from links
        if (!empty($scrapedData['links'])) {
            foreach ($scrapedData['links'] as $link) {
                $linkUrl = $link['url'] ?? '';
                $linkDomain = parse_url($linkUrl, PHP_URL_HOST);
                
                // Only include internal links that look like articles
                if ($linkDomain === $baseDomain && $this->looksLikeArticleUrl($linkUrl)) {
                    $urls[] = $linkUrl;
                }
            }
        }

        // Extract from feed links
        if (!empty($scrapedData['feed_links'])) {
            foreach ($scrapedData['feed_links'] as $feed) {
                $urls[] = $feed['url'];
            }
        }

        return array_unique($urls);
    }

    protected function looksLikeArticleUrl(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH);
        
        if (!$path) {
            return false;
        }

        // Common article URL patterns
        $articlePatterns = [
            '/\/\d{4}\/\d{2}\/\d{2}\//',  // /2023/12/25/
            '/\/article\//',
            '/\/post\//',
            '/\/news\//',
            '/\/blog\//',
            '/\/story\//',
            '/\/\d+-/',  // numeric ID
            '/\w+-\w+-\w+/',  // hyphenated words
        ];

        foreach ($articlePatterns as $pattern) {
            if (preg_match($pattern, $path)) {
                return true;
            }
        }

        // Exclude common non-article paths
        $excludePatterns = [
            '/\/category\//',
            '/\/tag\//',
            '/\/author\//',
            '/\/search\//',
            '/\/page\//',
            '/\/about/',
            '/\/contact/',
            '/\/privacy/',
            '/\/terms/',
        ];

        foreach ($excludePatterns as $pattern) {
            if (preg_match($pattern, $path)) {
                return false;
            }
        }

        // If the path has multiple segments and looks content-like, include it
        $segments = explode('/', trim($path, '/'));
        return count($segments) >= 2 && strlen(end($segments)) > 10;
    }

    public function processContentQuality(Article $article): array
    {
        $quality = [
            'score' => 0,
            'factors' => [],
            'issues' => [],
        ];

        // Check title quality
        if (!empty($article->title)) {
            $titleLength = strlen($article->title);
            if ($titleLength >= 10 && $titleLength <= 200) {
                $quality['score'] += 20;
                $quality['factors'][] = 'Good title length';
            } else {
                $quality['issues'][] = 'Title length issues';
            }
        } else {
            $quality['issues'][] = 'Missing title';
        }

        // Check content quality
        if (!empty($article->content)) {
            $contentLength = strlen($article->content);
            if ($contentLength >= 300) {
                $quality['score'] += 30;
                $quality['factors'][] = 'Substantial content';
                
                if ($contentLength >= 1000) {
                    $quality['score'] += 10;
                    $quality['factors'][] = 'Long-form content';
                }
            } else {
                $quality['issues'][] = 'Content too short';
            }

            // Check for content structure
            if (str_contains($article->content, '.') && str_contains($article->content, ' ')) {
                $quality['score'] += 10;
                $quality['factors'][] = 'Well-structured content';
            }
        } else {
            $quality['issues'][] = 'Missing content';
        }

        // Check author information
        if (!empty($article->author)) {
            $quality['score'] += 10;
            $quality['factors'][] = 'Author information available';
        }

        // Check publication date
        if ($article->published_at) {
            $quality['score'] += 15;
            $quality['factors'][] = 'Publication date available';
        }

        // Check excerpt
        if (!empty($article->excerpt)) {
            $quality['score'] += 5;
            $quality['factors'][] = 'Excerpt available';
        }

        // Check language detection
        if (!empty($article->language)) {
            $quality['score'] += 5;
            $quality['factors'][] = 'Language detected';
        }

        // Check metadata richness
        $metadata = $article->metadata ?? [];
        if (!empty($metadata['meta_description'])) {
            $quality['score'] += 5;
            $quality['factors'][] = 'Meta description present';
        }

        $quality['score'] = min(100, $quality['score']); // Cap at 100

        return $quality;
    }

    public function markAsProcessed(Article $article): void
    {
        $article->update(['is_processed' => true]);
        
        Log::info("Article marked as processed", [
            'article_id' => $article->id,
            'title' => $article->title
        ]);
    }

    public function markAsDuplicate(Article $article): void
    {
        $article->update(['is_duplicate' => true]);
        
        Log::info("Article marked as duplicate", [
            'article_id' => $article->id,
            'title' => $article->title
        ]);
    }
}