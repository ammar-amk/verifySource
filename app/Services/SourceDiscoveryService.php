<?php

namespace App\Services;

use App\Models\Source;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

/**
 * Service for autonomously discovering new news sources
 * Validates domains, scores credibility, and auto-creates sources
 */
class SourceDiscoveryService
{
    private CredibilityService $credibilityService;
    private UrlDiscoveryService $urlDiscoveryService;
    private DomainTrustService $domainTrustService;

    private array $discoveredSources = [];
    private array $validatedSources = [];

    public function __construct(
        CredibilityService $credibilityService,
        UrlDiscoveryService $urlDiscoveryService,
        DomainTrustService $domainTrustService
    ) {
        $this->credibilityService = $credibilityService;
        $this->urlDiscoveryService = $urlDiscoveryService;
        $this->domainTrustService = $domainTrustService;
    }

    /**
     * Run complete source discovery pipeline
     * 1. Discover new sources
     * 2. Validate domains
     * 3. Score credibility
     * 4. Auto-create and activate
     */
    public function discoverAndOnboardSources(array $options = []): array
    {
        Log::info('Starting autonomous source discovery');

        try {
            // Step 1: Discover sources from multiple feeds
            $this->discoverFromNewsApis($options['limit'] ?? 50);

            // Step 2: Validate discovered domains
            $this->validateDiscoveredDomains();

            // Step 3: Score and create sources
            $createdSources = $this->createValidatedSources();

            Log::info('Source discovery completed', [
                'discovered' => count($this->discoveredSources),
                'validated' => count($this->validatedSources),
                'created' => count($createdSources),
            ]);

            return [
                'success' => true,
                'discovered' => $this->discoveredSources,
                'validated' => $this->validatedSources,
                'created' => $createdSources,
                'timestamp' => now(),
            ];
        } catch (Exception $e) {
            Log::error('Source discovery failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'timestamp' => now(),
            ];
        }
    }

    /**
     * Discover sources from multiple news APIs and aggregators
     */
    private function discoverFromNewsApis(int $limit = 50): void
    {
        // NewsAPI.org - Get top sources
        $this->discoverFromNewsApi($limit);

        // Google News sources via web scraping simulation
        $this->discoverFromGoogleNews($limit);

        // Common news domains (hardcoded high-quality sources)
        $this->discoverCommonNewsDomains();

        Log::info('Source discovery from APIs completed', [
            'total_discovered' => count($this->discoveredSources),
        ]);
    }

    /**
     * Discover sources from NewsAPI
     */
    private function discoverFromNewsApi(int $limit): void
    {
        try {
            $apiKey = config('external_apis.news_apis.newsapi.api_key');
            if (!$apiKey) {
                Log::warning('NewsAPI key not configured');
                return;
            }

            $response = Http::timeout(15)->get('https://newsapi.org/v2/sources', [
                'apiKey' => $apiKey,
                'language' => 'en',
            ]);

            if ($response->successful() && isset($response['sources'])) {
                foreach ($response['sources'] as $source) {
                    if (count($this->discoveredSources) >= $limit) {
                        break;
                    }

                    $this->addDiscoveredSource([
                        'name' => $source['name'] ?? '',
                        'domain' => $this->extractDomain($source['url'] ?? ''),
                        'url' => $source['url'] ?? '',
                        'description' => $source['description'] ?? '',
                        'category' => $source['category'] ?? 'general',
                        'language' => $source['language'] ?? 'en',
                        'country' => $source['country'] ?? 'US',
                        'source' => 'newsapi',
                    ]);
                }
            }
        } catch (Exception $e) {
            Log::warning('NewsAPI discovery failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Discover from Google News (simulated via common news sources)
     */
    private function discoverFromGoogleNews(int $limit): void
    {
        $commonSources = [
            [
                'name' => 'CNN',
                'domain' => 'cnn.com',
                'url' => 'https://www.cnn.com',
                'category' => 'news',
                'country' => 'US',
            ],
            [
                'name' => 'BBC News',
                'domain' => 'bbc.com',
                'url' => 'https://www.bbc.com/news',
                'category' => 'news',
                'country' => 'GB',
            ],
            [
                'name' => 'NPR',
                'domain' => 'npr.org',
                'url' => 'https://www.npr.org',
                'category' => 'news',
                'country' => 'US',
            ],
            [
                'name' => 'The Washington Post',
                'domain' => 'washingtonpost.com',
                'url' => 'https://www.washingtonpost.com',
                'category' => 'news',
                'country' => 'US',
            ],
            [
                'name' => 'The Wall Street Journal',
                'domain' => 'wsj.com',
                'url' => 'https://www.wsj.com',
                'category' => 'business',
                'country' => 'US',
            ],
            [
                'name' => 'The Economist',
                'domain' => 'economist.com',
                'url' => 'https://www.economist.com',
                'category' => 'business',
                'country' => 'GB',
            ],
            [
                'name' => 'Bloomberg',
                'domain' => 'bloomberg.com',
                'url' => 'https://www.bloomberg.com',
                'category' => 'business',
                'country' => 'US',
            ],
            [
                'name' => 'The Telegraph',
                'domain' => 'telegraph.co.uk',
                'url' => 'https://www.telegraph.co.uk',
                'category' => 'news',
                'country' => 'GB',
            ],
            [
                'name' => 'The Independent',
                'domain' => 'independent.co.uk',
                'url' => 'https://www.independent.co.uk',
                'category' => 'news',
                'country' => 'GB',
            ],
            [
                'name' => 'The Times',
                'domain' => 'thetimes.co.uk',
                'url' => 'https://www.thetimes.co.uk',
                'category' => 'news',
                'country' => 'GB',
            ],
        ];

        foreach ($commonSources as $source) {
            if (count($this->discoveredSources) >= $limit) {
                break;
            }

            $this->addDiscoveredSource(array_merge($source, ['source' => 'google_news']));
        }
    }

    /**
     * Add a discovered source (deduped)
     */
    private function addDiscoveredSource(array $sourceData): void
    {
        $domain = $sourceData['domain'] ?? '';
        if (empty($domain)) {
            return;
        }

        // Skip if already discovered or exists in DB
        if (isset($this->discoveredSources[$domain])) {
            return;
        }

        if (Source::where('domain', $domain)->exists()) {
            return;
        }

        $this->discoveredSources[$domain] = $sourceData;
    }

    /**
     * Discover common news domains
     */
    private function discoverCommonNewsDomains(): void
    {
        // This is a fallback for when APIs are unavailable
        // Already populated in discoverFromGoogleNews
    }

    /**
     * Validate discovered domains
     */
    private function validateDiscoveredDomains(): void
    {
        Log::info('Validating discovered domains', [
            'count' => count($this->discoveredSources),
        ]);

        foreach ($this->discoveredSources as $domain => $sourceData) {
            try {
                if ($this->validateDomain($domain, $sourceData)) {
                    $this->validatedSources[$domain] = $sourceData;
                }
            } catch (Exception $e) {
                Log::warning("Domain validation failed for {$domain}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Domain validation completed', [
            'validated' => count($this->validatedSources),
        ]);
    }

    /**
     * Validate a single domain
     */
    private function validateDomain(string $domain, array $sourceData): bool
    {
        // Check if domain is reachable
        try {
            $response = Http::timeout(10)->head('https://' . $domain);
            if (!$response->successful() && !in_array($response->status(), [301, 302, 403])) {
                Log::warning("Domain not reachable: {$domain}");
                return false;
            }
        } catch (Exception $e) {
            Log::warning("Domain check failed for {$domain}: {$e->getMessage()}");
            return false;
        }

        // Check domain legitimacy
        if (!$this->isLegitimateNewsDomain($domain)) {
            Log::warning("Domain not recognized as news source: {$domain}");
            return false;
        }

        return true;
    }

    /**
     * Check if domain is a legitimate news source
     */
    private function isLegitimateNewsDomain(string $domain): bool
    {
        // Blacklist known non-news domains
        $blacklist = [
            'example.com',
            'test.com',
            'localhost',
            'staging',
            'facebook.com',
            'twitter.com',
            'instagram.com',
            'reddit.com',
        ];

        foreach ($blacklist as $blocked) {
            if (str_contains($domain, $blocked)) {
                return false;
            }
        }

        // Must have news-like characteristics
        $hasNewsIndicators = (
            str_contains($domain, 'news') ||
            str_contains($domain, 'press') ||
            str_contains($domain, 'times') ||
            str_contains($domain, 'tribune') ||
            str_contains($domain, 'chronicle') ||
            str_contains($domain, 'gazette') ||
            str_contains($domain, 'daily') ||
            str_contains($domain, 'post') ||
            str_contains($domain, 'wire') ||
            in_array($domain, $this->getKnownNewsDomains())
        );

        return $hasNewsIndicators;
    }

    /**
     * Get list of known legitimate news domains
     */
    private function getKnownNewsDomains(): array
    {
        return [
            'cnn.com',
            'bbc.com',
            'npr.org',
            'reuters.com',
            'apnews.com',
            'theguardian.com',
            'nytimes.com',
            'wsj.com',
            'bloomberg.com',
            'economist.com',
            'washingtonpost.com',
            'foxnews.com',
            'msnbc.com',
            'abcnews.go.com',
            'cbsnews.com',
            'bbc.co.uk',
            'telegraph.co.uk',
            'thesundotcom.uk',
            'independent.co.uk',
            'thetimes.co.uk',
            'metro.co.uk',
            'dw.com',
            'euronews.com',
            'france24.com',
            'aljazeera.com',
            'politico.com',
            'vox.com',
            'wired.com',
            'theatlantic.com',
            'newyorker.com',
        ];
    }

    /**
     * Create validated sources in database with credibility scoring
     */
    private function createValidatedSources(): array
    {
        $createdSources = [];

        foreach ($this->validatedSources as $domain => $sourceData) {
            try {
                // Pre-score domain before creating source
                $initialScore = $this->getInitialDomainScore($domain);

                // Create source record
                $source = Source::create([
                    'name' => $sourceData['name'] ?? $domain,
                    'domain' => $domain,
                    'url' => $sourceData['url'] ?? "https://{$domain}",
                    'description' => $sourceData['description'] ?? "Automatically discovered news source",
                    'category' => $sourceData['category'] ?? 'news',
                    'language' => $sourceData['language'] ?? 'en',
                    'country' => $sourceData['country'] ?? 'US',
                    'is_active' => true,
                    'is_verified' => false,
                    'credibility_score' => $initialScore,
                    'metadata' => [
                        'auto_discovered' => true,
                        'discovered_at' => now()->toISOString(),
                        'discovery_source' => $sourceData['source'] ?? 'unknown',
                        'initial_credibility_score' => $initialScore,
                    ],
                ]);

                // Skip async credibility scoring for new sources (no articles yet)
                // Will be automatically recalculated once articles are crawled
                $finalScore = $initialScore;
                
                Log::info("Using initial domain score for new source (will recalculate after crawling)", [
                    'source_id' => $source->id,
                    'domain' => $domain,
                    'initial_score' => $initialScore,
                ]);

                Log::info("Created new source", [
                    'source_id' => $source->id,
                    'domain' => $domain,
                    'initial_score' => $initialScore,
                    'final_score' => $finalScore,
                ]);

                // Trigger initial URL discovery
                $this->queueInitialCrawl($source);

                $createdSources[] = [
                    'source_id' => $source->id,
                    'domain' => $domain,
                    'name' => $source->name,
                    'credibility_score' => $finalScore,
                ];
            } catch (Exception $e) {
                Log::error("Failed to create source for {$domain}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $createdSources;
    }

    /**
     * Get initial credibility score for known news domains
     */
    private function getInitialDomainScore(string $domain): float
    {
        // Highly trusted established news sources
        $highTrust = [
            'cnn.com' => 95,
            'bbc.com' => 95,
            'bbc.co.uk' => 95,
            'npr.org' => 95,
            'reuters.com' => 95,
            'apnews.com' => 95,
            'theguardian.com' => 95,
            'nytimes.com' => 95,
            'wsj.com' => 95,
            'bloomberg.com' => 95,
            'economist.com' => 95,
            'washingtonpost.com' => 95,
            'foxnews.com' => 85,
            'msnbc.com' => 85,
            'abcnews.go.com' => 85,
            'cbsnews.com' => 85,
            'telegraph.co.uk' => 85,
            'independent.co.uk' => 85,
            'thetimes.co.uk' => 85,
            'metro.co.uk' => 80,
            'dw.com' => 90,
            'euronews.com' => 90,
            'france24.com' => 90,
            'aljazeera.com' => 85,
            'politico.com' => 85,
            'vox.com' => 80,
            'wired.com' => 80,
            'theatlantic.com' => 85,
            'newyorker.com' => 85,
        ];

        return $highTrust[$domain] ?? 70; // Default to 70 for validated but unverified news sources
    }

    /**
     * Queue initial crawl for newly discovered source
     */
    private function queueInitialCrawl(Source $source): void
    {
        try {
            // Discover initial URLs from source
            $discoveredUrls = $this->urlDiscoveryService->discoverUrls(
                $source->url,
                $source->id
            );

            if (!empty($discoveredUrls)) {
                // Create crawl jobs
                $created = $this->urlDiscoveryService->createCrawlJobs(
                    array_slice($discoveredUrls, 0, 100), // Limit to first 100
                    $source->id,
                    10 // High priority for new sources
                );

                Log::info("Queued initial crawl for new source", [
                    'source_id' => $source->id,
                    'jobs_created' => $created,
                ]);
            }
        } catch (Exception $e) {
            Log::warning("Failed to queue initial crawl for source {$source->id}", [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Extract domain from URL
     */
    private function extractDomain(string $url): string
    {
        if (empty($url)) {
            return '';
        }

        $domain = parse_url($url, PHP_URL_HOST) ?? '';
        // Remove www. prefix
        $domain = preg_replace('/^www\./', '', $domain);

        return $domain;
    }

    /**
     * Get sources discovered in last discovery run
     */
    public function getDiscoveredSources(): array
    {
        return $this->discoveredSources;
    }

    /**
     * Get validated sources from last discovery run
     */
    public function getValidatedSources(): array
    {
        return $this->validatedSources;
    }
}
