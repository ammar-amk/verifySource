<?php

namespace App\Console\Commands;

use App\Jobs\ProcessCrawlJobQueue;
use App\Models\CrawlJob;
use Illuminate\Console\Command;

class DispatchLoop extends Command
{
    protected $signature = 'crawl:dispatch-loop 
                            {--sleep=10 : Seconds to sleep between dispatches}
                            {--batch=50 : Number of jobs to dispatch per batch}';

    protected $description = 'Continuously dispatch pending crawl jobs to the queue';

    public function handle(): int
    {
        $this->info('🚀 Job Dispatcher Started');
        $this->info('📊 Batch size: ' . $this->option('batch'));
        $this->info('⏱️  Sleep interval: ' . $this->option('sleep') . 's');
        $this->line('');

        $iteration = 0;
        $totalDispatched = 0;

        while (true) {
            $iteration++;
            
            // Get pending jobs that haven't been dispatched yet
            $pendingJobs = CrawlJob::where('status', 'pending')
                ->whereNull('dispatched_at')
                ->limit($this->option('batch'))
                ->get();

            if ($pendingJobs->count() > 0) {
                foreach ($pendingJobs as $job) {
                    ProcessCrawlJobQueue::dispatch($job)->onQueue('crawling');
                    $job->update(['dispatched_at' => now()]);
                    $totalDispatched++;
                }

                $this->line(sprintf(
                    '[%s] Iteration %d: Dispatched %d jobs (Total: %d)',
                    now()->format('H:i:s'),
                    $iteration,
                    $pendingJobs->count(),
                    $totalDispatched
                ));
            } else {
                $this->line(sprintf(
                    '[%s] Iteration %d: No pending jobs to dispatch',
                    now()->format('H:i:s'),
                    $iteration
                ));
            }

            sleep($this->option('sleep'));
        }

        return self::SUCCESS;
    }
}
