<?php

namespace App\Console\Commands;

use App\Services\CrawlerOrchestrationService;
use Illuminate\Console\Command;

class ProcessCrawlJobs extends Command
{
    protected $signature = 'crawl:process {--limit=10 : Maximum number of jobs to process}';
    
    protected $description = 'Process pending crawl jobs';

    public function handle(CrawlerOrchestrationService $crawlerService): int
    {
        $limit = (int) $this->option('limit');
        
        $this->info("Processing up to {$limit} crawl jobs...");
        
        $results = $crawlerService->processPendingJobs($limit);
        
        $this->info("Crawl processing completed:");
        $this->line("- Jobs processed: {$results['jobs_processed']}");
        $this->line("- Articles created: {$results['articles_created']}");
        $this->line("- URLs discovered: {$results['urls_discovered']}");
        
        if (!empty($results['errors'])) {
            $this->error("Errors encountered:");
            foreach ($results['errors'] as $error) {
                $this->line("  - {$error}");
            }
            return self::FAILURE;
        }
        
        return self::SUCCESS;
    }
}