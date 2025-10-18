<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;
use App\Models\CrawlJob;
use App\Models\Source;

class WebScraperService
{
    protected int $timeout;
    protected int $retries;
    protected array $userAgents;
    protected array $headers;

    public function __construct()
    {
        $this->timeout = config('verifysource.scraper.timeout', 30);
        $this->retries = config('verifysource.scraper.retries', 3);
        $this->userAgents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:89.0) Gecko/20100101 Firefox/89.0',
        ];
        $this->headers = [
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.5',
            'Accept-Encoding' => 'gzip, deflate',
            'DNT' => '1',
            'Connection' => 'keep-alive',
            'Upgrade-Insecure-Requests' => '1',
        ];
    }

    public function scrapeUrl(string $url): array
    {
        try {
            Log::info("Starting web scrape", ['url' => $url]);

            $response = $this->makeRequest($url);
            
            if (!$response['success']) {
                throw new \Exception($response['error']);
            }

            $crawler = new Crawler($response['content']);
            $scrapedData = $this->extractContent($crawler, $url);
            
            Log::info("Web scrape completed", [
                'url' => $url,
                'title_length' => strlen($scrapedData['title'] ?? ''),
                'content_length' => strlen($scrapedData['content'] ?? ''),
                'links_count' => count($scrapedData['links'] ?? [])
            ]);

            return [
                'success' => true,
                'data' => $scrapedData,
                'response_info' => $response['info']
            ];

        } catch (\Exception $e) {
            Log::error("Web scrape failed", [
                'url' => $url,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'data' => null
            ];
        }
    }

    protected function makeRequest(string $url): array
    {
        $attempts = 0;
        $lastError = null;

        while ($attempts < $this->retries) {
            $attempts++;

            try {
                $userAgent = $this->userAgents[array_rand($this->userAgents)];
                $headers = array_merge($this->headers, ['User-Agent' => $userAgent]);

                $response = Http::withHeaders($headers)
                    ->timeout($this->timeout)
                    ->get($url);

                if ($response->successful()) {
                    return [
                        'success' => true,
                        'content' => $response->body(),
                        'info' => [
                            'status_code' => $response->status(),
                            'headers' => $response->headers(),
                            'final_url' => $url, // Laravel HTTP doesn't expose redirect chain easily
                            'content_type' => $response->header('Content-Type'),
                            'content_length' => strlen($response->body()),
                        ]
                    ];
                } else {
                    $lastError = "HTTP {$response->status()}: " . $response->body();
                }

            } catch (\Exception $e) {
                $lastError = $e->getMessage();
            }

            if ($attempts < $this->retries) {
                sleep(pow(2, $attempts)); // Exponential backoff
            }
        }

        return [
            'success' => false,
            'error' => $lastError ?? 'Unknown error occurred',
            'content' => null,
            'info' => null
        ];
    }

    protected function extractContent(Crawler $crawler, string $url): array
    {
        $data = [
            'url' => $url,
            'title' => $this->extractTitle($crawler),
            'content' => $this->extractMainContent($crawler),
            'excerpt' => null,
            'author' => $this->extractAuthor($crawler),
            'published_at' => $this->extractPublishedDate($crawler),
            'meta_description' => $this->extractMetaDescription($crawler),
            'meta_keywords' => $this->extractMetaKeywords($crawler),
            'language' => $this->extractLanguage($crawler),
            'canonical_url' => $this->extractCanonicalUrl($crawler),
            'images' => $this->extractImages($crawler, $url),
            'links' => $this->extractLinks($crawler, $url),
            'feed_links' => $this->extractFeedLinks($crawler, $url),
            'social_media' => $this->extractSocialMediaLinks($crawler),
            'schema_org' => $this->extractSchemaOrg($crawler),
        ];

        // Generate excerpt from content if not found
        if (empty($data['excerpt']) && !empty($data['content'])) {
            $data['excerpt'] = $this->generateExcerpt($data['content']);
        }

        return $data;
    }

    protected function extractTitle(Crawler $crawler): ?string
    {
        // Try multiple selectors in order of preference
        $selectors = [
            'meta[property="og:title"]',
            'meta[name="twitter:title"]',
            'title',
            'h1',
            '.title',
            '.headline',
            '.post-title'
        ];

        foreach ($selectors as $selector) {
            try {
                $element = $crawler->filter($selector)->first();
                if ($element->count() > 0) {
                    $title = $selector === 'title' ? 
                        $element->text() : 
                        ($element->attr('content') ?: $element->text());
                    
                    $title = trim($title);
                    if (!empty($title)) {
                        return $title;
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return null;
    }

    protected function extractMainContent(Crawler $crawler): ?string
    {
        // Try multiple content selectors
        $selectors = [
            'article',
            '.post-content',
            '.entry-content',
            '.content',
            '.main-content',
            '.article-body',
            '.post-body',
            '[role="main"]',
            'main',
            '.story-body',
            '.article-text'
        ];

        foreach ($selectors as $selector) {
            try {
                $element = $crawler->filter($selector)->first();
                if ($element->count() > 0) {
                    // Remove unwanted elements
                    $element->filter('script, style, nav, aside, .advertisement, .ads, .social-share')->each(function ($node) {
                        $node->getNode(0)->parentNode->removeChild($node->getNode(0));
                    });
                    
                    $content = $element->text();
                    $content = trim($content);
                    
                    if (!empty($content) && strlen($content) > 100) {
                        return $content;
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        // Fallback to body text if no specific content area found
        try {
            $bodyElement = $crawler->filter('body')->first();
            if ($bodyElement->count() > 0) {
                $bodyElement->filter('script, style, nav, header, footer, aside')->each(function ($node) {
                    $node->getNode(0)->parentNode->removeChild($node->getNode(0));
                });
                
                $content = $bodyElement->text();
                return trim($content);
            }
        } catch (\Exception $e) {
            // Continue to return null
        }

        return null;
    }

    protected function extractAuthor(Crawler $crawler): ?string
    {
        $selectors = [
            'meta[name="author"]',
            'meta[property="article:author"]',
            '.author',
            '.byline',
            '.by-author',
            '[rel="author"]'
        ];

        foreach ($selectors as $selector) {
            try {
                $element = $crawler->filter($selector)->first();
                if ($element->count() > 0) {
                    $author = $element->attr('content') ?: $element->text();
                    $author = trim($author);
                    if (!empty($author)) {
                        return $author;
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return null;
    }

    protected function extractPublishedDate(Crawler $crawler): ?string
    {
        $selectors = [
            'meta[property="article:published_time"]',
            'meta[name="publishdate"]',
            'meta[name="date"]',
            'time[datetime]',
            '.published',
            '.date',
            '.publish-date'
        ];

        foreach ($selectors as $selector) {
            try {
                $element = $crawler->filter($selector)->first();
                if ($element->count() > 0) {
                    $date = $element->attr('content') ?: 
                           $element->attr('datetime') ?: 
                           $element->text();
                    
                    $date = trim($date);
                    if (!empty($date)) {
                        return $date;
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return null;
    }

    protected function extractMetaDescription(Crawler $crawler): ?string
    {
        try {
            $element = $crawler->filter('meta[name="description"], meta[property="og:description"]')->first();
            if ($element->count() > 0) {
                return trim($element->attr('content'));
            }
        } catch (\Exception $e) {
            // Continue to return null
        }

        return null;
    }

    protected function extractMetaKeywords(Crawler $crawler): ?string
    {
        try {
            $element = $crawler->filter('meta[name="keywords"]')->first();
            if ($element->count() > 0) {
                return trim($element->attr('content'));
            }
        } catch (\Exception $e) {
            // Continue to return null
        }

        return null;
    }

    protected function extractLanguage(Crawler $crawler): ?string
    {
        try {
            $htmlElement = $crawler->filter('html')->first();
            if ($htmlElement->count() > 0) {
                $lang = $htmlElement->attr('lang');
                if ($lang) {
                    return substr($lang, 0, 2); // Get just the language code
                }
            }
        } catch (\Exception $e) {
            // Continue to return null
        }

        return null;
    }

    protected function extractCanonicalUrl(Crawler $crawler): ?string
    {
        try {
            $element = $crawler->filter('link[rel="canonical"]')->first();
            if ($element->count() > 0) {
                return trim($element->attr('href'));
            }
        } catch (\Exception $e) {
            // Continue to return null
        }

        return null;
    }

    protected function extractImages(Crawler $crawler, string $baseUrl): array
    {
        $images = [];

        try {
            $crawler->filter('img')->each(function (Crawler $node) use (&$images, $baseUrl) {
                $src = $node->attr('src');
                $alt = $node->attr('alt');
                
                if ($src) {
                    $images[] = [
                        'src' => $this->resolveUrl($src, $baseUrl),
                        'alt' => $alt,
                    ];
                }
            });
        } catch (\Exception $e) {
            Log::warning("Error extracting images", ['error' => $e->getMessage()]);
        }

        return array_slice($images, 0, 10); // Limit to first 10 images
    }

    protected function extractLinks(Crawler $crawler, string $baseUrl): array
    {
        $links = [];

        try {
            $crawler->filter('a[href]')->each(function (Crawler $node) use (&$links, $baseUrl) {
                $href = $node->attr('href');
                $text = trim($node->text());
                
                if ($href && !str_starts_with($href, '#') && !str_starts_with($href, 'javascript:')) {
                    $resolvedUrl = $this->resolveUrl($href, $baseUrl);
                    
                    // Only include external links or important internal links
                    if ($this->isExternalUrl($resolvedUrl, $baseUrl) || 
                        $this->isImportantInternalLink($href)) {
                        $links[] = [
                            'url' => $resolvedUrl,
                            'text' => $text,
                            'is_external' => $this->isExternalUrl($resolvedUrl, $baseUrl)
                        ];
                    }
                }
            });
        } catch (\Exception $e) {
            Log::warning("Error extracting links", ['error' => $e->getMessage()]);
        }

        return array_slice($links, 0, 50); // Limit to first 50 links
    }

    protected function extractFeedLinks(Crawler $crawler, string $baseUrl): array
    {
        $feeds = [];

        try {
            $crawler->filter('link[type="application/rss+xml"], link[type="application/atom+xml"]')->each(function (Crawler $node) use (&$feeds, $baseUrl) {
                $href = $node->attr('href');
                $title = $node->attr('title');
                $type = $node->attr('type');
                
                if ($href) {
                    $feeds[] = [
                        'url' => $this->resolveUrl($href, $baseUrl),
                        'title' => $title,
                        'type' => $type
                    ];
                }
            });
        } catch (\Exception $e) {
            Log::warning("Error extracting feed links", ['error' => $e->getMessage()]);
        }

        return $feeds;
    }

    protected function extractSocialMediaLinks(Crawler $crawler): array
    {
        $social = [];
        $platforms = [
            'facebook.com' => 'facebook',
            'twitter.com' => 'twitter',
            'x.com' => 'twitter',
            'instagram.com' => 'instagram',
            'linkedin.com' => 'linkedin',
            'youtube.com' => 'youtube',
            'tiktok.com' => 'tiktok'
        ];

        try {
            $crawler->filter('a[href]')->each(function (Crawler $node) use (&$social, $platforms) {
                $href = $node->attr('href');
                
                foreach ($platforms as $domain => $platform) {
                    if (str_contains($href, $domain)) {
                        $social[$platform] = $href;
                        break;
                    }
                }
            });
        } catch (\Exception $e) {
            Log::warning("Error extracting social media links", ['error' => $e->getMessage()]);
        }

        return $social;
    }

    protected function extractSchemaOrg(Crawler $crawler): array
    {
        $schemas = [];

        try {
            $crawler->filter('script[type="application/ld+json"]')->each(function (Crawler $node) use (&$schemas) {
                $content = $node->text();
                $decoded = json_decode($content, true);
                
                if ($decoded) {
                    $schemas[] = $decoded;
                }
            });
        } catch (\Exception $e) {
            Log::warning("Error extracting Schema.org data", ['error' => $e->getMessage()]);
        }

        return $schemas;
    }

    protected function generateExcerpt(string $content, int $length = 160): string
    {
        $content = strip_tags($content);
        $content = preg_replace('/\s+/', ' ', $content);
        $content = trim($content);

        if (strlen($content) <= $length) {
            return $content;
        }

        $excerpt = substr($content, 0, $length);
        $lastSpace = strrpos($excerpt, ' ');

        if ($lastSpace !== false) {
            $excerpt = substr($excerpt, 0, $lastSpace);
        }

        return $excerpt . '...';
    }

    protected function resolveUrl(string $url, string $baseUrl): string
    {
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        $baseParsed = parse_url($baseUrl);
        $baseScheme = $baseParsed['scheme'] ?? 'http';
        $baseHost = $baseParsed['host'] ?? '';

        if (str_starts_with($url, '//')) {
            return $baseScheme . ':' . $url;
        }

        if (str_starts_with($url, '/')) {
            return $baseScheme . '://' . $baseHost . $url;
        }

        $basePath = dirname($baseParsed['path'] ?? '/');
        return $baseScheme . '://' . $baseHost . $basePath . '/' . $url;
    }

    protected function isExternalUrl(string $url, string $baseUrl): bool
    {
        $urlHost = parse_url($url, PHP_URL_HOST);
        $baseHost = parse_url($baseUrl, PHP_URL_HOST);

        return $urlHost !== $baseHost;
    }

    protected function isImportantInternalLink(string $href): bool
    {
        $importantPaths = [
            'sitemap',
            'feed',
            'rss',
            'atom',
            'archive',
            'category',
            'tag',
            'author'
        ];

        foreach ($importantPaths as $path) {
            if (str_contains(strtolower($href), $path)) {
                return true;
            }
        }

        return false;
    }

    public function scrapeSitemap(string $sitemapUrl): array
    {
        try {
            $response = $this->makeRequest($sitemapUrl);
            
            if (!$response['success']) {
                return ['success' => false, 'error' => $response['error']];
            }

            $crawler = new Crawler($response['content']);
            $urls = [];

            // Handle XML sitemaps
            if (str_contains($response['info']['content_type'] ?? '', 'xml')) {
                $crawler->filter('url > loc, sitemap > loc')->each(function (Crawler $node) use (&$urls) {
                    $urls[] = trim($node->text());
                });
            } else {
                // Handle HTML sitemaps
                $crawler->filter('a[href]')->each(function (Crawler $node) use (&$urls, $sitemapUrl) {
                    $href = $node->attr('href');
                    if ($href) {
                        $urls[] = $this->resolveUrl($href, $sitemapUrl);
                    }
                });
            }

            return [
                'success' => true,
                'urls' => array_unique($urls),
                'count' => count($urls)
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}