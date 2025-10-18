<?php

namespace App\Console\Commands;

use App\Models\Source;
use App\Services\CrawlerOrchestrationService;
use Illuminate\Console\Command;

class CrawlSource extends Command
{
    protected $signature = 'crawl:source {source? : Source ID or domain} {--all : Crawl all active sources} {--immediate : Process jobs immediately}';
    
    protected $description = 'Crawl a specific source or all active sources';

    public function handle(CrawlerOrchestrationService $crawlerService): int
    {
        if ($this->option('all')) {
            return $this->crawlAllSources($crawlerService);
        }
        
        $sourceIdentifier = $this->argument('source');
        
        if (!$sourceIdentifier) {
            $this->error('Please provide a source ID/domain or use --all flag');
            return self::FAILURE;
        }
        
        return $this->crawlSingleSource($sourceIdentifier, $crawlerService);
    }
    
    protected function crawlSingleSource(string $identifier, CrawlerOrchestrationService $crawlerService): int
    {
        // Try to find source by ID first, then by domain
        $source = is_numeric($identifier) 
            ? Source::find($identifier)
            : Source::where('domain', $identifier)->first();
            
        if (!$source) {
            $this->error("Source not found: {$identifier}");
            return self::FAILURE;
        }
        
        if (!$source->is_active) {
            $this->error("Source is inactive: {$source->domain}");
            return self::FAILURE;
        }
        
        $this->info("Crawling source: {$source->name} ({$source->domain})");
        
        $options = [
            'process_immediately' => $this->option('immediate')
        ];
        
        $results = $crawlerService->crawlSource($source, $options);
        
        $this->info("Crawl completed:");
        $this->line("- Jobs created: {$results['jobs_created']}");
        
        if ($options['process_immediately']) {
            $this->line("- Jobs processed: {$results['jobs_processed']}");
            $this->line("- Articles created: {$results['articles_created']}");
            $this->line("- URLs discovered: {$results['urls_discovered']}");
        }
        
        if (!empty($results['errors'])) {
            $this->error("Errors encountered:");
            foreach ($results['errors'] as $error) {
                $this->line("  - {$error}");
            }
            return self::FAILURE;
        }
        
        return self::SUCCESS;
    }
    
    protected function crawlAllSources(CrawlerOrchestrationService $crawlerService): int
    {
        $this->info("Crawling all active sources...");
        
        $results = $crawlerService->crawlAllActiveSources();
        
        $this->info("Bulk crawl completed:");
        $this->line("- Sources crawled: {$results['sources_crawled']}");
        $this->line("- Total jobs created: {$results['total_jobs_created']}");
        
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