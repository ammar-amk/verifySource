<?php

namespace App\Observers;

use App\Models\Article;
use App\Services\SearchOrchestrationService;
use Exception;
use Illuminate\Support\Facades\Log;

class ArticleObserver
{
    protected SearchOrchestrationService $searchOrchestration;

    public function __construct(SearchOrchestrationService $searchOrchestration)
    {
        $this->searchOrchestration = $searchOrchestration;
    }

    /**
     * Handle the Article "created" event.
     */
    public function created(Article $article): void
    {
        // Only index if search is enabled and article has content
        if ($this->shouldIndex($article)) {
            $this->indexArticleAsync($article, 'created');
        }
    }

    /**
     * Handle the Article "updated" event.
     */
    public function updated(Article $article): void
    {
        // Check if relevant fields were updated
        if ($this->shouldIndex($article) && $this->hasRelevantChanges($article)) {
            $this->indexArticleAsync($article, 'updated');
        }
    }

    /**
     * Handle the Article "deleted" event.
     */
    public function deleted(Article $article): void
    {
        if (config('verifysource.search.enabled', true)) {
            $this->removeArticleFromSearchAsync($article);
        }
    }

    /**
     * Check if article should be indexed
     */
    protected function shouldIndex(Article $article): bool
    {
        // Don't index if search is disabled
        if (! config('verifysource.search.enabled', true)) {
            return false;
        }

        // Don't index duplicates
        if ($article->is_duplicate) {
            return false;
        }

        // Must have title and content
        if (empty($article->title) || empty($article->content)) {
            return false;
        }

        // Content must meet minimum length requirements
        $minLength = config('verifysource.content.min_content_length', 100);
        if (strlen($article->content) < $minLength) {
            return false;
        }

        return true;
    }

    /**
     * Check if the article has relevant changes that require re-indexing
     */
    protected function hasRelevantChanges(Article $article): bool
    {
        $relevantFields = [
            'title',
            'content',
            'excerpt',
            'author',
            'published_at',
            'language',
            'is_processed',
            'is_duplicate',
        ];

        foreach ($relevantFields as $field) {
            if ($article->wasChanged($field)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Index article asynchronously to avoid blocking the main process
     */
    protected function indexArticleAsync(Article $article, string $action): void
    {
        try {
            // For now, we'll index synchronously but could queue this later
            $result = $this->searchOrchestration->indexArticle($article);

            if ($result['success']) {
                Log::info('Article indexed in search engines', [
                    'article_id' => $article->id,
                    'action' => $action,
                    'meilisearch' => $result['meilisearch']['success'] ?? false,
                    'qdrant' => $result['qdrant']['success'] ?? false,
                ]);
            } else {
                Log::warning('Failed to index article in search engines', [
                    'article_id' => $article->id,
                    'action' => $action,
                    'error' => $result['error'] ?? 'Unknown error',
                ]);
            }

        } catch (Exception $e) {
            Log::error('Exception while indexing article', [
                'article_id' => $article->id,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Remove article from search engines asynchronously
     */
    protected function removeArticleFromSearchAsync(Article $article): void
    {
        try {
            $result = $this->searchOrchestration->removeArticles([$article->id]);

            if ($result['success']) {
                Log::info('Article removed from search engines', [
                    'article_id' => $article->id,
                    'removed_count' => $result['removed_count'],
                ]);
            } else {
                Log::warning('Failed to remove article from search engines', [
                    'article_id' => $article->id,
                    'error' => $result['error'] ?? 'Unknown error',
                ]);
            }

        } catch (Exception $e) {
            Log::error('Exception while removing article from search', [
                'article_id' => $article->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
