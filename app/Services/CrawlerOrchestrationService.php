<?php

namespace App\Services;

use App\Models\Article;
use App\Models\CrawlJob;
use App\Models\Source;
use Exception;
use Illuminate\Support\Facades\Log;

class CrawlerOrchestrationService
{
    protected CrawlJobService $crawlJobService;

    protected WebScraperService $webScraperService;

    protected ContentExtractionService $contentExtractionService;

    protected ContentHashService $contentHashService;

    public function __construct(
        CrawlJobService $crawlJobService,
        WebScraperService $webScraperService,
        ContentExtractionService $contentExtractionService,
        ContentHashService $contentHashService
    ) {
        $this->crawlJobService = $crawlJobService;
        $this->webScraperService = $webScraperService;
        $this->contentExtractionService = $contentExtractionService;
        $this->contentHashService = $contentHashService;
    }

    public function processCrawlJob(CrawlJob $crawlJob): array
    {
        $result = [
            'success' => false,
            'articles_created' => 0,
            'urls_discovered' => 0,
            'error' => null,
        ];

        try {
            Log::info('Processing crawl job', [
                'crawl_job_id' => $crawlJob->id,
                'url' => $crawlJob->url,
            ]);

            // Mark job as running
            $this->crawlJobService->markJobAsRunning($crawlJob);

            // Scrape the URL
            $scrapeResult = $this->webScraperService->scrapeUrl($crawlJob->url);

            if (! $scrapeResult['success']) {
                throw new Exception('Scraping failed: '.$scrapeResult['error']);
            }

            $scrapedData = $scrapeResult['data'];

            // Process the main content
            $article = $this->contentExtractionService->processScrapedContent(
                $scrapedData,
                $crawlJob->source
            );

            if ($article) {
                $result['articles_created'] = 1;

                // Extract additional URLs for future crawling
                $discoveredUrls = $this->contentExtractionService->extractArticleUrls(
                    $scrapedData,
                    $crawlJob->source
                );

                if (! empty($discoveredUrls)) {
                    $this->queueDiscoveredUrls($discoveredUrls, $crawlJob->source);
                    $result['urls_discovered'] = count($discoveredUrls);
                }
            }

            // Handle special URL types
            if ($this->isSitemapUrl($crawlJob->url)) {
                $sitemapUrls = $this->processSitemap($crawlJob->url, $crawlJob->source);
                $result['urls_discovered'] += $sitemapUrls;
            }

            $result['success'] = true;

            // Mark job as completed
            $this->crawlJobService->markJobAsCompleted($crawlJob, [
                'articles_created' => $result['articles_created'],
                'urls_discovered' => $result['urls_discovered'],
                'content_length' => strlen($scrapedData['content'] ?? ''),
                'title' => $scrapedData['title'] ?? null,
            ]);

            Log::info('Crawl job completed successfully', [
                'crawl_job_id' => $crawlJob->id,
                'articles_created' => $result['articles_created'],
                'urls_discovered' => $result['urls_discovered'],
            ]);

        } catch (Exception $e) {
            $result['error'] = $e->getMessage();

            $this->crawlJobService->markJobAsFailed($crawlJob, $e->getMessage());

            Log::error('Crawl job failed', [
                'crawl_job_id' => $crawlJob->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $result;
    }

    public function crawlSource(Source $source, array $options = []): array
    {
        $results = [
            'jobs_created' => 0,
            'jobs_processed' => 0,
            'articles_created' => 0,
            'urls_discovered' => 0,
            'errors' => [],
        ];

        try {
            Log::info('Starting source crawl', [
                'source_id' => $source->id,
                'domain' => $source->domain,
            ]);

            // Generate initial crawl URLs
            $initialUrls = $this->generateInitialCrawlUrls($source);

            // Create crawl jobs
            $jobs = $this->crawlJobService->createBulkCrawlJobs($source, $initialUrls, $options);
            $results['jobs_created'] = $jobs->count();

            // Process jobs if immediate processing is requested
            if ($options['process_immediately'] ?? false) {
                foreach ($jobs as $job) {
                    $jobResult = $this->processCrawlJob($job);
                    $results['jobs_processed']++;
                    $results['articles_created'] += $jobResult['articles_created'];
                    $results['urls_discovered'] += $jobResult['urls_discovered'];

                    if (! $jobResult['success']) {
                        $results['errors'][] = $jobResult['error'];
                    }
                }
            }

            // Update source's last crawled timestamp
            $source->update(['last_crawled_at' => now()]);

            Log::info('Source crawl completed', [
                'source_id' => $source->id,
                'results' => $results,
            ]);

        } catch (Exception $e) {
            $results['errors'][] = $e->getMessage();

            Log::error('Source crawl failed', [
                'source_id' => $source->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $results;
    }

    public function processNextPendingJob(): ?array
    {
        $job = $this->crawlJobService->getNextPendingJob();

        if (! $job) {
            return null;
        }

        return $this->processCrawlJob($job);
    }

    public function processPendingJobs(int $limit = 10): array
    {
        $jobs = $this->crawlJobService->getPendingJobs($limit);
        $results = [
            'jobs_processed' => 0,
            'articles_created' => 0,
            'urls_discovered' => 0,
            'errors' => [],
        ];

        foreach ($jobs as $job) {
            $jobResult = $this->processCrawlJob($job);
            $results['jobs_processed']++;
            $results['articles_created'] += $jobResult['articles_created'];
            $results['urls_discovered'] += $jobResult['urls_discovered'];

            if (! $jobResult['success']) {
                $results['errors'][] = $jobResult['error'];
            }
        }

        return $results;
    }

    public function indexContent(Article $article): void
    {
        try {
            Log::info('Indexing content', [
                'article_id' => $article->id,
                'title' => $article->title,
            ]);

            // Generate content hash if not already done
            if (! $article->contentHash) {
                $this->contentHashService->generateHash($article);
            }

            // Check for duplicates based on content hash
            $duplicates = $this->contentHashService->findSimilarContent($article);

            if ($duplicates->isNotEmpty()) {
                $this->contentExtractionService->markAsDuplicate($article);
                Log::info('Article marked as duplicate during indexing', [
                    'article_id' => $article->id,
                    'duplicate_count' => $duplicates->count(),
                ]);

                return;
            }

            // Perform content quality analysis
            $qualityAnalysis = $this->contentExtractionService->processContentQuality($article);

            // Update article metadata with quality info
            $metadata = $article->metadata ?? [];
            $metadata['quality_analysis'] = $qualityAnalysis;
            $article->update(['metadata' => $metadata]);

            // Mark as processed
            $this->contentExtractionService->markAsProcessed($article);

            Log::info('Content indexed successfully', [
                'article_id' => $article->id,
                'quality_score' => $qualityAnalysis['score'],
            ]);

        } catch (Exception $e) {
            Log::error('Content indexing failed', [
                'article_id' => $article->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function indexAllUnprocessedContent(): array
    {
        $articles = Article::where('is_processed', false)
            ->where('is_duplicate', false)
            ->limit(100)
            ->get();

        $results = [
            'processed' => 0,
            'errors' => 0,
        ];

        foreach ($articles as $article) {
            try {
                $this->indexContent($article);
                $results['processed']++;
            } catch (Exception $e) {
                $results['errors']++;
                Log::error('Failed to index article', [
                    'article_id' => $article->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    public function crawlAllActiveSources(): array
    {
        $sources = Source::where('is_active', true)->get();
        $results = [
            'sources_crawled' => 0,
            'total_jobs_created' => 0,
            'errors' => [],
        ];

        foreach ($sources as $source) {
            try {
                $sourceResult = $this->crawlSource($source, ['process_immediately' => false]);
                $results['sources_crawled']++;
                $results['total_jobs_created'] += $sourceResult['jobs_created'];

                if (! empty($sourceResult['errors'])) {
                    $results['errors'] = array_merge($results['errors'], $sourceResult['errors']);
                }

            } catch (Exception $e) {
                $results['errors'][] = "Source {$source->domain}: ".$e->getMessage();
            }
        }

        return $results;
    }

    protected function generateInitialCrawlUrls(Source $source): array
    {
        $urls = [$source->url];

        // Add common paths that might contain content
        $commonPaths = [
            '/sitemap.xml',
            '/sitemap.txt',
            '/robots.txt',
            '/feed',
            '/rss',
            '/atom.xml',
            '/news',
            '/blog',
            '/articles',
            '/posts',
        ];

        $baseUrl = rtrim($source->url, '/');

        foreach ($commonPaths as $path) {
            $urls[] = $baseUrl.$path;
        }

        return $urls;
    }

    protected function queueDiscoveredUrls(array $urls, Source $source): void
    {
        $filteredUrls = $this->filterDiscoveredUrls($urls, $source);

        if (! empty($filteredUrls)) {
            $this->crawlJobService->createBulkCrawlJobs($source, $filteredUrls, [
                'priority' => -1, // Lower priority for discovered URLs
                'metadata' => ['discovered' => true],
            ]);

            Log::info('Queued discovered URLs', [
                'source_id' => $source->id,
                'url_count' => count($filteredUrls),
            ]);
        }
    }

    protected function filterDiscoveredUrls(array $urls, Source $source): array
    {
        $filtered = [];
        $maxUrls = 50; // Limit to prevent spam
        $count = 0;

        foreach ($urls as $url) {
            if ($count >= $maxUrls) {
                break;
            }

            // Skip if URL is too long or looks suspicious
            if (strlen($url) > 500 || $this->isSuspiciousUrl($url)) {
                continue;
            }

            // Skip if we already have a job for this URL
            if ($this->crawlJobService->jobExistsForUrl($url)) {
                continue;
            }

            $filtered[] = $url;
            $count++;
        }

        return $filtered;
    }

    protected function isSuspiciousUrl(string $url): bool
    {
        $suspicious = [
            'javascript:',
            'mailto:',
            'tel:',
            'ftp:',
            '#',
            'data:',
        ];

        foreach ($suspicious as $pattern) {
            if (str_starts_with($url, $pattern)) {
                return true;
            }
        }

        // Check for too many query parameters
        $parsed = parse_url($url);
        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $params);
            if (count($params) > 10) {
                return true;
            }
        }

        return false;
    }

    protected function isSitemapUrl(string $url): bool
    {
        return str_contains(strtolower($url), 'sitemap');
    }

    protected function processSitemap(string $sitemapUrl, Source $source): int
    {
        try {
            $sitemapResult = $this->webScraperService->scrapeSitemap($sitemapUrl);

            if ($sitemapResult['success']) {
                $urls = array_slice($sitemapResult['urls'], 0, 100); // Limit sitemap URLs
                $this->queueDiscoveredUrls($urls, $source);

                Log::info('Sitemap processed', [
                    'source_id' => $source->id,
                    'sitemap_url' => $sitemapUrl,
                    'urls_found' => count($urls),
                ]);

                return count($urls);
            }
        } catch (Exception $e) {
            Log::warning('Sitemap processing failed', [
                'sitemap_url' => $sitemapUrl,
                'error' => $e->getMessage(),
            ]);
        }

        return 0;
    }

    public function getSystemStats(): array
    {
        $crawlStats = $this->crawlJobService->getCrawlJobStats();

        $contentStats = [
            'total_articles' => Article::count(),
            'processed_articles' => Article::where('is_processed', true)->count(),
            'duplicate_articles' => Article::where('is_duplicate', true)->count(),
            'articles_with_hashes' => Article::whereHas('contentHash')->count(),
        ];

        $sourceStats = [
            'total_sources' => Source::count(),
            'active_sources' => Source::where('is_active', true)->count(),
            'verified_sources' => Source::where('is_verified', true)->count(),
        ];

        return [
            'crawl_jobs' => $crawlStats,
            'content' => $contentStats,
            'sources' => $sourceStats,
            'system_health' => $this->calculateSystemHealth($crawlStats, $contentStats),
        ];
    }

    protected function calculateSystemHealth(array $crawlStats, array $contentStats): array
    {
        $health = ['status' => 'healthy', 'issues' => []];

        // Check if there are too many failed jobs
        if ($crawlStats['failed'] > 0 && $crawlStats['total'] > 0) {
            $failureRate = ($crawlStats['failed'] / $crawlStats['total']) * 100;
            if ($failureRate > 20) {
                $health['status'] = 'degraded';
                $health['issues'][] = "High failure rate: {$failureRate}%";
            }
        }

        // Check if there are too many pending jobs
        if ($crawlStats['pending'] > 1000) {
            $health['status'] = 'degraded';
            $health['issues'][] = "High number of pending jobs: {$crawlStats['pending']}";
        }

        // Check processing rate
        if ($contentStats['total_articles'] > 0) {
            $processingRate = ($contentStats['processed_articles'] / $contentStats['total_articles']) * 100;
            if ($processingRate < 80) {
                $health['status'] = 'degraded';
                $health['issues'][] = "Low processing rate: {$processingRate}%";
            }
        }

        return $health;
    }
}
