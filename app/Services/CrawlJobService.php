<?php

namespace App\Services;

use App\Models\CrawlJob;
use App\Models\Source;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class CrawlJobService
{
    public function createCrawlJob(Source $source, string $url, array $options = []): CrawlJob
    {
        $crawlJob = CrawlJob::create([
            'source_id' => $source->id,
            'url' => $url,
            'status' => 'pending',
            'priority' => $options['priority'] ?? 0,
            'retry_count' => 0,
            'max_retries' => $options['max_retries'] ?? 3,
            'metadata' => $options['metadata'] ?? [],
            'scheduled_at' => $options['scheduled_at'] ?? now(),
        ]);

        Log::info("Crawl job created", [
            'crawl_job_id' => $crawlJob->id,
            'source_id' => $source->id,
            'url' => $url,
            'priority' => $crawlJob->priority
        ]);

        return $crawlJob;
    }

    public function createBulkCrawlJobs(Source $source, array $urls, array $options = []): Collection
    {
        $jobs = collect();
        
        foreach ($urls as $url) {
            // Check if job already exists for this URL
            if (!$this->jobExistsForUrl($url)) {
                $jobs->push($this->createCrawlJob($source, $url, $options));
            }
        }

        Log::info("Bulk crawl jobs created", [
            'source_id' => $source->id,
            'job_count' => $jobs->count(),
            'total_urls' => count($urls)
        ]);

        return $jobs;
    }

    public function markJobAsRunning(CrawlJob $crawlJob): CrawlJob
    {
        $crawlJob->update([
            'status' => 'running',
            'started_at' => now(),
            'error_message' => null,
        ]);

        Log::info("Crawl job marked as running", [
            'crawl_job_id' => $crawlJob->id,
            'url' => $crawlJob->url
        ]);

        return $crawlJob;
    }

    public function markJobAsCompleted(CrawlJob $crawlJob, array $metadata = []): CrawlJob
    {
        $crawlJob->update([
            'status' => 'completed',
            'completed_at' => now(),
            'metadata' => array_merge($crawlJob->metadata ?? [], $metadata),
        ]);

        Log::info("Crawl job completed", [
            'crawl_job_id' => $crawlJob->id,
            'url' => $crawlJob->url,
            'duration' => $crawlJob->started_at ? $crawlJob->started_at->diffInSeconds($crawlJob->completed_at) : null
        ]);

        return $crawlJob;
    }

    public function markJobAsFailed(CrawlJob $crawlJob, string $errorMessage, bool $shouldRetry = true): CrawlJob
    {
        $crawlJob->increment('retry_count');
        
        $status = ($shouldRetry && $crawlJob->canRetry()) ? 'pending' : 'failed';
        
        $crawlJob->update([
            'status' => $status,
            'error_message' => $errorMessage,
            'completed_at' => $status === 'failed' ? now() : null,
        ]);

        Log::warning("Crawl job failed", [
            'crawl_job_id' => $crawlJob->id,
            'url' => $crawlJob->url,
            'error' => $errorMessage,
            'retry_count' => $crawlJob->retry_count,
            'will_retry' => $status === 'pending'
        ]);

        return $crawlJob;
    }

    public function getNextPendingJob(): ?CrawlJob
    {
        return CrawlJob::pending()
            ->orderBy('priority', 'desc')
            ->orderBy('scheduled_at', 'asc')
            ->orderBy('created_at', 'asc')
            ->first();
    }

    public function getPendingJobs(int $limit = 10): Collection
    {
        return CrawlJob::pending()
            ->orderBy('priority', 'desc')
            ->orderBy('scheduled_at', 'asc')
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get();
    }

    public function getRunningJobs(): Collection
    {
        return CrawlJob::running()->get();
    }

    public function getFailedJobs(): Collection
    {
        return CrawlJob::failed()->get();
    }

    public function getJobsBySource(Source $source, string $status = null): Collection
    {
        $query = CrawlJob::where('source_id', $source->id);
        
        if ($status) {
            $query->where('status', $status);
        }
        
        return $query->orderBy('created_at', 'desc')->get();
    }

    public function retryFailedJobs(int $maxAge = 24): int
    {
        $cutoffTime = now()->subHours($maxAge);
        
        $failedJobs = CrawlJob::failed()
            ->where('completed_at', '>=', $cutoffTime)
            ->whereColumn('retry_count', '<', 'max_retries')
            ->get();

        $retryCount = 0;
        
        foreach ($failedJobs as $job) {
            $job->update([
                'status' => 'pending',
                'error_message' => null,
                'completed_at' => null,
                'scheduled_at' => now()->addMinutes(rand(1, 10)), // Random delay to spread load
            ]);
            
            $retryCount++;
        }

        Log::info("Failed jobs retried", [
            'retry_count' => $retryCount,
            'max_age_hours' => $maxAge
        ]);

        return $retryCount;
    }

    public function cleanupOldJobs(int $daysToKeep = 30): int
    {
        $cutoffDate = now()->subDays($daysToKeep);
        
        $deletedCount = CrawlJob::where('created_at', '<', $cutoffDate)
            ->whereIn('status', ['completed', 'failed'])
            ->delete();

        Log::info("Old crawl jobs cleaned up", [
            'deleted_count' => $deletedCount,
            'cutoff_date' => $cutoffDate->toDateString()
        ]);

        return $deletedCount;
    }

    public function getCrawlJobStats(): array
    {
        $stats = [
            'pending' => CrawlJob::pending()->count(),
            'running' => CrawlJob::running()->count(),
            'completed' => CrawlJob::completed()->count(),
            'failed' => CrawlJob::failed()->count(),
        ];

        $stats['total'] = array_sum($stats);
        
        // Get average completion time for jobs completed in the last 24 hours
        $recentCompletedJobs = CrawlJob::completed()
            ->where('completed_at', '>=', now()->subDay())
            ->whereNotNull('started_at')
            ->get();

        if ($recentCompletedJobs->count() > 0) {
            $totalDuration = $recentCompletedJobs->sum(function ($job) {
                return $job->started_at->diffInSeconds($job->completed_at);
            });
            
            $stats['average_completion_time'] = round($totalDuration / $recentCompletedJobs->count(), 2);
        } else {
            $stats['average_completion_time'] = 0;
        }

        // Get success rate for jobs completed in the last 24 hours
        $recentJobs = CrawlJob::whereIn('status', ['completed', 'failed'])
            ->where('completed_at', '>=', now()->subDay())
            ->get();

        if ($recentJobs->count() > 0) {
            $successCount = $recentJobs->where('status', 'completed')->count();
            $stats['success_rate'] = round(($successCount / $recentJobs->count()) * 100, 2);
        } else {
            $stats['success_rate'] = 0;
        }

        return $stats;
    }

    public function scheduleCrawlJob(Source $source, string $url, Carbon $scheduledAt, array $options = []): CrawlJob
    {
        $options['scheduled_at'] = $scheduledAt;
        
        return $this->createCrawlJob($source, $url, $options);
    }

    public function scheduleRecurringCrawl(Source $source, string $frequency = 'daily'): Collection
    {
        $urls = $this->generateCrawlUrls($source);
        $jobs = collect();
        
        $scheduledAt = $this->calculateNextScheduledTime($frequency);
        
        foreach ($urls as $url) {
            $jobs->push($this->scheduleCrawlJob($source, $url, $scheduledAt, [
                'metadata' => ['type' => 'recurring', 'frequency' => $frequency]
            ]));
        }

        Log::info("Recurring crawl scheduled", [
            'source_id' => $source->id,
            'frequency' => $frequency,
            'job_count' => $jobs->count(),
            'scheduled_at' => $scheduledAt->toDateTimeString()
        ]);

        return $jobs;
    }

    protected function jobExistsForUrl(string $url): bool
    {
        return CrawlJob::where('url', $url)
            ->whereIn('status', ['pending', 'running'])
            ->exists();
    }

    protected function generateCrawlUrls(Source $source): array
    {
        // This could be enhanced to generate multiple URLs based on source type
        // For now, just return the main source URL and common paths
        $baseUrl = $source->url;
        
        return [
            $baseUrl,
            $baseUrl . '/sitemap.xml',
            $baseUrl . '/feed',
            $baseUrl . '/rss',
            $baseUrl . '/atom.xml',
        ];
    }

    protected function calculateNextScheduledTime(string $frequency): Carbon
    {
        return match ($frequency) {
            'hourly' => now()->addHour(),
            'daily' => now()->addDay(),
            'weekly' => now()->addWeek(),
            'monthly' => now()->addMonth(),
            default => now()->addDay(),
        };
    }

    public function prioritizeCrawlJob(CrawlJob $crawlJob, int $priority): CrawlJob
    {
        $crawlJob->update(['priority' => $priority]);
        
        Log::info("Crawl job priority updated", [
            'crawl_job_id' => $crawlJob->id,
            'new_priority' => $priority
        ]);

        return $crawlJob;
    }

    public function cancelCrawlJob(CrawlJob $crawlJob): CrawlJob
    {
        $crawlJob->update([
            'status' => 'cancelled',
            'completed_at' => now(),
        ]);

        Log::info("Crawl job cancelled", [
            'crawl_job_id' => $crawlJob->id,
            'url' => $crawlJob->url
        ]);

        return $crawlJob;
    }
}