<?php

namespace App\Console\Commands;

use App\Services\CrawlJobService;
use App\Services\CrawlerOrchestrationService;
use Illuminate\Console\Command;

class CrawlStats extends Command
{
    protected $signature = 'crawl:stats';
    
    protected $description = 'Display crawling system statistics';

    public function handle(CrawlerOrchestrationService $crawlerService): int
    {
        $stats = $crawlerService->getSystemStats();
        
        $this->info("=== Crawling System Statistics ===");
        
        // Crawl Job Stats
        $this->line("");
        $this->info("Crawl Jobs:");
        $this->line("- Pending: {$stats['crawl_jobs']['pending']}");
        $this->line("- Running: {$stats['crawl_jobs']['running']}");
        $this->line("- Completed: {$stats['crawl_jobs']['completed']}");
        $this->line("- Failed: {$stats['crawl_jobs']['failed']}");
        $this->line("- Total: {$stats['crawl_jobs']['total']}");
        $this->line("- Success Rate: {$stats['crawl_jobs']['success_rate']}%");
        $this->line("- Avg Completion Time: {$stats['crawl_jobs']['average_completion_time']}s");
        
        // Content Stats
        $this->line("");
        $this->info("Content:");
        $this->line("- Total Articles: {$stats['content']['total_articles']}");
        $this->line("- Processed: {$stats['content']['processed_articles']}");
        $this->line("- Duplicates: {$stats['content']['duplicate_articles']}");
        $this->line("- With Hashes: {$stats['content']['articles_with_hashes']}");
        
        // Source Stats
        $this->line("");
        $this->info("Sources:");
        $this->line("- Total Sources: {$stats['sources']['total_sources']}");
        $this->line("- Active: {$stats['sources']['active_sources']}");
        $this->line("- Verified: {$stats['sources']['verified_sources']}");
        
        // System Health
        $this->line("");
        $health = $stats['system_health'];
        $healthColor = $health['status'] === 'healthy' ? 'info' : 'error';
        $this->$healthColor("System Health: " . strtoupper($health['status']));
        
        if (!empty($health['issues'])) {
            $this->line("Issues:");
            foreach ($health['issues'] as $issue) {
                $this->line("  - {$issue}");
            }
        }
        
        return self::SUCCESS;
    }
}