<?php
require 'bootstrap/app.php';

use App\Models\Source;

$sources = Source::orderBy('created_at', 'desc')->get(['id', 'name', 'domain', 'credibility_score', 'created_at']);

echo "\n📊 ALL SOURCES\n";
echo str_repeat("═", 80) . "\n";
printf("%-3s | %-30s | %-25s | %8s | %s\n", "ID", "Name", "Domain", "Score", "Created");
echo str_repeat("─", 80) . "\n";

foreach ($sources as $s) {
    printf("%-3d | %-30s | %-25s | %7d%% | %s\n", 
        $s->id,
        substr($s->name, 0, 28),
        substr($s->domain, 0, 24),
        intval($s->credibility_score ?? 0),
        $s->created_at->format('Y-m-d H:i')
    );
}

echo str_repeat("═", 80) . "\n";
echo "Total: " . $sources->count() . " sources\n";
$avg = $sources->avg('credibility_score');
$min = $sources->min('credibility_score');
$max = $sources->max('credibility_score');
printf("Statistics: Avg=%.0f%%, Min=%.0f%%, Max=%.0f%%\n", $avg, $min, $max);
echo "\n";
