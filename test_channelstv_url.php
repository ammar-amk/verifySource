<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🧪 Testing URL Verification with Channels TV Article\n\n";

$testUrl = 'https://www.channelstv.com/2025/12/27/fg-graduates-over-7000-forest-guards-set-for-immediate-deployment/';

echo "Testing URL: {$testUrl}\n";
echo str_repeat('-', 80) . "\n\n";

$scraperService = app(\App\Services\WebScraperService::class);

try {
    echo "⏳ Starting extraction...\n";
    $result = $scraperService->scrapeUrl($testUrl);
    
    if ($result) {
        echo "✅ SUCCESS! Content extracted\n\n";
        echo "📄 Title:\n   " . ($result['title'] ?? 'N/A') . "\n\n";
        echo "✍️  Author:\n   " . ($result['author'] ?? 'N/A') . "\n\n";
        echo "📅 Published:\n   " . ($result['published_at'] ?? 'N/A') . "\n\n";
        echo "📝 Content Length:\n   " . (isset($result['content']) ? strlen($result['content']) : 0) . " characters\n\n";
        
        if (!empty($result['content'])) {
            echo "📖 Content Preview (first 300 chars):\n";
            echo "   " . substr($result['content'], 0, 300) . "...\n\n";
        }
        
        echo "🔍 Extraction Method:\n   " . ($result['extraction_method'] ?? 'Unknown') . "\n\n";
        
        if (!empty($result['excerpt'])) {
            echo "📋 Excerpt:\n   " . $result['excerpt'] . "\n\n";
        }
        
        if (!empty($result['language'])) {
            echo "🌐 Language:\n   " . $result['language'] . "\n\n";
        }
        
        echo str_repeat('=', 80) . "\n";
        echo "✅ Verification test completed successfully!\n";
        
    } else {
        echo "⚠️  WARNING: Scraping returned empty result\n";
    }
    
} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n\n";
    echo "Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
}
