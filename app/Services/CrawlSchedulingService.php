<?php

namespace App\Services;

use App\Jobs\ProcessCrawlJobQueue;
use App\Jobs\ScheduledSourceCrawl;
use App\Models\CrawlJob;
use App\Models\Source;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

class CrawlSchedulingService
{
    public function queueCrawlJob(CrawlJob $crawlJob): void
    {
        ProcessCrawlJobQueue::dispatch($crawlJob);

        Log::info('Crawl job queued', [
            'crawl_job_id' => $crawlJob->id,
            'url' => $crawlJob->url,
        ]);
    }

    public function queuePendingJobs(int $limit = 50): int
    {
        $pendingJobs = CrawlJob::pending()
            ->where('scheduled_at', '<=', now())
            ->orderBy('priority', 'desc')
            ->orderBy('scheduled_at', 'asc')
            ->limit($limit)
            ->get();

        $queuedCount = 0;

        foreach ($pendingJobs as $job) {
            $this->queueCrawlJob($job);
            $queuedCount++;
        }

        Log::info('Queued pending crawl jobs', [
            'queued_count' => $queuedCount,
            'limit' => $limit,
        ]);

        return $queuedCount;
    }

    public function scheduleSourceCrawl(Source $source, string $frequency = 'daily'): void
    {
        $delay = $this->calculateScheduleDelay($frequency);

        ScheduledSourceCrawl::dispatch($source, $frequency)->delay($delay);

        Log::info('Scheduled source crawl', [
            'source_id' => $source->id,
            'domain' => $source->domain,
            'frequency' => $frequency,
            'delay_minutes' => $delay,
        ]);
    }

    public function scheduleAllActiveSources(string $frequency = 'daily'): int
    {
        $sources = Source::where('is_active', true)->get();
        $scheduledCount = 0;

        foreach ($sources as $source) {
            // Add random delay to spread the load
            $randomDelay = rand(0, 60); // 0-60 minutes

            ScheduledSourceCrawl::dispatch($source, $frequency)
                ->delay(now()->addMinutes($randomDelay));

            $scheduledCount++;
        }

        Log::info('Scheduled crawls for all active sources', [
            'sources_count' => $scheduledCount,
            'frequency' => $frequency,
        ]);

        return $scheduledCount;
    }

    public function setupRecurringCrawls(): void
    {
        // Schedule high-priority sources more frequently
        $highPrioritySources = Source::where('is_active', true)
            ->where('credibility_score', '>=', 0.8)
            ->get();

        foreach ($highPrioritySources as $source) {
            $this->scheduleRecurringSourceCrawl($source, 'hourly');
        }

        // Schedule medium-priority sources daily
        $mediumPrioritySources = Source::where('is_active', true)
            ->where('credibility_score', '>=', 0.5)
            ->where('credibility_score', '<', 0.8)
            ->get();

        foreach ($mediumPrioritySources as $source) {
            $this->scheduleRecurringSourceCrawl($source, 'daily');
        }

        // Schedule low-priority sources weekly
        $lowPrioritySources = Source::where('is_active', true)
            ->where('credibility_score', '<', 0.5)
            ->get();

        foreach ($lowPrioritySources as $source) {
            $this->scheduleRecurringSourceCrawl($source, 'weekly');
        }

        Log::info('Set up recurring crawls', [
            'high_priority' => $highPrioritySources->count(),
            'medium_priority' => $mediumPrioritySources->count(),
            'low_priority' => $lowPrioritySources->count(),
        ]);
    }

    protected function scheduleRecurringSourceCrawl(Source $source, string $frequency): void
    {
        // Use Laravel's scheduler instead of immediate dispatch for recurring jobs
        // This would typically be called from the scheduler
        $this->scheduleSourceCrawl($source, $frequency);
    }

    protected function calculateScheduleDelay(string $frequency): int
    {
        return match ($frequency) {
            'immediate' => 0,
            'hourly' => rand(0, 30), // 0-30 minutes
            'daily' => rand(0, 120), // 0-2 hours
            'weekly' => rand(0, 360), // 0-6 hours
            'monthly' => rand(0, 720), // 0-12 hours
            default => rand(0, 60)
        };
    }

    public function getQueueStatus(): array
    {
        $status = [
            'pending_jobs' => CrawlJob::pending()->count(),
            'running_jobs' => CrawlJob::running()->count(),
            'queue_size' => $this->getQueueSize('crawling'),
            'failed_queue_size' => $this->getFailedQueueSize(),
        ];

        // Add queue health indicators
        $status['health'] = 'healthy';
        $status['issues'] = [];

        if ($status['queue_size'] > 1000) {
            $status['health'] = 'degraded';
            $status['issues'][] = 'High queue size';
        }

        if ($status['failed_queue_size'] > 50) {
            $status['health'] = 'degraded';
            $status['issues'][] = 'High failed queue size';
        }

        return $status;
    }

    protected function getQueueSize(string $queue = 'default'): int
    {
        try {
            return Queue::size($queue);
        } catch (\Exception $e) {
            Log::warning('Could not get queue size', ['error' => $e->getMessage()]);

            return 0;
        }
    }

    protected function getFailedQueueSize(): int
    {
        try {
            // This depends on your queue driver
            // For database driver, you could query the failed_jobs table
            return \DB::table('failed_jobs')->count();
        } catch (\Exception $e) {
            Log::warning('Could not get failed queue size', ['error' => $e->getMessage()]);

            return 0;
        }
    }

    public function prioritizeCrawlsForSource(Source $source, int $priority = 10): int
    {
        $updated = CrawlJob::where('source_id', $source->id)
            ->whereIn('status', ['pending'])
            ->update(['priority' => $priority]);

        Log::info('Prioritized crawl jobs for source', [
            'source_id' => $source->id,
            'updated_jobs' => $updated,
            'new_priority' => $priority,
        ]);

        return $updated;
    }

    public function pauseCrawlsForSource(Source $source): int
    {
        $paused = CrawlJob::where('source_id', $source->id)
            ->where('status', 'pending')
            ->update([
                'status' => 'paused',
                'metadata' => \DB::raw("JSON_SET(COALESCE(metadata, '{}'), '$.paused_at', '".now()->toISOString()."')"),
            ]);

        Log::info('Paused crawl jobs for source', [
            'source_id' => $source->id,
            'paused_jobs' => $paused,
        ]);

        return $paused;
    }

    public function resumeCrawlsForSource(Source $source): int
    {
        $resumed = CrawlJob::where('source_id', $source->id)
            ->where('status', 'paused')
            ->update([
                'status' => 'pending',
                'metadata' => \DB::raw("JSON_SET(COALESCE(metadata, '{}'), '$.resumed_at', '".now()->toISOString()."')"),
            ]);

        Log::info('Resumed crawl jobs for source', [
            'source_id' => $source->id,
            'resumed_jobs' => $resumed,
        ]);

        return $resumed;
    }

    public function cancelPendingJobsForSource(Source $source): int
    {
        $cancelled = CrawlJob::where('source_id', $source->id)
            ->where('status', 'pending')
            ->update([
                'status' => 'cancelled',
                'completed_at' => now(),
                'metadata' => \DB::raw("JSON_SET(COALESCE(metadata, '{}'), '$.cancelled_at', '".now()->toISOString()."')"),
            ]);

        Log::info('Cancelled pending crawl jobs for source', [
            'source_id' => $source->id,
            'cancelled_jobs' => $cancelled,
        ]);

        return $cancelled;
    }

    public function getSchedulingStats(): array
    {
        $stats = [
            'total_pending' => CrawlJob::pending()->count(),
            'total_running' => CrawlJob::running()->count(),
            'scheduled_future' => CrawlJob::pending()->where('scheduled_at', '>', now())->count(),
            'overdue' => CrawlJob::pending()->where('scheduled_at', '<', now()->subHours(1))->count(),
            'high_priority' => CrawlJob::pending()->where('priority', '>', 0)->count(),
        ];

        // Get stats by source
        $stats['by_source'] = CrawlJob::pending()
            ->selectRaw('source_id, COUNT(*) as pending_count')
            ->groupBy('source_id')
            ->with('source:id,name,domain')
            ->get()
            ->map(function ($item) {
                return [
                    'source_id' => $item->source_id,
                    'source_name' => $item->source->name ?? 'Unknown',
                    'source_domain' => $item->source->domain ?? 'Unknown',
                    'pending_count' => $item->pending_count,
                ];
            });

        return $stats;
    }
}
