<?php

namespace App\Console\Commands;

use App\Services\PythonCrawlerService;
use Illuminate\Console\Command;

class PythonCrawlProcess extends Command
{
    protected $signature = 'crawl:python:process {--max-jobs=10 : Maximum number of jobs to process} {--continuous : Run continuously}';
    
    protected $description = 'Process crawl jobs using Python crawlers';

    public function handle(PythonCrawlerService $pythonCrawler): int
    {
        $this->info("Checking Python environment...");
        
        $envCheck = $pythonCrawler->checkPythonEnvironment();
        
        if (!$envCheck['ready']) {
            $this->error("Python environment is not ready:");
            foreach ($envCheck['checks'] as $check => $result) {
                if (!($result['available'] ?? $result['exists'] ?? $result['installed'] ?? false)) {
                    $error = $result['error'] ?? 'Not available';
                    $this->line("  ✗ {$check}: {$error}");
                }
            }
            return self::FAILURE;
        }
        
        $this->info("✓ Python environment ready");
        
        $maxJobs = (int) $this->option('max-jobs');
        $continuous = $this->option('continuous');
        
        if ($continuous) {
            $this->info("Starting continuous Python job processor...");
            
            $result = $pythonCrawler->startContinuousProcessor();
            
            if ($result['success']) {
                $this->info("Continuous processor started successfully");
                $this->line("Process ID: " . ($result['process_id'] ?? 'N/A'));
                $this->line("The processor is now running in the background.");
                $this->line("Use 'ps aux | grep process_jobs.py' to check if it's running.");
            } else {
                $this->error("Failed to start continuous processor: " . $result['error']);
                return self::FAILURE;
            }
        } else {
            $this->info("Processing up to {$maxJobs} crawl jobs with Python...");
            
            $result = $pythonCrawler->processPendingJobs($maxJobs);
            
            if ($result['success']) {
                $this->info("Python job processing completed:");
                $this->line("- Jobs processed: {$result['jobs_processed']}");
                
                if (!empty($result['output'])) {
                    $this->line("\nPython output:");
                    $this->line($result['output']);
                }
            } else {
                $this->error("Python job processing failed: " . $result['error']);
                return self::FAILURE;
            }
        }
        
        return self::SUCCESS;
    }
}