<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Source;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PythonCrawlerResultsService
{
    public function __construct(
        private ContentHashService $contentHashService
    ) {}

    /**
     * Import articles from Python crawler JSON output
     */
    public function importFromJson(string $jsonOutput, ?int $sourceId = null): array
    {
        try {
            $data = json_decode($jsonOutput, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Failed to parse Python crawler JSON', [
                    'error' => json_last_error_msg(),
                    'output' => substr($jsonOutput, 0, 500)
                ]);
                return ['imported' => 0, 'skipped' => 0, 'errors' => 1];
            }

            $imported = 0;
            $skipped = 0;
            $errors = 0;

            // Handle both single article and array of articles
            $articles = [];
            if (isset($data['articles']) && is_array($data['articles'])) {
                $articles = $data['articles'];
            } elseif (isset($data['title']) || isset($data['content'])) {
                // Single article format
                $articles = [$data];
            }
            
            foreach ($articles as $articleData) {
                try {
                    $result = $this->importArticle($articleData, $sourceId);
                    
                    if ($result === 'imported') {
                        $imported++;
                    } else {
                        $skipped++;
                    }
                } catch (\Exception $e) {
                    $errors++;
                    Log::error('Failed to import article from Python crawler', [
                        'error' => $e->getMessage(),
                        'article_title' => $articleData['title'] ?? 'Unknown'
                    ]);
                }
            }

            Log::info('Python crawler results imported', [
                'imported' => $imported,
                'skipped' => $skipped,
                'errors' => $errors
            ]);

            return compact('imported', 'skipped', 'errors');
            
        } catch (\Exception $e) {
            Log::error('Failed to import Python crawler results', [
                'error' => $e->getMessage()
            ]);
            return ['imported' => 0, 'skipped' => 0, 'errors' => 1];
        }
    }

    /**
     * Import a single article
     */
    private function importArticle(array $data, ?int $sourceId): string
    {
        // Validate required fields
        if (empty($data['url'])) {
            Log::warning('Skipping article without URL', ['data' => $data]);
            return 'skipped';
        }

        if (empty($data['title']) && empty($data['content'])) {
            Log::warning('Skipping article without title or content', ['url' => $data['url']]);
            return 'skipped';
        }

        // Extract domain from URL
        $domain = parse_url($data['url'], PHP_URL_HOST);
        $domain = preg_replace('/^www\./', '', $domain ?? '');

        // Find or create source
        if (!$sourceId) {
            $source = Source::firstOrCreate(
                ['domain' => $domain],
                [
                    'name' => $data['source_name'] ?? ucfirst($domain),
                    'url' => $data['source_url'] ?? 'https://' . $domain,
                    'is_active' => true,
                    'credibility_score' => 0.5 // Default neutral score
                ]
            );
            $sourceId = $source->id;
        }

        // Check if article already exists by URL
        $exists = Article::where('url', $data['url'])->exists();
        
        if ($exists) {
            return 'skipped';
        }

        // Generate content hash for deduplication
        $content = $data['content'] ?? $data['text'] ?? '';
        if (strlen($content) < 50) {
            Log::warning('Skipping article with insufficient content', [
                'url' => $data['url'],
                'content_length' => strlen($content)
            ]);
            return 'skipped';
        }

        $contentHash = $this->contentHashService->generateHash($content);

        // Parse published date
        $publishedAt = null;
        if (!empty($data['published_at']) || !empty($data['publish_date'])) {
            try {
                $dateString = $data['published_at'] ?? $data['publish_date'];
                $publishedAt = Carbon::parse($dateString);
            } catch (\Exception $e) {
                Log::debug('Failed to parse published_at', [
                    'value' => $dateString ?? 'null'
                ]);
            }
        }

        // Extract excerpt
        $excerpt = $data['summary'] ?? $data['excerpt'] ?? null;
        if (!$excerpt && $content) {
            $excerpt = substr(strip_tags($content), 0, 200) . '...';
        }

        // Parse authors
        $authors = null;
        if (!empty($data['authors'])) {
            $authors = is_array($data['authors']) ? implode(', ', $data['authors']) : $data['authors'];
        } elseif (!empty($data['author'])) {
            $authors = $data['author'];
        }

        // Create article
        Article::create([
            'source_id' => $sourceId,
            'title' => $data['title'] ?? 'Untitled Article',
            'url' => $data['url'],
            'content' => $content,
            'excerpt' => $excerpt,
            'content_hash' => $contentHash,
            'authors' => $authors,
            'published_at' => $publishedAt ?? now(),
            'crawled_at' => now(),
            'language' => $data['language'] ?? 'en',
            'word_count' => str_word_count(strip_tags($content)),
            'metadata' => [
                'keywords' => $data['keywords'] ?? [],
                'summary' => $data['summary'] ?? null,
                'top_image' => $data['top_image'] ?? null,
                'images' => $data['images'] ?? [],
                'videos' => $data['videos'] ?? [],
                'meta_description' => $data['meta_description'] ?? null,
                'scraped_at' => $data['scraped_at'] ?? now()->toISOString(),
            ],
            'is_processed' => false,
            'is_duplicate' => false,
        ]);

        Log::info('Article imported from Python crawler', [
            'url' => $data['url'],
            'title' => $data['title'] ?? 'Untitled'
        ]);

        return 'imported';
    }

    /**
     * Import results from multiple crawl attempts
     */
    public function importBatch(array $results, ?int $sourceId = null): array
    {
        $totalImported = 0;
        $totalSkipped = 0;
        $totalErrors = 0;

        foreach ($results as $result) {
            if (empty($result['data']) && empty($result['raw_output'])) {
                continue;
            }

            $output = $result['raw_output'] ?? json_encode($result['data']);
            $stats = $this->importFromJson($output, $sourceId);

            $totalImported += $stats['imported'];
            $totalSkipped += $stats['skipped'];
            $totalErrors += $stats['errors'];
        }

        return [
            'imported' => $totalImported,
            'skipped' => $totalSkipped,
            'errors' => $totalErrors
        ];
    }
}
