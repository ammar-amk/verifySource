<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Database Status Check ===\n\n";

// Check Articles
$totalArticles = App\Models\Article::count();
$recentArticles = App\Models\Article::where('created_at', '>=', now()->subHours(24))->count();
echo "Articles:\n";
echo "  Total: {$totalArticles}\n";
echo "  Last 24 hours: {$recentArticles}\n";

if ($recentArticles > 0) {
    $latest = App\Models\Article::latest()->first();
    echo "  Latest: {$latest->title} (created: {$latest->created_at})\n";
}
echo "\n";

// Check Crawl Jobs
$pendingJobs = App\Models\CrawlJob::where('status', 'pending')->count();
$runningJobs = App\Models\CrawlJob::where('status', 'running')->count();
$completedJobs = App\Models\CrawlJob::where('status', 'completed')->count();
$failedJobs = App\Models\CrawlJob::where('status', 'failed')->count();

echo "Crawl Jobs:\n";
echo "  Pending: {$pendingJobs}\n";
echo "  Running: {$runningJobs}\n";
echo "  Completed: {$completedJobs}\n";
echo "  Failed: {$failedJobs}\n";

if ($completedJobs > 0) {
    $latestCompleted = App\Models\CrawlJob::where('status', 'completed')->latest('completed_at')->first();
    echo "  Latest completed: {$latestCompleted->url} (at: {$latestCompleted->completed_at})\n";
}
echo "\n";

// Check Sources
$sources = App\Models\Source::all();
echo "Sources ({$sources->count()}):\n";
foreach ($sources as $source) {
    $articleCount = App\Models\Article::where('source_id', $source->id)->count();
    $lastCrawl = $source->last_crawl_at ? $source->last_crawl_at->diffForHumans() : 'Never';
    echo "  [{$source->id}] {$source->name} ({$source->domain}) - {$articleCount} articles - Last crawl: {$lastCrawl}\n";
}
echo "\n";

// Check if queue is configured
$queueConnection = config('queue.default');
echo "Queue Configuration:\n";
echo "  Default connection: {$queueConnection}\n";

if ($queueConnection === 'redis') {
    try {
        $redis = Illuminate\Support\Facades\Redis::connection();
        $redis->ping();
        echo "  Redis: ✓ Connected\n";
        
        // Check queue sizes
        $crawlQueueSize = $redis->llen('queues:crawling');
        $defaultQueueSize = $redis->llen('queues:default');
        echo "  Crawling queue size: {$crawlQueueSize}\n";
        echo "  Default queue size: {$defaultQueueSize}\n";
    } catch (Exception $e) {
        echo "  Redis: ✗ Error - {$e->getMessage()}\n";
    }
}
echo "\n";

// Check recent verification requests
$verificationRequests = App\Models\VerificationRequest::count();
$recentVerifications = App\Models\VerificationRequest::where('created_at', '>=', now()->subHours(24))->count();
echo "Verification Requests:\n";
echo "  Total: {$verificationRequests}\n";
echo "  Last 24 hours: {$recentVerifications}\n";
