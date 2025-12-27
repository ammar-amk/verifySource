<?php

namespace App\Console\Commands;

use App\Models\CrawlJob;
use App\Jobs\ProcessCrawlJobQueue;
use Illuminate\Console\Command;

class DispatchPendingJobs extends Command
{
    protected $signature = 'crawl:dispatch-pending 
                            {--limit=100 : Number of pending jobs to dispatch}
                            {--all : Dispatch all pending jobs}';

    protected $description = 'Dispatch pending crawl jobs to the Laravel queue for processing';

    public function handle(): int
    {
        $limit = $this->option('all') ? PHP_INT_MAX : (int) $this->option('limit');
        
        $this->info("📤 Dispatching pending crawl jobs to queue...");
        
        $pendingJobs = CrawlJob::where('status', 'pending')
            ->whereNull('dispatched_at')
            ->orderBy('priority', 'desc')
            ->orderBy('created_at')
            ->limit($limit)
            ->get();
        
        if ($pendingJobs->isEmpty()) {
            $this->info('✓ No pending jobs to dispatch');
            return self::SUCCESS;
        }
        
        $dispatched = 0;
        $bar = $this->output->createProgressBar($pendingJobs->count());
        $bar->start();
        
        foreach ($pendingJobs as $job) {
            try {
                ProcessCrawlJobQueue::dispatch($job)->onQueue('crawling');
                
                // Mark as dispatched to avoid re-dispatching
                $job->update(['dispatched_at' => now()]);
                
                $dispatched++;
                $bar->advance();
            } catch (\Exception $e) {
                $this->error("\nFailed to dispatch job {$job->id}: {$e->getMessage()}");
            }
        }
        
        $bar->finish();
        $this->newLine(2);
        
        $this->info("✓ Successfully dispatched {$dispatched} crawl jobs to queue");
        $this->line("  Run 'php artisan queue:work --queue=crawling' to process them");
        
        return self::SUCCESS;
    }
}
