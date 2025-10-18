<?php

namespace App\Console\Commands;

use App\Services\CrawlSchedulingService;
use Illuminate\Console\Command;

class ScheduleCrawls extends Command
{
    protected $signature = 'crawl:schedule 
                          {--queue : Queue pending jobs instead of scheduling new ones}
                          {--setup-recurring : Set up recurring crawl schedules}
                          {--frequency=daily : Frequency for new schedules (hourly, daily, weekly, monthly)}
                          {--limit=50 : Maximum jobs to queue}';
    
    protected $description = 'Schedule crawl jobs and manage crawl queues';

    public function handle(CrawlSchedulingService $schedulingService): int
    {
        if ($this->option('queue')) {
            return $this->queuePendingJobs($schedulingService);
        }
        
        if ($this->option('setup-recurring')) {
            return $this->setupRecurringCrawls($schedulingService);
        }
        
        return $this->scheduleAllSources($schedulingService);
    }
    
    protected function queuePendingJobs(CrawlSchedulingService $schedulingService): int
    {
        $limit = (int) $this->option('limit');
        
        $this->info("Queueing pending crawl jobs (limit: {$limit})...");
        
        $queuedCount = $schedulingService->queuePendingJobs($limit);
        
        $this->info("Queued {$queuedCount} crawl jobs");
        
        // Show queue status
        $status = $schedulingService->getQueueStatus();
        $this->displayQueueStatus($status);
        
        return self::SUCCESS;
    }
    
    protected function setupRecurringCrawls(CrawlSchedulingService $schedulingService): int
    {
        $this->info("Setting up recurring crawl schedules...");
        
        $schedulingService->setupRecurringCrawls();
        
        $this->info("Recurring crawl schedules configured:");
        $this->line("- High credibility sources: hourly");
        $this->line("- Medium credibility sources: daily");  
        $this->line("- Low credibility sources: weekly");
        
        return self::SUCCESS;
    }
    
    protected function scheduleAllSources(CrawlSchedulingService $schedulingService): int
    {
        $frequency = $this->option('frequency');
        
        $this->info("Scheduling crawls for all active sources (frequency: {$frequency})...");
        
        $scheduledCount = $schedulingService->scheduleAllActiveSources($frequency);
        
        $this->info("Scheduled {$scheduledCount} source crawls");
        
        return self::SUCCESS;
    }
    
    protected function displayQueueStatus(array $status): void
    {
        $this->line("");
        $this->info("Queue Status:");
        $this->line("- Pending jobs in DB: {$status['pending_jobs']}");
        $this->line("- Running jobs: {$status['running_jobs']}");
        $this->line("- Queue size: {$status['queue_size']}");
        $this->line("- Failed queue size: {$status['failed_queue_size']}");
        
        $healthColor = $status['health'] === 'healthy' ? 'info' : 'warn';
        $this->$healthColor("Health: " . strtoupper($status['health']));
        
        if (!empty($status['issues'])) {
            foreach ($status['issues'] as $issue) {
                $this->warn("Issue: {$issue}");
            }
        }
    }
}