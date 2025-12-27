<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$discoveryService = app(\App\Services\SourceDiscoveryService::class);

echo "🔍 Testing Source Discovery with Detailed Failures...\n\n";

$result = $discoveryService->discoverAndOnboardSources(['limit' => 20]);

echo "📊 Summary:\n";
echo "  • Discovered: " . count($result['discovered']) . " sources\n";
echo "  • Validated: " . count($result['validated']) . " sources\n";
echo "  • Created: " . count($result['created']) . " new sources\n";
echo "  • Failed: " . count($result['failures']) . " sources\n\n";

if (!empty($result['failures'])) {
    echo "❌ Validation Failures:\n";
    echo str_repeat('=', 80) . "\n";
    
    foreach ($result['failures'] as $domain => $failure) {
        printf("%-35s | %-20s | %s\n", 
            $domain, 
            substr($failure['name'], 0, 20),
            $failure['reason']
        );
    }
    
    echo str_repeat('=', 80) . "\n\n";
    
    // Categorize failures
    $reasons = [];
    foreach ($result['failures'] as $failure) {
        $reason = $failure['reason'];
        if (!isset($reasons[$reason])) {
            $reasons[$reason] = 0;
        }
        $reasons[$reason]++;
    }
    
    echo "📈 Failure Breakdown:\n";
    arsort($reasons);
    foreach ($reasons as $reason => $count) {
        echo "  • {$reason}: {$count} sources\n";
    }
}

if (!empty($result['created'])) {
    echo "\n✅ Successfully Created:\n";
    foreach ($result['created'] as $source) {
        echo "  • {$source['name']} ({$source['domain']}) - {$source['credibility_score']}%\n";
    }
}
