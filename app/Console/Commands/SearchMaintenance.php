<?php

namespace App\Console\Commands;

use App\Services\SearchOrchestrationService;
use Illuminate\Console\Command;

class SearchMaintenance extends Command
{
    protected $signature = 'search:maintenance {action} {--confirm : Confirm destructive operations}';

    protected $description = 'Perform maintenance operations on search indices
    
Available actions:
  clear       - Clear all search indices
  optimize    - Optimize search indices  
  sync        - Synchronize indices with database
  rebuild     - Clear and re-index all content';

    public function handle(SearchOrchestrationService $searchOrchestration): int
    {
        $action = $this->argument('action');
        $confirm = $this->option('confirm');

        return match ($action) {
            'clear' => $this->clearIndices($searchOrchestration, $confirm),
            'optimize' => $this->optimizeIndices($searchOrchestration),
            'sync' => $this->synchronizeIndices($searchOrchestration),
            'rebuild' => $this->rebuildIndices($searchOrchestration, $confirm),
            default => $this->showUsage()
        };
    }

    protected function clearIndices(SearchOrchestrationService $searchOrchestration, bool $confirm): int
    {
        $this->warn('This will clear ALL search indices and remove all indexed content.');

        if (! $confirm && ! $this->confirm('Are you sure you want to continue?')) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        $this->info('Clearing search indices...');

        $result = $searchOrchestration->clearAllIndices();

        if ($result['success']) {
            $this->info('✓ All search indices cleared successfully');

            if (isset($result['meilisearch'])) {
                $this->line('  Meilisearch indices cleared');
            }

            if (isset($result['qdrant'])) {
                $this->line('  Qdrant collections cleared');
            }

            $this->line('');
            $this->warn('Note: You will need to re-index content using: php artisan search:index --all');

        } else {
            $this->error('✗ Failed to clear indices');
            if (isset($result['error'])) {
                $this->error('Error: '.$result['error']);
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    protected function optimizeIndices(SearchOrchestrationService $searchOrchestration): int
    {
        $this->info('Optimizing search indices...');

        $result = $searchOrchestration->optimizeIndices();

        if ($result['success']) {
            $this->info('✓ Search indices optimization completed');

            if (isset($result['meilisearch'])) {
                $this->line('  Meilisearch: '.$result['meilisearch']['message']);
            }

            if (isset($result['qdrant'])) {
                $this->line('  Qdrant: '.$result['qdrant']['message']);
            }

        } else {
            $this->error('✗ Failed to optimize indices');
            if (isset($result['error'])) {
                $this->error('Error: '.$result['error']);
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    protected function synchronizeIndices(SearchOrchestrationService $searchOrchestration): int
    {
        $this->info('Synchronizing search indices with database...');

        $result = $searchOrchestration->synchronizeIndices();

        $this->info('Database Analysis:');
        $this->line('  Database articles: '.number_format($result['database_count']));
        $this->line('  Meilisearch documents: '.number_format($result['meilisearch_count']));
        $this->line('  Qdrant vectors: '.number_format($result['qdrant_count']));

        if (! empty($result['articles_to_index'])) {
            $this->warn('Found '.count($result['articles_to_index']).' articles that need indexing');

            if ($this->confirm('Would you like to index these articles now?')) {
                $this->call('search:index', [
                    '--articles' => $result['articles_to_index'],
                    '--queue' => true,
                ]);
            }
        }

        if (! empty($result['articles_to_remove'])) {
            $this->warn('Found '.count($result['articles_to_remove']).' indexed articles that no longer exist in database');

            if ($this->confirm('Would you like to remove these from search indices?')) {
                $removeResult = $searchOrchestration->removeArticles($result['articles_to_remove']);

                if ($removeResult['success']) {
                    $this->info("✓ Removed {$removeResult['removed_count']} articles from search indices");
                } else {
                    $this->error('✗ Failed to remove articles');
                }
            }
        }

        if (empty($result['articles_to_index']) && empty($result['articles_to_remove'])) {
            $this->info('✓ Search indices are synchronized with database');
        }

        return self::SUCCESS;
    }

    protected function rebuildIndices(SearchOrchestrationService $searchOrchestration, bool $confirm): int
    {
        $this->warn('This will clear all search indices and rebuild them from database content.');
        $this->warn('This operation may take a long time for large databases.');

        if (! $confirm && ! $this->confirm('Are you sure you want to continue?')) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        $this->info('Rebuilding search indices...');

        // Step 1: Clear indices
        $this->line('1. Clearing existing indices...');
        $clearResult = $searchOrchestration->clearAllIndices();

        if (! $clearResult['success']) {
            $this->error('✗ Failed to clear indices');

            return self::FAILURE;
        }

        $this->info('✓ Indices cleared');

        // Step 2: Re-initialize
        $this->line('2. Re-initializing search system...');
        $initResult = $searchOrchestration->initializeSearchSystem();

        if (! $initResult['success']) {
            $this->error('✗ Failed to initialize search system');

            return self::FAILURE;
        }

        $this->info('✓ Search system initialized');

        // Step 3: Index all content
        $this->line('3. Indexing all content...');
        $indexResult = $searchOrchestration->indexAllContent();

        if (! $indexResult['success']) {
            $this->error('✗ Failed to index content');
            if (isset($indexResult['error'])) {
                $this->error('Error: '.$indexResult['error']);
            }

            return self::FAILURE;
        }

        $this->info("✓ Indexed {$indexResult['total_indexed']} articles");

        // Show results breakdown
        if (isset($indexResult['articles']['meilisearch']) && $indexResult['articles']['meilisearch']['success']) {
            $this->line("  Meilisearch: {$indexResult['articles']['meilisearch']['indexed']} articles");
        }

        if (isset($indexResult['articles']['qdrant']) && $indexResult['articles']['qdrant']['success']) {
            $this->line("  Qdrant: {$indexResult['articles']['qdrant']['indexed']} articles");
        }

        $this->line('');
        $this->info('✓ Search indices rebuilt successfully!');

        return self::SUCCESS;
    }

    protected function showUsage(): int
    {
        $this->error('Invalid action specified.');
        $this->line('');
        $this->info('Available actions:');
        $this->line('  clear     - Clear all search indices');
        $this->line('  optimize  - Optimize search indices');
        $this->line('  sync      - Synchronize indices with database');
        $this->line('  rebuild   - Clear and re-index all content');
        $this->line('');
        $this->line('Examples:');
        $this->line('  php artisan search:maintenance clear --confirm');
        $this->line('  php artisan search:maintenance optimize');
        $this->line('  php artisan search:maintenance sync');
        $this->line('  php artisan search:maintenance rebuild --confirm');

        return self::FAILURE;
    }
}
