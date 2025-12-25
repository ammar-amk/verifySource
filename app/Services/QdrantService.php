<?php

namespace App\Services;

use App\Models\Article;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class QdrantService
{
    protected Client $client;

    protected array $config;

    protected string $baseUrl;

    protected array $headers;

    public function __construct()
    {
        $this->config = config('verifysource.search.qdrant');
        $this->baseUrl = rtrim($this->config['host'], '/');

        $this->headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        if (! empty($this->config['api_key'])) {
            $this->headers['Authorization'] = 'Bearer '.$this->config['api_key'];
        }

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => $this->config['timeout'],
            'headers' => $this->headers,
        ]);
    }

    /**
     * Check if Qdrant is available
     */
    public function isAvailable(): bool
    {
        try {
            $response = $this->client->get('/');

            return $response->getStatusCode() === 200;
        } catch (Exception $e) {
            Log::warning('Qdrant not available: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Get Qdrant server info
     */
    public function getServerInfo(): array
    {
        try {
            $response = $this->client->get('/');
            $data = json_decode($response->getBody(), true);

            $collectionsResponse = $this->client->get('/collections');
            $collections = json_decode($collectionsResponse->getBody(), true);

            return [
                'available' => true,
                'version' => $data['title'] ?? 'Unknown',
                'collections' => $collections['result']['collections'] ?? [],
                'collections_info' => $this->getCollectionsInfo(),
            ];

        } catch (Exception $e) {
            return [
                'available' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Initialize collections
     */
    public function initializeCollections(): array
    {
        $results = [];
        $collectionsConfig = $this->config['collections'];

        foreach ($collectionsConfig as $collectionKey => $collectionConfig) {
            try {
                $results[$collectionKey] = $this->createOrUpdateCollection(
                    $collectionConfig['name'],
                    $collectionConfig
                );
            } catch (Exception $e) {
                $results[$collectionKey] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Create or update a collection
     */
    public function createOrUpdateCollection(string $collectionName, array $config): array
    {
        try {
            // Check if collection exists
            if ($this->collectionExists($collectionName)) {
                return [
                    'success' => true,
                    'action' => 'exists',
                    'collection' => $collectionName,
                    'info' => $this->getCollectionInfo($collectionName),
                ];
            }

            // Create collection
            $createData = [
                'vectors' => [
                    'size' => $config['vector_size'],
                    'distance' => $config['distance'],
                ],
                'optimizers_config' => [
                    'default_segment_number' => 0,
                ],
                'replication_factor' => 1,
            ];

            if (isset($config['hnsw_config'])) {
                $createData['hnsw_config'] = $config['hnsw_config'];
            }

            if (isset($config['on_disk_payload'])) {
                $createData['on_disk_payload'] = $config['on_disk_payload'];
            }

            $response = $this->client->put("/collections/{$collectionName}", [
                'json' => $createData,
            ]);

            return [
                'success' => true,
                'action' => 'created',
                'collection' => $collectionName,
                'response' => json_decode($response->getBody(), true),
            ];

        } catch (Exception $e) {
            Log::error("Failed to create/update collection {$collectionName}: ".$e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check if collection exists
     */
    public function collectionExists(string $collectionName): bool
    {
        try {
            $response = $this->client->get("/collections/{$collectionName}");

            return $response->getStatusCode() === 200;
        } catch (RequestException $e) {
            $statusCode = $e->getResponse()?->getStatusCode();
            if ($statusCode !== 404) {
                Log::warning("Error checking if collection exists: {$collectionName}. Status code: ".($statusCode ?? 'N/A').'. Message: '.$e->getMessage());
            }

            return false;
        }
    }

    /**
     * Get collection info
     */
    public function getCollectionInfo(string $collectionName): array
    {
        try {
            $response = $this->client->get("/collections/{$collectionName}");

            return json_decode($response->getBody(), true)['result'] ?? [];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Index articles with embeddings
     */
    public function indexArticles(array $articleIds = []): array
    {
        $collectionName = $this->config['collections']['articles']['name'];

        try {
            $query = Article::with('source')
                ->where('content', '!=', '')
                ->where('title', '!=', '');

            if (! empty($articleIds)) {
                $query->whereIn('id', $articleIds);
            }

            $articles = $query->get();

            if ($articles->isEmpty()) {
                return ['success' => true, 'indexed' => 0];
            }

            $vectors = [];
            $batchSize = config('verifysource.search.embeddings.batch_size', 32);

            foreach ($articles->chunk($batchSize) as $batch) {
                $batchVectors = $this->generateBatchEmbeddings($batch);
                $vectors = array_merge($vectors, $batchVectors);
            }

            if (empty($vectors)) {
                return ['success' => true, 'indexed' => 0];
            }

            // Upload vectors to Qdrant
            $response = $this->client->put("/collections/{$collectionName}/points", [
                'json' => [
                    'points' => $vectors,
                ],
            ]);

            $result = json_decode($response->getBody(), true);

            return [
                'success' => true,
                'indexed' => count($vectors),
                'response' => $result,
            ];

        } catch (Exception $e) {
            Log::error('Failed to index articles in Qdrant: '.$e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Search for similar articles using vector similarity
     */
    public function searchSimilarArticles(string $content, array $options = []): array
    {
        $collectionName = $this->config['collections']['articles']['name'];

        try {
            // Generate embedding for the search content
            $embedding = $this->generateEmbedding($content);

            if (empty($embedding)) {
                return [
                    'success' => false,
                    'error' => 'Failed to generate embedding for search content',
                ];
            }

            $searchParams = [
                'vector' => $embedding,
                'limit' => $options['limit'] ?? 10,
                'with_payload' => true,
                'with_vector' => false,
            ];

            if (isset($options['filter'])) {
                $searchParams['filter'] = $options['filter'];
            }

            if (isset($options['score_threshold'])) {
                $searchParams['score_threshold'] = $options['score_threshold'];
            } else {
                $searchParams['score_threshold'] = config('verifysource.search.options.similarity_threshold', 0.7);
            }

            $response = $this->client->post("/collections/{$collectionName}/points/search", [
                'json' => $searchParams,
            ]);

            $result = json_decode($response->getBody(), true);

            return [
                'success' => true,
                'results' => $result['result'] ?? [],
                'query_vector_size' => count($embedding),
            ];

        } catch (Exception $e) {
            Log::error('Failed to search similar articles in Qdrant: '.$e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Find duplicate or near-duplicate articles
     */
    public function findDuplicateArticles(Article $article, float $threshold = 0.9): array
    {
        $content = $article->title.' '.$article->content;

        return $this->searchSimilarArticles($content, [
            'limit' => 20,
            'score_threshold' => $threshold,
            'filter' => [
                'must_not' => [
                    'key' => 'article_id',
                    'match' => ['value' => $article->id],
                ],
            ],
        ]);
    }

    /**
     * Update a single article vector
     */
    public function updateArticle(Article $article): array
    {
        $collectionName = $this->config['collections']['articles']['name'];

        try {
            $content = $this->prepareContentForEmbedding($article);
            $embedding = $this->generateEmbedding($content);

            if (empty($embedding)) {
                return [
                    'success' => false,
                    'error' => 'Failed to generate embedding',
                ];
            }

            $vector = [
                'id' => $article->id,
                'vector' => $embedding,
                'payload' => $this->prepareArticlePayload($article),
            ];

            $response = $this->client->put("/collections/{$collectionName}/points", [
                'json' => [
                    'points' => [$vector],
                ],
            ]);

            return [
                'success' => true,
                'response' => json_decode($response->getBody(), true),
            ];

        } catch (Exception $e) {
            Log::error("Failed to update article {$article->id} in Qdrant: ".$e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Delete articles from collection
     */
    public function deleteArticles(array $articleIds): array
    {
        $collectionName = $this->config['collections']['articles']['name'];

        try {
            $response = $this->client->post("/collections/{$collectionName}/points/delete", [
                'json' => [
                    'points' => $articleIds,
                ],
            ]);

            return [
                'success' => true,
                'deleted' => count($articleIds),
                'response' => json_decode($response->getBody(), true),
            ];

        } catch (Exception $e) {
            Log::error('Failed to delete articles from Qdrant: '.$e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get collections info
     */
    public function getCollectionsInfo(): array
    {
        try {
            $response = $this->client->get('/collections');
            $data = json_decode($response->getBody(), true);

            $collections = [];
            foreach ($data['result']['collections'] ?? [] as $collection) {
                $name = $collection['name'];
                $info = $this->getCollectionInfo($name);
                $collections[$name] = array_merge($collection, $info);
            }

            return $collections;

        } catch (Exception $e) {
            Log::error('Failed to get Qdrant collections info: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Clear all points from a collection
     */
    public function clearCollection(string $collectionName): array
    {
        try {
            $response = $this->client->post("/collections/{$collectionName}/points/delete", [
                'json' => [
                    'filter' => [
                        'must' => [
                            'key' => 'article_id',
                            'range' => [
                                'gte' => 0,
                            ],
                        ],
                    ],
                ],
            ]);

            return [
                'success' => true,
                'response' => json_decode($response->getBody(), true),
            ];

        } catch (Exception $e) {
            Log::error("Failed to clear collection {$collectionName}: ".$e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate batch embeddings for articles
     */
    protected function generateBatchEmbeddings($articles): array
    {
        $vectors = [];

        foreach ($articles as $article) {
            $content = $this->prepareContentForEmbedding($article);
            $embedding = $this->generateEmbedding($content);

            if (! empty($embedding)) {
                $vectors[] = [
                    'id' => $article->id,
                    'vector' => $embedding,
                    'payload' => $this->prepareArticlePayload($article),
                ];
            }
        }

        return $vectors;
    }

    /**
     * Generate embedding for text content
     */
    protected function generateEmbedding(string $content): array
    {
        $cacheKey = 'embedding:'.hash('sha256', $content);

        // Check cache first if enabled
        if (config('verifysource.search.embeddings.cache')) {
            $cached = Cache::get($cacheKey);
            if ($cached) {
                return $cached;
            }
        }

        try {
            // Use sentence-transformers via Python script
            $embedding = $this->generateEmbeddingViaPython($content);

            // Cache the embedding if enabled
            if (config('verifysource.search.embeddings.cache') && ! empty($embedding)) {
                Cache::put($cacheKey, $embedding, now()->addDays(7));
            }

            return $embedding;

        } catch (Exception $e) {
            Log::error('Failed to generate embedding: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Generate embedding using Python sentence-transformers
     */
    protected function generateEmbeddingViaPython(string $content): array
    {
        $model = config('verifysource.search.embeddings.model', 'all-MiniLM-L6-v2');
        $maxTokens = config('verifysource.search.embeddings.max_tokens', 512);

        // Truncate content if too long
        if (strlen($content) > $maxTokens * 4) { // Rough estimate: 4 chars per token
            $content = substr($content, 0, $maxTokens * 4);
        }

        $pythonScript = base_path('storage/app/scripts/generate_embedding.py');

        // Create Python script if it doesn't exist
        if (! file_exists($pythonScript)) {
            $this->createEmbeddingScript($pythonScript);
        }

        $pythonExecutable = config('verifysource.python.executable', 'python3');

        // Escape content for command line
        $escapedContent = base64_encode($content);

        $command = "{$pythonExecutable} \"{$pythonScript}\" \"{$model}\" \"{$escapedContent}\"";

        $result = Process::timeout(60)->run($command);

        if ($result->failed()) {
            Log::error('Python embedding generation failed: '.$result->errorOutput());

            return [];
        }

        $output = json_decode($result->output(), true);

        if (! isset($output['embedding']) || ! is_array($output['embedding'])) {
            Log::error('Invalid embedding output from Python script');

            return [];
        }

        return $output['embedding'];
    }

    /**
     * Create Python embedding script
     */
    protected function createEmbeddingScript(string $scriptPath): void
    {
        $scriptDir = dirname($scriptPath);
        if (! is_dir($scriptDir)) {
            mkdir($scriptDir, 0755, true);
        }

        $script = <<<'PYTHON'
#!/usr/bin/env python3

import sys
import json
import base64
from sentence_transformers import SentenceTransformer

def generate_embedding(model_name, content_base64):
    try:
        # Decode content
        content = base64.b64decode(content_base64).decode('utf-8')
        
        # Load model
        model = SentenceTransformer(model_name)
        
        # Generate embedding
        embedding = model.encode(content).tolist()
        
        return {
            'success': True,
            'embedding': embedding,
            'model': model_name,
            'content_length': len(content)
        }
        
    except Exception as e:
        return {
            'success': False,
            'error': str(e)
        }

if __name__ == '__main__':
    if len(sys.argv) != 3:
        print(json.dumps({'success': False, 'error': 'Usage: python generate_embedding.py <model> <content_base64>'}))
        sys.exit(1)
    
    model_name = sys.argv[1]
    content_base64 = sys.argv[2]
    
    result = generate_embedding(model_name, content_base64)
    print(json.dumps(result))
PYTHON;

        file_put_contents($scriptPath, $script);
        chmod($scriptPath, 0755);
    }

    /**
     * Prepare content for embedding generation
     */
    protected function prepareContentForEmbedding(Article $article): string
    {
        $content = $article->title;

        if ($article->excerpt) {
            $content .= ' '.$article->excerpt;
        }

        if ($article->content) {
            // Take first part of content to stay within token limits
            $maxLength = config('verifysource.search.embeddings.max_tokens', 512) * 3; // Rough estimate
            $contentPart = substr($article->content, 0, $maxLength);
            $content .= ' '.$contentPart;
        }

        return trim($content);
    }

    /**
     * Prepare article payload for Qdrant
     */
    protected function prepareArticlePayload(Article $article): array
    {
        return [
            'article_id' => $article->id,
            'title' => $article->title,
            'url' => $article->url,
            'source_id' => $article->source_id,
            'source_name' => $article->source?->name,
            'published_at' => $article->published_at?->timestamp,
            'quality_score' => $article->quality_score,
            'word_count' => $article->word_count,
            'language' => $article->language,
            'created_at' => $article->created_at->timestamp,
        ];
    }
}
