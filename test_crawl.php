<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Creating Test Crawl Job ===\n\n";

// Find NYTimes source
$nytimes = App\Models\Source::where('domain', 'nytimes.com')->first();

if (!$nytimes) {
    echo "NYTimes source not found!\n";
    exit(1);
}

// Create a job with a working sitemap URL
$job = App\Models\CrawlJob::create([
    'source_id' => $nytimes->id,
    'url' => 'https://www.nytimes.com/sitemaps/new/news.xml.gz',
    'status' => 'pending',
    'priority' => 10,
    'metadata' => [
        'test' => true,
        'note' => 'Testing with working sitemap URL'
    ]
]);

echo "✓ Created crawl job [{$job->id}]\n";
echo "  URL: {$job->url}\n";
echo "  Source: {$nytimes->name}\n";
echo "  Priority: {$job->priority}\n\n";

echo "Now processing the job...\n\n";

// Process the job
$orchestrationService = app(App\Services\CrawlerOrchestrationService::class);
$result = $orchestrationService->processCrawlJob($job);

echo "✓ Job processed!\n";
echo "  Success: " . ($result['success'] ? 'Yes' : 'No') . "\n";
echo "  Articles created: {$result['articles_created']}\n";
echo "  URLs discovered: {$result['urls_discovered']}\n";

if ($result['error']) {
    echo "  Error: {$result['error']}\n";
}

// Check if new jobs were created
$newJobs = App\Models\CrawlJob::where('source_id', $nytimes->id)
    ->where('status', 'pending')
    ->where('created_at', '>', now()->subMinute())
    ->count();
    
echo "\n✓ New crawl jobs created: {$newJobs}\n";
