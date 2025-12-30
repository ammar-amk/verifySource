<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🧪 Testing URL Verification with Fixed Python Crawler...\n\n";

$scraperService = app(\App\Services\WebScraperService::class);

$testUrl = 'https://www.bbc.com/news';

echo "Testing URL: {$testUrl}\n";
echo str_repeat('-', 60) . "\n";

try {
    $result = $scraperService->scrapeUrl($testUrl);
    
    echo "✅ SUCCESS! Content extracted\n";
    echo "  • Title: " . (isset($result['title']) ? substr($result['title'], 0, 50) : 'N/A') . "\n";
    echo "  • Content Length: " . (isset($result['content']) ? strlen($result['content']) : 0) . " characters\n";
    echo "  • Author: " . ($result['author'] ?? 'N/A') . "\n";
    echo "  • Published: " . ($result['published_at'] ?? 'N/A') . "\n";
    
} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
