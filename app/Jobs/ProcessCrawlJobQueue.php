<?php

namespace App\Jobs;

use App\Models\CrawlJob;
use App\Services\CrawlerOrchestrationService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessCrawlJobQueue implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $timeout = 300; // 5 minutes

    public $backoff = [60, 120, 300]; // 1 min, 2 min, 5 min

    protected CrawlJob $crawlJob;

    public function __construct(CrawlJob $crawlJob)
    {
        $this->crawlJob = $crawlJob;
        $this->onQueue('crawling');
    }

    public function handle(CrawlerOrchestrationService $crawlerService): void
    {
        try {
            Log::info('Processing crawl job in queue', [
                'job_id' => $this->job->getJobId(),
                'crawl_job_id' => $this->crawlJob->id,
                'url' => $this->crawlJob->url,
            ]);

            $result = $crawlerService->processCrawlJob($this->crawlJob);

            Log::info('Queue job completed', [
                'job_id' => $this->job->getJobId(),
                'crawl_job_id' => $this->crawlJob->id,
                'success' => $result['success'],
                'articles_created' => $result['articles_created'],
                'urls_discovered' => $result['urls_discovered'],
            ]);

        } catch (Exception $e) {
            Log::error('Queue job failed', [
                'job_id' => $this->job->getJobId(),
                'crawl_job_id' => $this->crawlJob->id,
                'error' => $e->getMessage(),
            ]);

            throw $e; // Re-throw to trigger job retry mechanism
        }
    }

    public function failed(Exception $exception): void
    {
        Log::error('Crawl job permanently failed in queue', [
            'crawl_job_id' => $this->crawlJob->id,
            'url' => $this->crawlJob->url,
            'error' => $exception->getMessage(),
        ]);

        // Mark the crawl job as failed if it's not already
        if ($this->crawlJob->status !== 'failed') {
            $this->crawlJob->update([
                'status' => 'failed',
                'error_message' => 'Queue job failed: '.$exception->getMessage(),
                'completed_at' => now(),
            ]);
        }
    }

    public function retryUntil(): \DateTime
    {
        return now()->addHours(24); // Don't retry after 24 hours
    }
}
