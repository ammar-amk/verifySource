<?php

namespace App\Services;

use App\Models\CrawlJob;
use App\Models\Source;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;
use Exception;

/**
 * Service for discovering article URLs from sitemaps, RSS feeds, and homepages
 */
class UrlDiscoveryService
{
    /**
     * Discover article URLs from a given URL (sitemap, feed, or homepage)
     * 
     * @param string $url The URL to discover articles from
     * @param int $sourceId The source ID
     * @return array Array of discovered URLs
     */
    public function discoverUrls(string $url, int $sourceId): array
    {
        Log::info("Starting URL discovery", ['url' => $url, 'source_id' => $sourceId]);
        
        $discoveredUrls = [];
        
        try {
            // Determine URL type and use appropriate discovery method
            if ($this->isSitemap($url)) {
                $discoveredUrls = $this->discoverFromSitemap($url);
            } elseif ($this->isRssFeed($url)) {
                $discoveredUrls = $this->discoverFromRssFeed($url);
            } elseif ($this->isRobotsTxt($url)) {
                $discoveredUrls = $this->discoverFromRobotsTxt($url, $sourceId);
            } else {
                // For homepage or other URLs, try to discover links
                $discoveredUrls = $this->discoverFromHomepage($url);
            }
            
            // Filter and validate URLs
            $discoveredUrls = $this->filterArticleUrls($discoveredUrls, $url);
            
            Log::info("URL discovery completed", [
                'url' => $url,
                'discovered_count' => count($discoveredUrls)
            ]);
            
            return $discoveredUrls;
            
        } catch (Exception $e) {
            Log::error("Error during URL discovery", [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Create crawl jobs for discovered URLs
     * 
     * @param array $urls Array of URLs to create jobs for
     * @param int $sourceId The source ID
     * @param int $priority Priority for the jobs
     * @return int Number of jobs created
     */
    public function createCrawlJobs(array $urls, int $sourceId, int $priority = 5): int
    {
        $created = 0;
        
        foreach ($urls as $url) {
            // Skip if job already exists
            $exists = CrawlJob::where('url', $url)
                ->where('source_id', $sourceId)
                ->whereIn('status', ['pending', 'running'])
                ->exists();
            
            if (!$exists) {
                CrawlJob::create([
                    'source_id' => $sourceId,
                    'url' => $url,
                    'status' => 'pending',
                    'priority' => $priority,
                    'metadata' => [
                        'discovered_at' => now()->toISOString(),
                        'is_article' => true
                    ]
                ]);
                $created++;
            }
        }
        
        Log::info("Created crawl jobs for discovered URLs", [
            'source_id' => $sourceId,
            'created_count' => $created,
            'total_urls' => count($urls)
        ]);
        
        return $created;
    }
    
    /**
     * Check if URL is a sitemap
     */
    protected function isSitemap(string $url): bool
    {
        return str_contains($url, 'sitemap') || str_ends_with($url, '.xml');
    }
    
    /**
     * Check if URL is an RSS feed
     */
    protected function isRssFeed(string $url): bool
    {
        return str_contains($url, '/feed') || 
               str_contains($url, '/rss') || 
               str_contains($url, '.rss');
    }
    
    /**
     * Check if URL is robots.txt
     */
    protected function isRobotsTxt(string $url): bool
    {
        return str_ends_with($url, 'robots.txt');
    }
    
    /**
     * Discover URLs from sitemap
     */
    protected function discoverFromSitemap(string $url): array
    {
        Log::info("Discovering URLs from sitemap", ['url' => $url]);
        
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.9',
                    'Accept-Encoding' => 'gzip, deflate, br',
                ])
                ->get($url);
            
            if (!$response->successful()) {
                Log::warning("Failed to fetch sitemap", ['url' => $url, 'status' => $response->status()]);
                return [];
            }
            
            $content = $response->body();
            $urls = [];
            
            Log::debug("Sitemap content received", [
                'url' => $url,
                'content_length' => strlen($content),
                'first_100_chars' => substr($content, 0, 100)
            ]);
            
            // Try parsing as XML
            try {
                // Suppress XML errors to handle them gracefully
                libxml_use_internal_errors(true);
                $xml = new SimpleXMLElement($content);
                libxml_clear_errors();
                
                Log::debug("Successfully parsed as XML", ['url' => $url]);
                
                // Handle sitemap index (links to other sitemaps)
                if (isset($xml->sitemap)) {
                    Log::info("Found sitemap index", ['url' => $url, 'sitemap_count' => count($xml->sitemap)]);
                    foreach ($xml->sitemap as $sitemap) {
                        if (isset($sitemap->loc)) {
                            $sitemapUrl = (string) $sitemap->loc;
                            // Recursively parse nested sitemaps (limit depth to avoid infinite loops)
                            if (substr_count($url, '/sitemaps/') < 3) {
                                $nestedUrls = $this->discoverFromSitemap($sitemapUrl);
                                $urls = array_merge($urls, $nestedUrls);
                            }
                        }
                    }
                }
                
                // Handle URL entries
                if (isset($xml->url)) {
                    Log::info("Found URL entries in sitemap", ['url' => $url, 'url_count' => count($xml->url)]);
                    foreach ($xml->url as $urlEntry) {
                        if (isset($urlEntry->loc)) {
                            $urls[] = (string) $urlEntry->loc;
                        }
                    }
                }
                
            } catch (Exception $e) {
                Log::info("Not an XML sitemap, trying plain text", ['url' => $url, 'error' => $e->getMessage()]);
                
                // Try parsing as plain text sitemap
                $lines = explode("\n", $content);
                Log::debug("Plain text sitemap", ['url' => $url, 'line_count' => count($lines)]);
                
                $validUrlCount = 0;
                foreach ($lines as $line) {
                    $line = trim($line);
                    
                    // Skip empty lines and comments
                    if (empty($line) || str_starts_with($line, '#')) {
                        continue;
                    }
                    
                    // Validate URL
                    if (filter_var($line, FILTER_VALIDATE_URL)) {
                        $urls[] = $line;
                        $validUrlCount++;
                    } else {
                        // Log first few invalid lines for debugging
                        if ($validUrlCount < 5) {
                            Log::debug("Invalid URL in sitemap", ['url' => $url, 'line' => $line]);
                        }
                    }
                }
                
                Log::info("Plain text sitemap parsed", [
                    'url' => $url,
                    'total_lines' => count($lines),
                    'valid_urls' => $validUrlCount
                ]);
            }
            
            Log::info("Sitemap discovery complete", [
                'url' => $url,
                'urls_discovered' => count($urls)
            ]);
            
            return $urls;
            
        } catch (Exception $e) {
            Log::error("Error discovering from sitemap", ['url' => $url, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return [];
        }
    }
    
    /**
     * Discover URLs from RSS feed
     */
    protected function discoverFromRssFeed(string $url): array
    {
        Log::info("Discovering URLs from RSS feed", ['url' => $url]);
        
        try {
            $response = Http::timeout(30)->get($url);
            
            if (!$response->successful()) {
                return [];
            }
            
            $content = $response->body();
            $urls = [];
            
            try {
                $xml = new SimpleXMLElement($content);
                
                // RSS 2.0 format
                if (isset($xml->channel->item)) {
                    foreach ($xml->channel->item as $item) {
                        if (isset($item->link)) {
                            $urls[] = (string) $item->link;
                        }
                    }
                }
                
                // Atom format
                if (isset($xml->entry)) {
                    foreach ($xml->entry as $entry) {
                        if (isset($entry->link)) {
                            $link = $entry->link;
                            if (isset($link['href'])) {
                                $urls[] = (string) $link['href'];
                            }
                        }
                    }
                }
                
            } catch (Exception $e) {
                Log::warning("Failed to parse RSS feed", ['url' => $url, 'error' => $e->getMessage()]);
            }
            
            return $urls;
            
        } catch (Exception $e) {
            Log::error("Error discovering from RSS feed", ['url' => $url, 'error' => $e->getMessage()]);
            return [];
        }
    }
    
    /**
     * Discover sitemap URLs from robots.txt
     */
    protected function discoverFromRobotsTxt(string $url, int $sourceId): array
    {
        Log::info("Discovering sitemaps from robots.txt", ['url' => $url]);
        
        try {
            $response = Http::timeout(30)->get($url);
            
            if (!$response->successful()) {
                return [];
            }
            
            $content = $response->body();
            $sitemapUrls = [];
            
            // Parse robots.txt for sitemap entries
            $lines = explode("\n", $content);
            foreach ($lines as $line) {
                $line = trim($line);
                if (stripos($line, 'Sitemap:') === 0) {
                    $sitemapUrl = trim(substr($line, 8));
                    if (filter_var($sitemapUrl, FILTER_VALIDATE_URL)) {
                        $sitemapUrls[] = $sitemapUrl;
                    }
                }
            }
            
            // Create jobs for discovered sitemaps
            foreach ($sitemapUrls as $sitemapUrl) {
                CrawlJob::firstOrCreate(
                    [
                        'url' => $sitemapUrl,
                        'source_id' => $sourceId,
                        'status' => 'pending'
                    ],
                    [
                        'priority' => 10, // Higher priority for sitemaps
                        'metadata' => [
                            'discovered_from' => 'robots.txt',
                            'is_sitemap' => true
                        ]
                    ]
                );
            }
            
            Log::info("Found sitemaps in robots.txt", [
                'url' => $url,
                'sitemap_count' => count($sitemapUrls)
            ]);
            
            return $sitemapUrls;
            
        } catch (Exception $e) {
            Log::error("Error parsing robots.txt", ['url' => $url, 'error' => $e->getMessage()]);
            return [];
        }
    }
    
    /**
     * Discover article URLs from homepage by scraping links
     */
    protected function discoverFromHomepage(string $url): array
    {
        Log::info("Discovering URLs from homepage", ['url' => $url]);
        
        try {
            $response = Http::timeout(30)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; VerifySourceBot/1.0)'])
                ->get($url);
            
            if (!$response->successful()) {
                return [];
            }
            
            $html = $response->body();
            $urls = [];
            
            // Extract URLs from href attributes
            preg_match_all('/<a\s+(?:[^>]*?\s+)?href="([^"]*)"/', $html, $matches);
            
            if (!empty($matches[1])) {
                foreach ($matches[1] as $link) {
                    // Convert relative URLs to absolute
                    $absoluteUrl = $this->makeAbsoluteUrl($link, $url);
                    if ($absoluteUrl) {
                        $urls[] = $absoluteUrl;
                    }
                }
            }
            
            return $urls;
            
        } catch (Exception $e) {
            Log::error("Error discovering from homepage", ['url' => $url, 'error' => $e->getMessage()]);
            return [];
        }
    }
    
    /**
     * Filter URLs to keep only likely article URLs
     */
    protected function filterArticleUrls(array $urls, string $sourceUrl): array
    {
        $parsed = parse_url($sourceUrl);
        $baseDomain = $parsed['host'] ?? '';
        
        $filtered = [];
        
        foreach ($urls as $url) {
            // Must be valid URL
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                continue;
            }
            
            // Must be from same domain
            $urlParsed = parse_url($url);
            if (($urlParsed['host'] ?? '') !== $baseDomain) {
                continue;
            }
            
            // Skip common non-article patterns
            $skipPatterns = [
                '/tag/', '/category/', '/author/', '/search/', '/login/', '/register/',
                '/contact/', '/about/', '/privacy/', '/terms/', '/subscribe/',
                '.jpg', '.png', '.gif', '.pdf', '.zip', '/static/', '/assets/',
                '/wp-admin/', '/wp-content/', '/feed/', '/rss/', '/sitemap'
            ];
            
            $skip = false;
            foreach ($skipPatterns as $pattern) {
                if (str_contains(strtolower($url), $pattern)) {
                    $skip = true;
                    break;
                }
            }
            
            if ($skip) {
                continue;
            }
            
            // Must have reasonable path (likely an article)
            $path = $urlParsed['path'] ?? '';
            if (empty($path) || $path === '/') {
                continue;
            }
            
            $filtered[] = $url;
        }
        
        // Remove duplicates
        return array_unique($filtered);
    }
    
    /**
     * Convert relative URL to absolute
     */
    protected function makeAbsoluteUrl(string $link, string $baseUrl): ?string
    {
        // Already absolute
        if (filter_var($link, FILTER_VALIDATE_URL)) {
            return $link;
        }
        
        // Skip anchors and javascript
        if (str_starts_with($link, '#') || str_starts_with($link, 'javascript:')) {
            return null;
        }
        
        $parsed = parse_url($baseUrl);
        $scheme = $parsed['scheme'] ?? 'https';
        $host = $parsed['host'] ?? '';
        
        // Protocol-relative URL
        if (str_starts_with($link, '//')) {
            return $scheme . ':' . $link;
        }
        
        // Absolute path
        if (str_starts_with($link, '/')) {
            return $scheme . '://' . $host . $link;
        }
        
        // Relative path
        $basePath = dirname($parsed['path'] ?? '/');
        return $scheme . '://' . $host . rtrim($basePath, '/') . '/' . $link;
    }
}
