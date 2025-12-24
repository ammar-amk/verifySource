<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Recent Crawl Jobs Details ===\n\n";

$recentJobs = App\Models\CrawlJob::orderBy('completed_at', 'desc')
    ->whereNotNull('completed_at')
    ->limit(10)
    ->get();

foreach ($recentJobs as $job) {
    echo "Job [{$job->id}] - Status: {$job->status}\n";
    echo "  URL: {$job->url}\n";
    echo "  Completed: {$job->completed_at->diffForHumans()}\n";
    
    if ($job->metadata) {
        echo "  Metadata:\n";
        foreach ($job->metadata as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value);
            }
            echo "    - {$key}: {$value}\n";
        }
    }
    
    if ($job->error_message) {
        echo "  Error: {$job->error_message}\n";
    }
    
    echo "\n";
}

// Check if any URLs were discovered
$pendingWithPriority = App\Models\CrawlJob::where('status', 'pending')
    ->where('priority', '>', 0)
    ->count();
    
echo "Pending jobs with high priority: {$pendingWithPriority}\n";

// Check articles created recently
$recentArticles = App\Models\Article::latest()->limit(5)->get();
echo "\nRecent Articles ({$recentArticles->count()}):\n";
foreach ($recentArticles as $article) {
    echo "  [{$article->id}] {$article->title}\n";
    echo "      Source: {$article->source->name}\n";
    echo "      Created: {$article->created_at->diffForHumans()}\n";
}
