<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$source = App\Models\Source::first();

echo "ID: {$source->id}\n";
echo "Name: {$source->name}\n";
echo "URL: {$source->url}\n";
echo "Domain: {$source->domain}\n";
