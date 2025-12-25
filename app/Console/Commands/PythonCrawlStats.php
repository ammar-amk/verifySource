<?php

namespace App\Console\Commands;

use App\Services\PythonCrawlerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PythonCrawlStats extends Command
{
    protected $signature = 'crawl:python:stats {--reset : Reset all crawl statistics} {--source-id= : Show stats for specific source}';

    protected $description = 'Show Python crawl statistics and performance metrics';

    public function handle(PythonCrawlerService $pythonCrawler): int
    {
        if ($this->option('reset')) {
            return $this->resetStats();
        }

        $sourceId = $this->option('source-id');

        $this->info('Python Crawler Statistics');
        $this->line('========================');

        // Python environment status
        $envCheck = $pythonCrawler->checkPythonEnvironment();
        $this->line('Environment Status: '.($envCheck['ready'] ? '✓ Ready' : '✗ Not Ready'));

        if (! $envCheck['ready']) {
            $this->warn("Run 'php artisan crawl:python:check' for setup instructions.");

            return self::FAILURE;
        }

        $this->line('');

        // Overall statistics
        if ($sourceId) {
            $this->showSourceStats($sourceId);
        } else {
            $this->showOverallStats();
        }

        return self::SUCCESS;
    }

    private function showOverallStats(): void
    {
        // Crawl Jobs statistics
        $jobStats = DB::table('crawl_jobs')
            ->select(
                DB::raw('COUNT(*) as total_jobs'),
                DB::raw('COUNT(CASE WHEN status = "completed" THEN 1 END) as completed_jobs'),
                DB::raw('COUNT(CASE WHEN status = "failed" THEN 1 END) as failed_jobs'),
                DB::raw('COUNT(CASE WHEN status = "pending" THEN 1 END) as pending_jobs'),
                DB::raw('AVG(CASE WHEN execution_time IS NOT NULL THEN execution_time END) as avg_execution_time')
            )
            ->first();

        $this->info('Crawl Jobs Overview:');
        $this->line('  Total jobs: '.number_format($jobStats->total_jobs));
        $this->line('  Completed: '.number_format($jobStats->completed_jobs).' ('.$this->percentage($jobStats->completed_jobs, $jobStats->total_jobs).'%)');
        $this->line('  Failed: '.number_format($jobStats->failed_jobs).' ('.$this->percentage($jobStats->failed_jobs, $jobStats->total_jobs).'%)');
        $this->line('  Pending: '.number_format($jobStats->pending_jobs));
        $this->line('  Avg execution time: '.number_format($jobStats->avg_execution_time ?? 0, 2).'s');

        $this->line('');

        // Articles statistics
        $articleStats = DB::table('articles')
            ->select(
                DB::raw('COUNT(*) as total_articles'),
                DB::raw('COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as articles_today'),
                DB::raw('COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as articles_week'),
                DB::raw('AVG(word_count) as avg_word_count'),
                DB::raw('AVG(quality_score) as avg_quality_score')
            )
            ->first();

        $this->info('Articles Overview:');
        $this->line('  Total articles: '.number_format($articleStats->total_articles));
        $this->line('  Articles today: '.number_format($articleStats->articles_today));
        $this->line('  Articles this week: '.number_format($articleStats->articles_week));
        $this->line('  Avg word count: '.number_format($articleStats->avg_word_count ?? 0));
        $this->line('  Avg quality score: '.number_format($articleStats->avg_quality_score ?? 0, 2));

        $this->line('');

        // Sources statistics
        $sourceStats = DB::table('sources')
            ->select(
                DB::raw('COUNT(*) as total_sources'),
                DB::raw('COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_sources'),
                DB::raw('COUNT(CASE WHEN last_crawl_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as crawled_today')
            )
            ->first();

        $this->info('Sources Overview:');
        $this->line('  Total sources: '.number_format($sourceStats->total_sources));
        $this->line('  Active sources: '.number_format($sourceStats->active_sources));
        $this->line('  Crawled today: '.number_format($sourceStats->crawled_today));

        // Recent activity
        $this->line('');
        $this->info('Recent Activity (last 10 jobs):');

        $recentJobs = DB::table('crawl_jobs')
            ->leftJoin('sources', 'crawl_jobs.source_id', '=', 'sources.id')
            ->select('crawl_jobs.*', 'sources.name as source_name')
            ->orderBy('crawl_jobs.created_at', 'desc')
            ->limit(10)
            ->get();

        if ($recentJobs->isEmpty()) {
            $this->line('  No recent jobs found');
        } else {
            foreach ($recentJobs as $job) {
                $status = match ($job->status) {
                    'completed' => '✓',
                    'failed' => '✗',
                    'pending' => '⧖',
                    'processing' => '⚙',
                    default => '?'
                };

                $source = $job->source_name ?? 'Unknown';
                $time = \Carbon\Carbon::parse($job->created_at)->diffForHumans();

                $this->line("  {$status} {$job->job_type} - {$source} ({$time})");
            }
        }
    }

    private function showSourceStats(string $sourceId): void
    {
        $source = DB::table('sources')->where('id', $sourceId)->first();

        if (! $source) {
            $this->error("Source not found: {$sourceId}");

            return;
        }

        $this->info("Source Statistics: {$source->name}");
        $this->line("Domain: {$source->domain}");
        $this->line('Status: '.($source->is_active ? 'Active' : 'Inactive'));
        $this->line('Last crawl: '.($source->last_crawl_at ? \Carbon\Carbon::parse($source->last_crawl_at)->diffForHumans() : 'Never'));
        $this->line('');

        // Jobs for this source
        $jobStats = DB::table('crawl_jobs')
            ->where('source_id', $sourceId)
            ->select(
                DB::raw('COUNT(*) as total_jobs'),
                DB::raw('COUNT(CASE WHEN status = "completed" THEN 1 END) as completed_jobs'),
                DB::raw('COUNT(CASE WHEN status = "failed" THEN 1 END) as failed_jobs'),
                DB::raw('AVG(CASE WHEN execution_time IS NOT NULL THEN execution_time END) as avg_execution_time')
            )
            ->first();

        $this->info('Crawl Jobs:');
        $this->line('  Total: '.number_format($jobStats->total_jobs));
        $this->line('  Completed: '.number_format($jobStats->completed_jobs));
        $this->line('  Failed: '.number_format($jobStats->failed_jobs));
        $this->line('  Avg execution: '.number_format($jobStats->avg_execution_time ?? 0, 2).'s');

        // Articles for this source
        $articleStats = DB::table('articles')
            ->where('source_id', $sourceId)
            ->select(
                DB::raw('COUNT(*) as total_articles'),
                DB::raw('AVG(word_count) as avg_word_count'),
                DB::raw('AVG(quality_score) as avg_quality_score')
            )
            ->first();

        $this->line('');
        $this->info('Articles:');
        $this->line('  Total: '.number_format($articleStats->total_articles));
        $this->line('  Avg word count: '.number_format($articleStats->avg_word_count ?? 0));
        $this->line('  Avg quality: '.number_format($articleStats->avg_quality_score ?? 0, 2));
    }

    private function resetStats(): int
    {
        if (! $this->confirm('Are you sure you want to reset all crawl statistics? This will clear job history but keep articles.')) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        DB::table('crawl_jobs')->truncate();

        DB::table('sources')->update([
            'last_crawl_at' => null,
            'total_articles' => 0,
        ]);

        $this->info('✓ Crawl statistics have been reset.');

        return self::SUCCESS;
    }

    private function percentage(int $part, int $total): string
    {
        if ($total === 0) {
            return '0';
        }

        return number_format(($part / $total) * 100, 1);
    }
}
