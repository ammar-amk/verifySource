<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Resetting Stuck Jobs ===\n\n";

// Find jobs that have been running for more than 1 hour
$stuckJobs = App\Models\CrawlJob::where('status', 'running')
    ->where('started_at', '<', now()->subHour())
    ->get();

echo "Found {$stuckJobs->count()} stuck jobs\n\n";

foreach ($stuckJobs as $job) {
    $duration = $job->started_at->diffForHumans();
    echo "Resetting job [{$job->id}] {$job->url}\n";
    echo "  Was running for: {$duration}\n";
    
    $job->update([
        'status' => 'failed',
        'completed_at' => now(),
        'error_message' => 'Job timed out - was running for more than 1 hour'
    ]);
    
    echo "  ✓ Marked as failed\n\n";
}

echo "Done! Run 'php artisan crawl:process' to continue processing pending jobs.\n";
