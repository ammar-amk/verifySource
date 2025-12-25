<?php

namespace App\Console\Commands;

use App\Services\CrawlerOrchestrationService;
use Illuminate\Console\Command;

class IndexContent extends Command
{
    protected $signature = 'crawl:index {--all : Index all unprocessed content}';

    protected $description = 'Index scraped content for search and deduplication';

    public function handle(CrawlerOrchestrationService $crawlerService): int
    {
        $this->info('Indexing unprocessed content...');

        $results = $crawlerService->indexAllUnprocessedContent();

        $this->info('Content indexing completed:');
        $this->line("- Articles processed: {$results['processed']}");
        $this->line("- Errors: {$results['errors']}");

        if ($results['errors'] > 0) {
            $this->warn('Some articles failed to index. Check logs for details.');
        }

        return self::SUCCESS;
    }
}
