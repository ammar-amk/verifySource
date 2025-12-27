<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class DebugSitemapCommand extends Command
{
    protected $signature = 'crawl:debug-sitemap {url}';

    protected $description = 'Debug sitemap parsing';

    public function handle()
    {
        $url = $this->argument('url');
        
        $this->info("Fetching sitemap: {$url}");
        
        $response = Http::timeout(30)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
            ])
            ->get($url);
        
        if (!$response->successful()) {
            $this->error("Failed to fetch sitemap: " . $response->status());
            return self::FAILURE;
        }
        
        $content = $response->body();
        
        $this->info("Content length: " . strlen($content));
        $this->info("First 500 chars:");
        $this->line(substr($content, 0, 500));
        $this->line('');
        
        // Try XML parsing
        $this->info("Trying XML parsing...");
        try {
            $xml = new \SimpleXMLElement($content);
            $this->info("✓ Valid XML");
            
            if (isset($xml->sitemap)) {
                $this->info("Found sitemap index with " . count($xml->sitemap) . " sitemaps");
                foreach ($xml->sitemap as $sitemap) {
                    if (isset($sitemap->loc)) {
                        $this->line("  - " . (string) $sitemap->loc);
                    }
                }
            }
            
            if (isset($xml->url)) {
                $this->info("Found " . count($xml->url) . " URL entries");
                $count = 0;
                foreach ($xml->url as $urlEntry) {
                    if (isset($urlEntry->loc) && $count < 5) {
                        $this->line("  - " . (string) $urlEntry->loc);
                        $count++;
                    }
                }
            }
            
        } catch (\Exception $e) {
            $this->warn("✗ Not valid XML: " . $e->getMessage());
            
            // Try plain text parsing
            $this->info("Trying plain text parsing...");
            $lines = explode("\n", $content);
            $this->info("Found " . count($lines) . " lines");
            
            $validUrls = 0;
            $sampleUrls = [];
            foreach ($lines as $line) {
                $line = trim($line);
                if (!empty($line) && filter_var($line, FILTER_VALIDATE_URL)) {
                    $validUrls++;
                    if (count($sampleUrls) < 5) {
                        $sampleUrls[] = $line;
                    }
                }
            }
            
            $this->info("Found {$validUrls} valid URLs");
            if (!empty($sampleUrls)) {
                $this->line("Sample URLs:");
                foreach ($sampleUrls as $url) {
                    $this->line("  - {$url}");
                }
            }
        }
        
        return self::SUCCESS;
    }
}
