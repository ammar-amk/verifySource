<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🔧 Testing Python Crawler Configuration\n\n";

// Test 1: Check config
$pythonPath = config('verifysource.python.executable');
echo "1. Python Path from Config: {$pythonPath}\n";

// Test 2: Check if Python exists
if (file_exists($pythonPath)) {
    echo "   ✅ Python executable exists\n";
} else {
    echo "   ❌ Python executable NOT found\n";
}

// Test 3: Test Python version
echo "\n2. Testing Python Version:\n";
$versionCmd = "\"{$pythonPath}\" --version";
$version = shell_exec($versionCmd);
echo "   {$version}";

// Test 4: Test Python imports
echo "\n3. Testing Python Imports:\n";
$importTest = "\"{$pythonPath}\" -c \"import asyncio; from scrapy.crawler import CrawlerProcess; print('✅ All imports successful')\"";
$importResult = shell_exec($importTest . " 2>&1");
echo "   {$importResult}";

// Test 5: Test crawler script
echo "\n4. Testing Crawler Script:\n";
$crawlerPath = base_path('crawlers/crawler.py');
if (file_exists($crawlerPath)) {
    echo "   ✅ Crawler script exists: {$crawlerPath}\n";
    
    $helpCmd = "\"{$pythonPath}\" \"{$crawlerPath}\" --help";
    echo "\n   Running: {$helpCmd}\n";
    $helpResult = shell_exec($helpCmd . " 2>&1");
    echo "   " . substr($helpResult, 0, 200) . "...\n";
} else {
    echo "   ❌ Crawler script NOT found\n";
}

// Test 6: Test through PythonCrawlerService
echo "\n5. Testing PythonCrawlerService:\n";
$service = app(\App\Services\PythonCrawlerService::class);
echo "   Python available: " . ($service->isPythonAvailable() ? "✅ YES" : "❌ NO") . "\n";
