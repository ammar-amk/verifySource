<?php

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';

$sources = \App\Models\Source::all();

echo "ID | Name | Credibility Score\n";
echo str_repeat("-", 60) . "\n";

foreach ($sources as $source) {
    printf("%2d | %-30s | %3d%%\n", 
        $source->id, 
        substr($source->name, 0, 28),
        intval($source->credibility_score ?? 0)
    );
}

echo "\nTotal sources: " . $sources->count() . "\n";
echo "Average credibility: " . round($sources->avg('credibility_score'), 1) . "%\n";
