<?php

namespace App\Console\Commands;

use App\Services\UrlDiscoveryService;
use Illuminate\Console\Command;

class TestUrlDiscoveryCommand extends Command
{
    protected $signature = 'test:url-discovery {url}';
    protected $description = 'Test URL discovery service with a URL';

    public function handle(UrlDiscoveryService $urlDiscoveryService)
    {
        $url = $this->argument('url');
        
        $this->info("Testing URL discovery for: {$url}");
        $this->line('');
        
        $urls = $urlDiscoveryService->discoverUrls($url, 1); // Use source ID 1
        
        $this->info("✓ Discovery complete!");
        $this->line("Found " . count($urls) . " URLs");
        
        if (!empty($urls)) {
            $this->line('');
            $this->info('Sample URLs (first 10):');
            foreach (array_slice($urls, 0, 10) as $discoveredUrl) {
                $this->line("  - {$discoveredUrl}");
            }
            
            if (count($urls) > 10) {
                $this->line("  ... and " . (count($urls) - 10) . " more");
            }
        }
        
        return self::SUCCESS;
    }
}
