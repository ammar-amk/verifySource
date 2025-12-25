<?php

namespace App\Console\Commands;

use App\Models\CrawlJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupCrawlJobs extends Command
{
    protected $signature = 'crawl:cleanup 
                          {--days=30 : Delete jobs older than this many days}
                          {--dry-run : Show what would be deleted without deleting}
                          {--include-failed : Also delete failed jobs}';

    protected $description = 'Clean up old crawl jobs from the database';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        $includeFailed = $this->option('include-failed');

        $this->info("Cleaning up crawl jobs older than {$days} days".($dryRun ? ' (DRY RUN)' : ''));

        $cutoffDate = now()->subDays($days);

        // Build query
        $query = CrawlJob::where('created_at', '<', $cutoffDate);

        if (!$includeFailed) {
            $query->where('status', '!=', 'failed');
        }

        // Count by status
        $statusCounts = (clone $query)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $totalCount = $query->count();

        if ($totalCount === 0) {
            $this->info('No crawl jobs found to clean up.');
            return self::SUCCESS;
        }

        // Display what will be deleted
        $this->line('');
        $this->info("Jobs to be deleted:");
        foreach ($statusCounts as $status => $count) {
            $this->line("  - {$status}: {$count}");
        }
        $this->line("Total: {$totalCount}");

        if ($dryRun) {
            $this->warn('DRY RUN - No jobs were deleted.');
            return self::SUCCESS;
        }

        // Confirm deletion
        if (!$this->confirm("Are you sure you want to delete {$totalCount} crawl jobs?", true)) {
            $this->info('Operation cancelled.');
            return self::SUCCESS;
        }

        // Perform deletion
        $deletedCount = $query->delete();

        $this->info("Successfully deleted {$deletedCount} crawl jobs.");

        Log::info('Crawl jobs cleanup completed', [
            'deleted_count' => $deletedCount,
            'days' => $days,
            'cutoff_date' => $cutoffDate->toISOString(),
            'status_breakdown' => $statusCounts,
        ]);

        return self::SUCCESS;
    }
}
