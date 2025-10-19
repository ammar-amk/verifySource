<?php

namespace App\Console\Commands;

use App\Models\Source;
use App\Services\PythonCrawlerService;
use Illuminate\Console\Command;

class PythonCrawlSource extends Command
{
    protected $signature = 'crawl:python:source {source-id : Source ID to crawl} {--limit=10 : Number of articles to crawl} {--show-progress : Show crawling progress}';

    protected $description = 'Crawl a source using Python crawler';

    public function handle(PythonCrawlerService $pythonCrawler): int
    {
        $sourceId = $this->argument('source-id');
        $limit = max(1, intval($this->option('limit')));
        $showProgress = $this->option('show-progress');

        // Find the source
        $source = Source::find($sourceId);
        if (! $source) {
            $this->error("Source not found: {$sourceId}");

            return self::FAILURE;
        }

        $this->info("Crawling source: {$source->name} ({$source->domain})");
        $this->info("Limit: {$limit} articles");

        // Check Python environment
        $envCheck = $pythonCrawler->checkPythonEnvironment();
        if (! $envCheck['ready']) {
            $this->error("Python environment is not ready. Run 'php artisan crawl:python:check' for details.");

            return self::FAILURE;
        }

        // Show progress bar
        if ($showProgress) {
            $progressBar = $this->output->createProgressBar($limit);
            $progressBar->start();
        }

        // Crawl the source
        $result = $pythonCrawler->crawlSource($sourceId, $limit);

        if ($showProgress) {
            $progressBar->finish();
            $this->line('');
        }

        if ($result['success']) {
            $stats = $result['stats'];

            $this->info('✓ Source crawling completed!');
            $this->line('');
            $this->line('Articles crawled: '.$stats['articles_crawled']);
            $this->line('New articles: '.$stats['new_articles']);
            $this->line('Updated articles: '.$stats['updated_articles']);
            $this->line('Failed URLs: '.$stats['failed_urls']);
            $this->line('Execution time: '.number_format($stats['execution_time'] ?? 0, 2).'s');

            if ($stats['failed_urls'] > 0 && ! empty($stats['errors'])) {
                $this->line('');
                $this->warn('Errors encountered:');
                foreach ($stats['errors'] as $error) {
                    $this->line("  - {$error}");
                }
            }

            // Update source crawl stats
            $source->update([
                'last_crawl_at' => now(),
                'total_articles' => ($source->total_articles ?? 0) + $stats['new_articles'],
            ]);

        } else {
            $this->error('✗ Source crawl failed: '.$result['error']);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
