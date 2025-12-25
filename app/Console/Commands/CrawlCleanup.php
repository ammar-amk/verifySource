<?php

namespace App\Console\Commands;

use App\Services\CrawlJobService;
use Illuminate\Console\Command;

class CrawlCleanup extends Command
{
    protected $signature = 'crawl:cleanup {--days=30 : Days to keep completed jobs} {--retry-failed : Retry failed jobs}';

    protected $description = 'Clean up old crawl jobs and retry failed ones';

    public function handle(CrawlJobService $crawlJobService): int
    {
        $daysToKeep = (int) $this->option('days');

        if ($this->option('retry-failed')) {
            $this->info('Retrying failed jobs...');
            $retryCount = $crawlJobService->retryFailedJobs();
            $this->line("Retried {$retryCount} failed jobs");
        }

        $this->info("Cleaning up old crawl jobs (keeping {$daysToKeep} days)...");
        $deletedCount = $crawlJobService->cleanupOldJobs($daysToKeep);
        $this->line("Deleted {$deletedCount} old jobs");

        return self::SUCCESS;
    }
}
