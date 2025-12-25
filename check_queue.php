<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Checking Crawl Jobs ===\n\n";

// Check running jobs (might be stuck)
$runningJobs = App\Models\CrawlJob::where('status', 'running')->get();
echo "Running Jobs ({$runningJobs->count()}):\n";
foreach ($runningJobs as $job) {
    $duration = $job->started_at ? $job->started_at->diffForHumans() : 'Unknown';
    echo "  [{$job->id}] {$job->url}\n";
    echo "      Started: {$duration}\n";
    echo "      Source: {$job->source->name}\n";
}
echo "\n";

// Check pending jobs
$pendingJobs = App\Models\CrawlJob::where('status', 'pending')->orderBy('created_at', 'desc')->limit(10)->get();
echo "Pending Jobs (showing first 10 of " . App\Models\CrawlJob::where('status', 'pending')->count() . "):\n";
foreach ($pendingJobs as $job) {
    echo "  [{$job->id}] {$job->url}\n";
    echo "      Created: {$job->created_at->diffForHumans()}\n";
    echo "      Priority: {$job->priority}\n";
}
echo "\n";

// Check queue jobs in database
$queueJobs = DB::table('jobs')->count();
echo "Queue Jobs in Database: {$queueJobs}\n";

if ($queueJobs > 0) {
    $latestJob = DB::table('jobs')->latest('id')->first();
    echo "  Latest queue: {$latestJob->queue}\n";
    echo "  Attempts: {$latestJob->attempts}\n";
    echo "  Reserved: " . ($latestJob->reserved_at ? 'Yes' : 'No') . "\n";
}
echo "\n";

// Check if workers are processing
echo "To start processing, run:\n";
echo "  php artisan queue:work --queue=crawling\n";
echo "\nOr process jobs immediately:\n";
echo "  php artisan crawl:process --limit=10\n";
