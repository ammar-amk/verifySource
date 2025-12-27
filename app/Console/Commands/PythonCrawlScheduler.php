<?php

namespace App\Console\Commands;

use App\Services\PythonCrawlerService;
use Illuminate\Console\Command;

class PythonCrawlScheduler extends Command
{
    protected $signature = 'crawl:python:scheduler {--dry-run : Show what would be processed without executing} {--force : Force crawl even if recently crawled}';

    protected $description = 'Process scheduled crawl jobs with Python crawler';

    public function handle(PythonCrawlerService $pythonCrawler): int
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        $this->info('Processing Python crawl scheduler'.($dryRun ? ' (DRY RUN)' : ''));

        // Check Python environment
        $envCheck = $pythonCrawler->checkPythonEnvironment();
        if (! $envCheck['ready']) {
            $this->error("Python environment is not ready. Run 'php artisan crawl:python:check' for details.");

            return self::FAILURE;
        }

        // Get pending jobs
        $pendingJobs = $pythonCrawler->getPendingJobs($force);

        if (empty($pendingJobs)) {
            $this->info('No pending crawl jobs found.');

            return self::SUCCESS;
        }

        $this->info('Found '.count($pendingJobs).' pending jobs:');

        $totalProcessed = 0;
        $totalSuccessful = 0;
        $totalFailed = 0;

        foreach ($pendingJobs as $job) {
            $this->line('');
            $this->info("Job #{$job['id']}: {$job['type']} - {$job['target']}");

            if ($dryRun) {
                $this->line("  Would process: {$job['type']} job for ".($job['source'] ?? 'unknown source'));

                continue;
            }

            // Process the job
            $result = $pythonCrawler->processJob($job);

            $totalProcessed++;

            if ($result['success']) {
                $totalSuccessful++;
                $this->info('  ✓ Completed successfully');

                if (! empty($result['stats'])) {
                    $stats = $result['stats'];
                    $this->line("    Articles: {$stats['articles_crawled']}, New: {$stats['new_articles']}, Failed: {$stats['failed_urls']}");
                }

            } else {
                $totalFailed++;
                $this->error('  ✗ Failed: '.$result['error']);
            }
        }

        if (! $dryRun) {
            $this->line('');
            $this->info('=== Processing Summary ===');
            $this->line("Total jobs processed: {$totalProcessed}");
            $this->line("Successful: {$totalSuccessful}");
            $this->line("Failed: {$totalFailed}");

            if ($totalFailed > 0) {
                $this->warn('Some jobs failed. Check logs for details.');

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
