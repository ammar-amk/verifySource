<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Services\SearchOrchestrationService;
use Illuminate\Console\Command;

class SearchIndex extends Command
{
    protected $signature = 'search:index {--all : Index all articles} {--articles=* : Specific article IDs to index} {--recent : Index articles from last 24 hours} {--queue : Queue indexing jobs for large batches} {--batch-size=100 : Batch size for queued jobs}';

    protected $description = 'Index articles into search engines (Meilisearch and Qdrant)';

    public function handle(SearchOrchestrationService $searchOrchestration): int
    {
        $all = $this->option('all');
        $articleIds = $this->option('articles');
        $recent = $this->option('recent');
        $queue = $this->option('queue');
        $batchSize = intval($this->option('batch-size'));

        // Validate options
        if (! $all && empty($articleIds) && ! $recent) {
            $this->error('You must specify --all, --articles, or --recent');

            return self::FAILURE;
        }

        $this->info('Starting search indexing process...');

        // Determine articles to index
        $articlesToIndex = [];

        if ($all) {
            $this->line('Fetching all articles...');
            $articlesToIndex = Article::pluck('id')->toArray();
        } elseif ($recent) {
            $this->line('Fetching articles from last 24 hours...');
            $articlesToIndex = Article::where('created_at', '>=', now()->subDay())
                ->pluck('id')->toArray();
        } else {
            $articlesToIndex = array_map('intval', $articleIds);
        }

        if (empty($articlesToIndex)) {
            $this->warn('No articles found to index');

            return self::SUCCESS;
        }

        $this->info('Found '.count($articlesToIndex).' articles to index');

        // Index articles
        if ($queue && count($articlesToIndex) > $batchSize) {
            $this->info("Queueing indexing jobs in batches of {$batchSize}...");

            $result = $searchOrchestration->queueIndexingJobs($articlesToIndex, $batchSize);

            if ($result['success']) {
                $this->info("✓ Queued {$result['queued_jobs']} indexing jobs");
                $this->line("  Total articles: {$result['total_articles']}");
                $this->line("  Batches: {$result['batches']}");
                $this->line("  Batch size: {$result['batch_size']}");
            } else {
                $this->error('✗ Failed to queue indexing jobs');

                return self::FAILURE;
            }

        } else {
            // Direct indexing
            $progressBar = $this->output->createProgressBar(count($articlesToIndex));
            $progressBar->start();

            if (count($articlesToIndex) <= 100) {
                // Index all at once
                $result = $searchOrchestration->indexArticles($articlesToIndex);
                $progressBar->advance(count($articlesToIndex));
            } else {
                // Index in chunks
                $chunks = array_chunk($articlesToIndex, $batchSize);
                foreach ($chunks as $chunk) {
                    $searchOrchestration->indexArticles($chunk);
                    $progressBar->advance(count($chunk));
                }
                $result = ['success' => true, 'indexed_count' => count($articlesToIndex)];
            }

            $progressBar->finish();
            $this->line('');

            if ($result['success']) {
                $this->info("✓ Indexed {$result['indexed_count']} articles successfully");

                // Show service-specific results
                if (isset($result['meilisearch']) && $result['meilisearch']['success']) {
                    $this->line("  Meilisearch: {$result['meilisearch']['indexed']} articles indexed");
                }

                if (isset($result['qdrant']) && $result['qdrant']['success']) {
                    $this->line("  Qdrant: {$result['qdrant']['indexed']} articles indexed");
                }

            } else {
                $this->error('✗ Indexing failed');
                if (isset($result['error'])) {
                    $this->error('Error: '.$result['error']);
                }

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
