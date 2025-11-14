<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Source;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

class SearchOrchestrationService
{
    protected MeilisearchService $meilisearch;

    protected QdrantService $qdrant;

    protected ContentMatchingService $contentMatching;

    protected array $config;

    public function __construct(
        MeilisearchService $meilisearch,
        QdrantService $qdrant,
        ContentMatchingService $contentMatching
    ) {
        $this->meilisearch = $meilisearch;
        $this->qdrant = $qdrant;
        $this->contentMatching = $contentMatching;
        $this->config = config('verifysource.search');
    }

    /**
     * Initialize the entire search system
     */
    public function initializeSearchSystem(): array
    {
        $results = [
            'meilisearch' => ['available' => false],
            'qdrant' => ['available' => false],
            'initialization' => [],
            'success' => false,
        ];

        try {
            // Check service availability
            $results['meilisearch'] = $this->meilisearch->getServerInfo();
            $results['qdrant'] = $this->qdrant->getServerInfo();

            $meilisearchReady = $results['meilisearch']['available'] ?? false;
            $qdrantReady = $results['qdrant']['available'] ?? false;

            if (! $meilisearchReady && ! $qdrantReady) {
                throw new Exception('Neither Meilisearch nor Qdrant are available');
            }

            // Initialize indices/collections
            if ($meilisearchReady) {
                $results['initialization']['meilisearch'] = $this->meilisearch->initializeIndices();
            }

            if ($qdrantReady) {
                $results['initialization']['qdrant'] = $this->qdrant->initializeCollections();
            }

            $results['success'] = true;
            $results['message'] = 'Search system initialized successfully';

            return $results;

        } catch (Exception $e) {
            Log::error('Failed to initialize search system: '.$e->getMessage());

            $results['error'] = $e->getMessage();
            $results['success'] = false;

            return $results;
        }
    }

    /**
     * Index all content into both search engines
     */
    public function indexAllContent(): array
    {
        $results = [
            'articles' => ['meilisearch' => null, 'qdrant' => null],
            'sources' => ['meilisearch' => null],
            'success' => false,
            'total_indexed' => 0,
        ];

        try {
            // Index articles
            if ($this->meilisearch->isAvailable()) {
                $results['articles']['meilisearch'] = $this->meilisearch->indexArticles();

                // Also index sources in Meilisearch
                $results['sources']['meilisearch'] = $this->meilisearch->indexSources();
            }

            if ($this->qdrant->isAvailable()) {
                $results['articles']['qdrant'] = $this->qdrant->indexArticles();
            }

            // Calculate total indexed
            $totalIndexed = 0;
            if ($results['articles']['meilisearch']['success'] ?? false) {
                $totalIndexed += $results['articles']['meilisearch']['indexed'];
            }
            if ($results['articles']['qdrant']['success'] ?? false) {
                $totalIndexed += $results['articles']['qdrant']['indexed'];
            }

            $results['total_indexed'] = $totalIndexed;
            $results['success'] = $totalIndexed > 0;

            return $results;

        } catch (Exception $e) {
            Log::error('Failed to index all content: '.$e->getMessage());

            $results['error'] = $e->getMessage();

            return $results;
        }
    }

    /**
     * Index specific articles
     */
    public function indexArticles(array $articleIds): array
    {
        $results = [
            'meilisearch' => null,
            'qdrant' => null,
            'success' => false,
            'indexed_count' => 0,
        ];

        try {
            if ($this->meilisearch->isAvailable()) {
                $results['meilisearch'] = $this->meilisearch->indexArticles($articleIds);
            }

            if ($this->qdrant->isAvailable()) {
                $results['qdrant'] = $this->qdrant->indexArticles($articleIds);
            }

            $indexed = 0;
            if ($results['meilisearch']['success'] ?? false) {
                $indexed = $results['meilisearch']['indexed'];
            }
            if ($results['qdrant']['success'] ?? false) {
                $indexed = max($indexed, $results['qdrant']['indexed']);
            }

            $results['indexed_count'] = $indexed;
            $results['success'] = $indexed > 0;

            return $results;

        } catch (Exception $e) {
            Log::error('Failed to index articles: '.$e->getMessage());

            $results['error'] = $e->getMessage();

            return $results;
        }
    }

    /**
     * Index a single article in both search engines
     */
    public function indexArticle(Article $article): array
    {
        $results = [
            'article_id' => $article->id,
            'meilisearch' => null,
            'qdrant' => null,
            'success' => false,
        ];

        try {
            if ($this->meilisearch->isAvailable()) {
                $results['meilisearch'] = $this->meilisearch->updateArticle($article);
            }

            if ($this->qdrant->isAvailable()) {
                $results['qdrant'] = $this->qdrant->updateArticle($article);
            }

            $results['success'] =
                ($results['meilisearch']['success'] ?? false) ||
                ($results['qdrant']['success'] ?? false);

            return $results;

        } catch (Exception $e) {
            Log::error("Failed to index article {$article->id}: ".$e->getMessage());

            $results['error'] = $e->getMessage();

            return $results;
        }
    }

    /**
     * Queue indexing jobs for large batches
     */
    public function queueIndexingJobs(array $articleIds, int $batchSize = 100): array
    {
        $batches = array_chunk($articleIds, $batchSize);
        $queuedJobs = 0;

        foreach ($batches as $batch) {
            // Queue job for this batch
            Queue::push('IndexArticleBatch', [
                'article_ids' => $batch,
                'services' => ['meilisearch', 'qdrant'],
            ]);

            $queuedJobs++;
        }

        return [
            'success' => true,
            'total_articles' => count($articleIds),
            'batches' => count($batches),
            'batch_size' => $batchSize,
            'queued_jobs' => $queuedJobs,
        ];
    }

    /**
     * Remove articles from search indices
     */
    public function removeArticles(array $articleIds): array
    {
        $results = [
            'meilisearch' => null,
            'qdrant' => null,
            'success' => false,
            'removed_count' => 0,
        ];

        try {
            if ($this->meilisearch->isAvailable()) {
                $results['meilisearch'] = $this->meilisearch->deleteArticles($articleIds);
            }

            if ($this->qdrant->isAvailable()) {
                $results['qdrant'] = $this->qdrant->deleteArticles($articleIds);
            }

            $removed = 0;
            if ($results['meilisearch']['success'] ?? false) {
                $removed = $results['meilisearch']['deleted'];
            }
            if ($results['qdrant']['success'] ?? false) {
                $removed = max($removed, $results['qdrant']['deleted']);
            }

            $results['removed_count'] = $removed;
            $results['success'] = $removed > 0;

            return $results;

        } catch (Exception $e) {
            Log::error('Failed to remove articles from search indices: '.$e->getMessage());

            $results['error'] = $e->getMessage();

            return $results;
        }
    }

    /**
     * Perform comprehensive content search
     */
    public function searchContent(string $query, array $options = []): array
    {
        return $this->contentMatching->findMatches($query, $options);
    }

    /**
     * Check for duplicate content
     */
    public function checkForDuplicates(string $content, ?float $threshold = null): array
    {
        return $this->contentMatching->isDuplicateContent($content, $threshold);
    }

    /**
     * Find the original source of content
     */
    public function findOriginalSource(string $content): array
    {
        return $this->contentMatching->findContentSource($content);
    }

    /**
     * Get comprehensive search statistics
     */
    public function getSearchStatistics(): array
    {
        $stats = [
            'system_status' => [
                'meilisearch_available' => $this->meilisearch->isAvailable(),
                'qdrant_available' => $this->qdrant->isAvailable(),
                'default_engine' => $this->config['default_engine'],
            ],
            'meilisearch' => null,
            'qdrant' => null,
            'content_statistics' => $this->getContentStatistics(),
        ];

        // Get detailed service statistics
        if ($stats['system_status']['meilisearch_available']) {
            $stats['meilisearch'] = $this->meilisearch->getServerInfo();
        }

        if ($stats['system_status']['qdrant_available']) {
            $stats['qdrant'] = $this->qdrant->getServerInfo();
        }

        return $stats;
    }

    /**
     * Optimize search indices
     */
    public function optimizeIndices(): array
    {
        $results = [
            'meilisearch' => null,
            'qdrant' => null,
            'success' => false,
        ];

        try {
            // For Meilisearch, we can trigger index optimization tasks
            if ($this->meilisearch->isAvailable()) {
                // Meilisearch automatically optimizes, but we can force re-indexing
                $results['meilisearch'] = [
                    'message' => 'Meilisearch automatically optimizes indices',
                    'available' => true,
                ];
            }

            // For Qdrant, optimization is automatic but we can check collection status
            if ($this->qdrant->isAvailable()) {
                $collectionsInfo = $this->qdrant->getCollectionsInfo();
                $results['qdrant'] = [
                    'collections' => count($collectionsInfo),
                    'message' => 'Qdrant automatically optimizes collections',
                    'available' => true,
                ];
            }

            $results['success'] =
                ($results['meilisearch']['available'] ?? false) ||
                ($results['qdrant']['available'] ?? false);

            return $results;

        } catch (Exception $e) {
            Log::error('Failed to optimize indices: '.$e->getMessage());

            $results['error'] = $e->getMessage();

            return $results;
        }
    }

    /**
     * Clear all search indices
     */
    public function clearAllIndices(): array
    {
        $results = [
            'meilisearch' => null,
            'qdrant' => null,
            'success' => false,
        ];

        try {
            if ($this->meilisearch->isAvailable()) {
                $results['meilisearch'] = [
                    'articles' => $this->meilisearch->clearIndex('articles'),
                    'sources' => $this->meilisearch->clearIndex('sources'),
                ];
            }

            if ($this->qdrant->isAvailable()) {
                $collectionName = $this->config['qdrant']['collections']['articles']['name'];
                $results['qdrant'] = $this->qdrant->clearCollection($collectionName);
            }

            $results['success'] = true;
            $results['message'] = 'All search indices cleared successfully';

            return $results;

        } catch (Exception $e) {
            Log::error('Failed to clear search indices: '.$e->getMessage());

            $results['error'] = $e->getMessage();
            $results['success'] = false;

            return $results;
        }
    }

    /**
     * Synchronize search indices with database
     */
    public function synchronizeIndices(): array
    {
        $results = [
            'database_count' => 0,
            'meilisearch_count' => 0,
            'qdrant_count' => 0,
            'articles_to_index' => [],
            'articles_to_remove' => [],
            'synchronized' => false,
        ];

        try {
            // Get articles from database
            $dbArticles = Article::select('id', 'updated_at')->get()->keyBy('id');
            $results['database_count'] = $dbArticles->count();

            // Get indexed article IDs from Meilisearch
            $meilisearchArticles = [];
            if ($this->meilisearch->isAvailable()) {
                $meilisearchInfo = $this->meilisearch->getIndicesInfo();
                $articlesIndex = $meilisearchInfo[$this->config['meilisearch']['index_prefix'].'articles'] ?? null;
                $results['meilisearch_count'] = $articlesIndex['number_of_documents'] ?? 0;
            }

            // Get indexed article IDs from Qdrant
            if ($this->qdrant->isAvailable()) {
                $qdrantInfo = $this->qdrant->getCollectionsInfo();
                $collectionName = $this->config['qdrant']['collections']['articles']['name'];
                $results['qdrant_count'] = $qdrantInfo[$collectionName]['points_count'] ?? 0;
            }

            // For now, we'll re-index all articles to ensure sync
            // In a production system, you'd want more sophisticated sync logic
            $results['articles_to_index'] = $dbArticles->keys()->toArray();

            $results['synchronized'] = true;
            $results['message'] = 'Index synchronization analysis complete';

            return $results;

        } catch (Exception $e) {
            Log::error('Failed to synchronize indices: '.$e->getMessage());

            $results['error'] = $e->getMessage();

            return $results;
        }
    }

    /**
     * Get content statistics for dashboard
     */
    protected function getContentStatistics(): array
    {
        try {
            $totalArticles = Article::count();
            $recentArticles = Article::where('created_at', '>=', now()->subDays(7))->count();
            $sourcesCount = Source::count();
            $activeSources = Source::where('is_active', true)->count();

            return [
                'total_articles' => $totalArticles,
                'recent_articles' => $recentArticles,
                'total_sources' => $sourcesCount,
                'active_sources' => $activeSources,
                'indexing_rate' => $recentArticles > 0 ? round($recentArticles / 7, 2) : 0,
            ];

        } catch (Exception $e) {
            Log::error('Failed to get content statistics: '.$e->getMessage());

            return [
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Health check for the entire search system
     */
    public function healthCheck(): array
    {
        $health = [
            'overall_status' => 'healthy',
            'services' => [],
            'issues' => [],
            'recommendations' => [],
        ];

        // Check Meilisearch
        if ($this->meilisearch->isAvailable()) {
            $meilisearchInfo = $this->meilisearch->getServerInfo();
            $health['services']['meilisearch'] = [
                'status' => 'healthy',
                'version' => $meilisearchInfo['version'] ?? 'unknown',
                'indices' => count($meilisearchInfo['indices'] ?? []),
            ];
        } else {
            $health['services']['meilisearch'] = ['status' => 'unavailable'];
            $health['issues'][] = 'Meilisearch service is not available';
            $health['overall_status'] = 'degraded';
        }

        // Check Qdrant
        if ($this->qdrant->isAvailable()) {
            $qdrantInfo = $this->qdrant->getServerInfo();
            $health['services']['qdrant'] = [
                'status' => 'healthy',
                'version' => $qdrantInfo['version'] ?? 'unknown',
                'collections' => count($qdrantInfo['collections'] ?? []),
            ];
        } else {
            $health['services']['qdrant'] = ['status' => 'unavailable'];
            $health['issues'][] = 'Qdrant service is not available';
            $health['overall_status'] = 'degraded';
        }

        // Check if no services are available
        if (! $this->meilisearch->isAvailable() && ! $this->qdrant->isAvailable()) {
            $health['overall_status'] = 'unhealthy';
            $health['issues'][] = 'No search services are available';
        }

        // Add recommendations
        if ($health['overall_status'] !== 'healthy') {
            $health['recommendations'][] = 'Ensure search services are running and accessible';
            $health['recommendations'][] = 'Check service configuration in verifysource.php';
        }

        return $health;
    }
}
