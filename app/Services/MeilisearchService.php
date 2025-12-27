<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Source;
use Exception;
use Illuminate\Support\Facades\Log;
use MeiliSearch\Client;
use MeiliSearch\Endpoints\Indexes;
use MeiliSearch\Exceptions\MeiliSearchApiException;

class MeilisearchService
{
    protected Client $client;

    protected array $config;

    protected string $indexPrefix;

    public function __construct()
    {
        $this->config = config('verifysource.search.meilisearch');
        $this->indexPrefix = $this->config['index_prefix'];

        $this->client = new Client(
            $this->config['host'],
            $this->config['key'] ?: null
        );
    }

    /**
     * Check if Meilisearch is available
     */
    public function isAvailable(): bool
    {
        try {
            $this->client->health();

            return true;
        } catch (Exception $e) {
            Log::warning('Meilisearch not available: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Get Meilisearch server info
     */
    public function getServerInfo(): array
    {
        try {
            $health = $this->client->health();
            $version = $this->client->version();
            $stats = $this->client->stats();

            return [
                'available' => true,
                'health' => $health,
                'version' => $version,
                'stats' => $stats,
                'indices' => $this->getIndicesInfo(),
            ];

        } catch (Exception $e) {
            return [
                'available' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Initialize all indices with proper configuration
     */
    public function initializeIndices(): array
    {
        $results = [];
        $indicesConfig = $this->config['indices'];

        foreach ($indicesConfig as $indexName => $indexConfig) {
            try {
                $results[$indexName] = $this->createOrUpdateIndex($indexName, $indexConfig);
            } catch (Exception $e) {
                $results[$indexName] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Create or update an index with configuration
     */
    public function createOrUpdateIndex(string $indexName, array $config): array
    {
        $fullIndexName = $this->indexPrefix.$indexName;

        try {
            // Create index if it doesn't exist
            $index = $this->getOrCreateIndex($fullIndexName, $config['primary_key']);

            // Configure searchable attributes
            if (isset($config['searchable_attributes'])) {
                $index->updateSearchableAttributes($config['searchable_attributes']);
            }

            // Configure filterable attributes
            if (isset($config['filterable_attributes'])) {
                $index->updateFilterableAttributes($config['filterable_attributes']);
            }

            // Configure sortable attributes
            if (isset($config['sortable_attributes'])) {
                $index->updateSortableAttributes($config['sortable_attributes']);
            }

            // Configure ranking rules
            if (isset($config['ranking_rules'])) {
                $index->updateRankingRules($config['ranking_rules']);
            }

            // Configure stop words
            if (isset($config['stop_words'])) {
                $index->updateStopWords($config['stop_words']);
            }

            // Configure synonyms
            if (isset($config['synonyms']) && ! empty($config['synonyms'])) {
                $index->updateSynonyms($config['synonyms']);
            }

            return [
                'success' => true,
                'index' => $fullIndexName,
                'stats' => $index->stats(),
            ];

        } catch (Exception $e) {
            Log::error("Failed to create/update index {$fullIndexName}: ".$e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get or create an index
     */
    protected function getOrCreateIndex(string $indexName, string $primaryKey): Indexes
    {
        try {
            return $this->client->index($indexName);
        } catch (MeiliSearchApiException $e) {
            // Index doesn't exist, create it
            if ($e->getCode() === 'index_not_found') {
                $this->client->createIndex($indexName, ['primaryKey' => $primaryKey]);

                return $this->client->index($indexName);
            }

            throw $e;
        }
    }

    /**
     * Index articles for search
     */
    public function indexArticles(array $articleIds = []): array
    {
        $indexName = $this->indexPrefix.'articles';

        try {
            $query = Article::with('source')
                ->where('content', '!=', '')
                ->where('title', '!=', '');

            if (! empty($articleIds)) {
                $query->whereIn('id', $articleIds);
            }

            $articles = $query->get();
            $documents = [];

            foreach ($articles as $article) {
                $documents[] = $this->prepareArticleDocument($article);
            }

            if (empty($documents)) {
                return ['success' => true, 'indexed' => 0];
            }

            $index = $this->client->index($indexName);
            $task = $index->addDocuments($documents);

            return [
                'success' => true,
                'indexed' => count($documents),
                'task_id' => $task['taskUid'],
            ];

        } catch (Exception $e) {
            Log::error('Failed to index articles in Meilisearch: '.$e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Index sources for search
     */
    public function indexSources(array $sourceIds = []): array
    {
        $indexName = $this->indexPrefix.'sources';

        try {
            $query = Source::where('name', '!=', '');

            if (! empty($sourceIds)) {
                $query->whereIn('id', $sourceIds);
            }

            $sources = $query->get();
            $documents = [];

            foreach ($sources as $source) {
                $documents[] = $this->prepareSourceDocument($source);
            }

            if (empty($documents)) {
                return ['success' => true, 'indexed' => 0];
            }

            $index = $this->client->index($indexName);
            $task = $index->addDocuments($documents);

            return [
                'success' => true,
                'indexed' => count($documents),
                'task_id' => $task['taskUid'],
            ];

        } catch (Exception $e) {
            Log::error('Failed to index sources in Meilisearch: '.$e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Search articles with full-text search
     */
    public function searchArticles(string $query, array $options = []): array
    {
        $indexName = $this->indexPrefix.'articles';

        try {
            $searchOptions = array_merge([
                'limit' => config('verifysource.search.options.default_limit', 20),
                'offset' => 0,
                'attributesToRetrieve' => ['*'],
                'attributesToHighlight' => ['title', 'content', 'excerpt'],
                'highlightPreTag' => '<mark>',
                'highlightPostTag' => '</mark>',
                'cropLength' => 200,
                'sort' => ['published_at:desc', 'quality_score:desc'],
            ], $options);

            $index = $this->client->index($indexName);
            $results = $index->search($query, $searchOptions);

            return [
                'success' => true,
                'query' => $query,
                'results' => $results->getHits(),
                'total' => $results->getEstimatedTotalHits(),
                'processing_time' => $results->getProcessingTimeMs(),
                'facet_distribution' => $results->getFacetDistribution(),
            ];

        } catch (Exception $e) {
            Log::error('Failed to search articles in Meilisearch: '.$e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Search sources
     */
    public function searchSources(string $query, array $options = []): array
    {
        $indexName = $this->indexPrefix.'sources';

        try {
            $searchOptions = array_merge([
                'limit' => 20,
                'offset' => 0,
                'attributesToRetrieve' => ['*'],
                'attributesToHighlight' => ['name', 'description'],
                'sort' => ['credibility_score:desc'],
            ], $options);

            $index = $this->client->index($indexName);
            $results = $index->search($query, $searchOptions);

            return [
                'success' => true,
                'query' => $query,
                'results' => $results->getHits(),
                'total' => $results->getEstimatedTotalHits(),
                'processing_time' => $results->getProcessingTimeMs(),
            ];

        } catch (Exception $e) {
            Log::error('Failed to search sources in Meilisearch: '.$e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Find similar articles by content
     */
    public function findSimilarArticles(string $content, array $options = []): array
    {
        // Extract key phrases for similarity search
        $searchTerms = $this->extractKeyPhrases($content);

        $searchOptions = array_merge([
            'limit' => 10,
            'attributesToRetrieve' => ['id', 'title', 'url', 'source_id', 'published_at', 'quality_score'],
            'filter' => 'quality_score > 50',
        ], $options);

        return $this->searchArticles($searchTerms, $searchOptions);
    }

    /**
     * Update a single article in the index
     */
    public function updateArticle(Article $article): array
    {
        $indexName = $this->indexPrefix.'articles';

        try {
            $document = $this->prepareArticleDocument($article);

            $index = $this->client->index($indexName);
            $task = $index->addDocuments([$document]);

            return [
                'success' => true,
                'task_id' => $task['taskUid'],
            ];

        } catch (Exception $e) {
            Log::error("Failed to update article {$article->id} in Meilisearch: ".$e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Delete articles from index
     */
    public function deleteArticles(array $articleIds): array
    {
        $indexName = $this->indexPrefix.'articles';

        try {
            $index = $this->client->index($indexName);
            $task = $index->deleteDocuments($articleIds);

            return [
                'success' => true,
                'deleted' => count($articleIds),
                'task_id' => $task['taskUid'],
            ];

        } catch (Exception $e) {
            Log::error('Failed to delete articles from Meilisearch: '.$e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get index statistics
     */
    public function getIndicesInfo(): array
    {
        try {
            $indices = $this->client->getIndexes();
            $info = [];

            foreach ($indices->getResults() as $indexInfo) {
                $indexName = $indexInfo['uid'];

                if (str_starts_with($indexName, $this->indexPrefix)) {
                    $index = $this->client->index($indexName);
                    $stats = $index->stats();

                    $info[$indexName] = [
                        'uid' => $indexName,
                        'primary_key' => $indexInfo['primaryKey'],
                        'created_at' => $indexInfo['createdAt'],
                        'updated_at' => $indexInfo['updatedAt'],
                        'number_of_documents' => $stats['numberOfDocuments'],
                        'is_indexing' => $stats['isIndexing'],
                        'field_distribution' => $stats['fieldDistribution'],
                    ];
                }
            }

            return $info;

        } catch (Exception $e) {
            Log::error('Failed to get Meilisearch indices info: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Clear all documents from an index
     */
    public function clearIndex(string $indexName): array
    {
        $fullIndexName = $this->indexPrefix.$indexName;

        try {
            $index = $this->client->index($fullIndexName);
            $task = $index->deleteAllDocuments();

            return [
                'success' => true,
                'task_id' => $task['taskUid'],
            ];

        } catch (Exception $e) {
            Log::error("Failed to clear index {$fullIndexName}: ".$e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Prepare article document for indexing
     */
    protected function prepareArticleDocument(Article $article): array
    {
        return [
            'id' => $article->id,
            'title' => $article->title,
            'content' => $this->truncateText($article->content, 50000), // Meilisearch has size limits
            'excerpt' => $article->excerpt,
            'authors' => $article->authors,
            'url' => $article->url,
            'source_id' => $article->source_id,
            'source_name' => $article->source?->name,
            'source_domain' => $article->source?->domain,
            'published_at' => $article->published_at?->timestamp,
            'language' => $article->language,
            'word_count' => $article->word_count,
            'quality_score' => $article->quality_score,
            'created_at' => $article->created_at->timestamp,
            'updated_at' => $article->updated_at->timestamp,
        ];
    }

    /**
     * Prepare source document for indexing
     */
    protected function prepareSourceDocument(Source $source): array
    {
        return [
            'id' => $source->id,
            'name' => $source->name,
            'domain' => $source->domain,
            'description' => $source->description,
            'is_active' => $source->is_active,
            'credibility_score' => $source->credibility_score,
            'total_articles' => $source->total_articles,
            'last_crawl_at' => $source->last_crawl_at?->timestamp,
            'created_at' => $source->created_at->timestamp,
        ];
    }

    /**
     * Extract key phrases from content for similarity search
     */
    protected function extractKeyPhrases(string $content): string
    {
        // Simple key phrase extraction
        $words = str_word_count(strtolower($content), 1);
        $stopWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'is', 'are', 'was', 'were', 'be', 'been', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should'];

        $keywords = array_diff($words, $stopWords);
        $keywordCounts = array_count_values($keywords);
        arsort($keywordCounts);

        // Take top 10 keywords
        $topKeywords = array_slice(array_keys($keywordCounts), 0, 10);

        return implode(' ', $topKeywords);
    }

    /**
     * Truncate text to specified length
     */
    protected function truncateText(string $text, int $maxLength): string
    {
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        return mb_substr($text, 0, $maxLength - 3).'...';
    }
}
