<?php

namespace App\Jobs;

use App\Models\Source;
use App\Services\CrawlerOrchestrationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

class ScheduledSourceCrawl implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $timeout = 600; // 10 minutes

    protected Source $source;
    protected string $frequency;

    public function __construct(Source $source, string $frequency = 'daily')
    {
        $this->source = $source;
        $this->frequency = $frequency;
        $this->onQueue('crawling');
    }

    public function handle(CrawlerOrchestrationService $crawlerService): void
    {
        try {
            Log::info("Processing scheduled source crawl", [
                'job_id' => $this->job->getJobId(),
                'source_id' => $this->source->id,
                'domain' => $this->source->domain,
                'frequency' => $this->frequency
            ]);

            // Check if source is still active
            if (!$this->source->is_active) {
                Log::info("Skipping crawl - source is inactive", [
                    'source_id' => $this->source->id,
                    'domain' => $this->source->domain
                ]);
                return;
            }

            $result = $crawlerService->crawlSource($this->source, [
                'process_immediately' => false, // Let individual jobs be queued
                'metadata' => [
                    'scheduled' => true,
                    'frequency' => $this->frequency,
                    'scheduled_at' => now()->toISOString()
                ]
            ]);

            Log::info("Scheduled crawl completed", [
                'job_id' => $this->job->getJobId(),
                'source_id' => $this->source->id,
                'jobs_created' => $result['jobs_created']
            ]);

        } catch (Exception $e) {
            Log::error("Scheduled crawl failed", [
                'job_id' => $this->job->getJobId(),
                'source_id' => $this->source->id,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    public function failed(Exception $exception): void
    {
        Log::error("Scheduled source crawl permanently failed", [
            'source_id' => $this->source->id,
            'domain' => $this->source->domain,
            'frequency' => $this->frequency,
            'error' => $exception->getMessage()
        ]);
    }
}