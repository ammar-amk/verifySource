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
    private array $validationFailures = [];

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
                'failures' => $this->validationFailures,
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
        // 1. NewsAPI.org - Get top sources
        $this->discoverFromNewsApi($limit);

        // 2. Scrape Google News to find actual sources
        $this->scrapeGoogleNews($limit);

        // 3. Discover from RSS aggregators
        $this->discoverFromRssAggregators($limit);

        // 4. Search for news sites via Bing/Google
        $this->searchForNewsSites($limit);

        // 5. Fallback to common news domains if nothing found
        if (count($this->discoveredSources) < 10) {
            $this->discoverFromGoogleNews($limit);
        }

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
            // North America - US
            [
                'name' => 'CNN',
                'domain' => 'cnn.com',
                'url' => 'https://www.cnn.com',
                'category' => 'news',
                'country' => 'US',
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
                'name' => 'The New York Times',
                'domain' => 'nytimes.com',
                'url' => 'https://www.nytimes.com',
                'category' => 'news',
                'country' => 'US',
            ],
            [
                'name' => 'USA Today',
                'domain' => 'usatoday.com',
                'url' => 'https://www.usatoday.com',
                'category' => 'news',
                'country' => 'US',
            ],
            [
                'name' => 'ABC News',
                'domain' => 'abcnews.go.com',
                'url' => 'https://abcnews.go.com',
                'category' => 'news',
                'country' => 'US',
            ],
            [
                'name' => 'CBS News',
                'domain' => 'cbsnews.com',
                'url' => 'https://www.cbsnews.com',
                'category' => 'news',
                'country' => 'US',
            ],
            [
                'name' => 'NBC News',
                'domain' => 'nbcnews.com',
                'url' => 'https://www.nbcnews.com',
                'category' => 'news',
                'country' => 'US',
            ],
            [
                'name' => 'Fox News',
                'domain' => 'foxnews.com',
                'url' => 'https://www.foxnews.com',
                'category' => 'news',
                'country' => 'US',
            ],
            [
                'name' => 'Bloomberg',
                'domain' => 'bloomberg.com',
                'url' => 'https://www.bloomberg.com',
                'category' => 'business',
                'country' => 'US',
            ],
            [
                'name' => 'Politico',
                'domain' => 'politico.com',
                'url' => 'https://www.politico.com',
                'category' => 'politics',
                'country' => 'US',
            ],
            [
                'name' => 'The Hill',
                'domain' => 'thehill.com',
                'url' => 'https://thehill.com',
                'category' => 'politics',
                'country' => 'US',
            ],
            [
                'name' => 'TIME',
                'domain' => 'time.com',
                'url' => 'https://time.com',
                'category' => 'news',
                'country' => 'US',
            ],
            [
                'name' => 'Newsweek',
                'domain' => 'newsweek.com',
                'url' => 'https://www.newsweek.com',
                'category' => 'news',
                'country' => 'US',
            ],
            [
                'name' => 'Los Angeles Times',
                'domain' => 'latimes.com',
                'url' => 'https://www.latimes.com',
                'category' => 'news',
                'country' => 'US',
            ],
            [
                'name' => 'Chicago Tribune',
                'domain' => 'chicagotribune.com',
                'url' => 'https://www.chicagotribune.com',
                'category' => 'news',
                'country' => 'US',
            ],
            [
                'name' => 'Boston Globe',
                'domain' => 'bostonglobe.com',
                'url' => 'https://www.bostonglobe.com',
                'category' => 'news',
                'country' => 'US',
            ],
            
            // North America - Canada
            [
                'name' => 'CBC News',
                'domain' => 'cbc.ca',
                'url' => 'https://www.cbc.ca/news',
                'category' => 'news',
                'country' => 'CA',
            ],
            [
                'name' => 'The Globe and Mail',
                'domain' => 'theglobeandmail.com',
                'url' => 'https://www.theglobeandmail.com',
                'category' => 'news',
                'country' => 'CA',
            ],
            [
                'name' => 'Toronto Star',
                'domain' => 'thestar.com',
                'url' => 'https://www.thestar.com',
                'category' => 'news',
                'country' => 'CA',
            ],
            
            // Europe
            [
                'name' => 'BBC News',
                'domain' => 'bbc.com',
                'url' => 'https://www.bbc.com/news',
                'category' => 'news',
                'country' => 'GB',
            ],
            [
                'name' => 'The Economist',
                'domain' => 'economist.com',
                'url' => 'https://www.economist.com',
                'category' => 'business',
                'country' => 'GB',
            ],
            [
                'name' => 'The Guardian',
                'domain' => 'theguardian.com',
                'url' => 'https://www.theguardian.com',
                'category' => 'news',
                'country' => 'GB',
            ],
            [
                'name' => 'Deutsche Welle',
                'domain' => 'dw.com',
                'url' => 'https://www.dw.com',
                'category' => 'news',
                'country' => 'DE',
            ],
            [
                'name' => 'France 24',
                'domain' => 'france24.com',
                'url' => 'https://www.france24.com',
                'category' => 'news',
                'country' => 'FR',
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
                'name' => 'Financial Times',
                'domain' => 'ft.com',
                'url' => 'https://www.ft.com',
                'category' => 'business',
                'country' => 'GB',
            ],
            [
                'name' => 'Le Monde',
                'domain' => 'lemonde.fr',
                'url' => 'https://www.lemonde.fr',
                'category' => 'news',
                'country' => 'FR',
            ],
            [
                'name' => 'El País',
                'domain' => 'elpais.com',
                'url' => 'https://elpais.com',
                'category' => 'news',
                'country' => 'ES',
            ],
            [
                'name' => 'Der Spiegel',
                'domain' => 'spiegel.de',
                'url' => 'https://www.spiegel.de',
                'category' => 'news',
                'country' => 'DE',
            ],
            [
                'name' => 'Euronews',
                'domain' => 'euronews.com',
                'url' => 'https://www.euronews.com',
                'category' => 'news',
                'country' => 'FR',
            ],
            [
                'name' => 'The Local',
                'domain' => 'thelocal.com',
                'url' => 'https://www.thelocal.com',
                'category' => 'news',
                'country' => 'SE',
            ],
            
            // Africa
            [
                'name' => 'News24',
                'domain' => 'news24.com',
                'url' => 'https://www.news24.com',
                'category' => 'news',
                'country' => 'ZA',
            ],
            [
                'name' => 'Daily Maverick',
                'domain' => 'dailymaverick.co.za',
                'url' => 'https://www.dailymaverick.co.za',
                'category' => 'news',
                'country' => 'ZA',
            ],
            [
                'name' => 'The Citizen',
                'domain' => 'citizen.co.za',
                'url' => 'https://www.citizen.co.za',
                'category' => 'news',
                'country' => 'ZA',
            ],
            [
                'name' => 'Daily Nation',
                'domain' => 'nation.africa',
                'url' => 'https://nation.africa',
                'category' => 'news',
                'country' => 'KE',
            ],
            [
                'name' => 'Premium Times',
                'domain' => 'premiumtimesng.com',
                'url' => 'https://www.premiumtimesng.com',
                'category' => 'news',
                'country' => 'NG',
            ],
            [
                'name' => 'The Guardian Nigeria',
                'domain' => 'guardian.ng',
                'url' => 'https://guardian.ng',
                'category' => 'news',
                'country' => 'NG',
            ],
            [
                'name' => 'Al Jazeera',
                'domain' => 'aljazeera.com',
                'url' => 'https://www.aljazeera.com',
                'category' => 'news',
                'country' => 'QA',
            ],
            [
                'name' => 'Ahram Online',
                'domain' => 'english.ahram.org.eg',
                'url' => 'https://english.ahram.org.eg',
                'category' => 'news',
                'country' => 'EG',
            ],
            [
                'name' => 'The East African',
                'domain' => 'theeastafrican.co.ke',
                'url' => 'https://www.theeastafrican.co.ke',
                'category' => 'news',
                'country' => 'KE',
            ],
            [
                'name' => 'Punch Newspapers',
                'domain' => 'punchng.com',
                'url' => 'https://punchng.com',
                'category' => 'news',
                'country' => 'NG',
            ],
            [
                'name' => 'Vanguard Nigeria',
                'domain' => 'vanguardngr.com',
                'url' => 'https://www.vanguardngr.com',
                'category' => 'news',
                'country' => 'NG',
            ],
            [
                'name' => 'Business Day',
                'domain' => 'businessday.ng',
                'url' => 'https://businessday.ng',
                'category' => 'business',
                'country' => 'NG',
            ],
            [
                'name' => 'IOL News',
                'domain' => 'iol.co.za',
                'url' => 'https://www.iol.co.za',
                'category' => 'news',
                'country' => 'ZA',
            ],
            [
                'name' => 'Mail & Guardian',
                'domain' => 'mg.co.za',
                'url' => 'https://mg.co.za',
                'category' => 'news',
                'country' => 'ZA',
            ],
            
            // Asia
            [
                'name' => 'The Times of India',
                'domain' => 'timesofindia.indiatimes.com',
                'url' => 'https://timesofindia.indiatimes.com',
                'category' => 'news',
                'country' => 'IN',
            ],
            [
                'name' => 'The Hindu',
                'domain' => 'thehindu.com',
                'url' => 'https://www.thehindu.com',
                'category' => 'news',
                'country' => 'IN',
            ],
            [
                'name' => 'South China Morning Post',
                'domain' => 'scmp.com',
                'url' => 'https://www.scmp.com',
                'category' => 'news',
                'country' => 'HK',
            ],
            [
                'name' => 'The Straits Times',
                'domain' => 'straitstimes.com',
                'url' => 'https://www.straitstimes.com',
                'category' => 'news',
                'country' => 'SG',
            ],
            [
                'name' => 'Japan Times',
                'domain' => 'japantimes.co.jp',
                'url' => 'https://www.japantimes.co.jp',
                'category' => 'news',
                'country' => 'JP',
            ],
            [
                'name' => 'The Korea Herald',
                'domain' => 'koreaherald.com',
                'url' => 'https://www.koreaherald.com',
                'category' => 'news',
                'country' => 'KR',
            ],
            [
                'name' => 'The Jakarta Post',
                'domain' => 'thejakartapost.com',
                'url' => 'https://www.thejakartapost.com',
                'category' => 'news',
                'country' => 'ID',
            ],
            [
                'name' => 'Bangkok Post',
                'domain' => 'bangkokpost.com',
                'url' => 'https://www.bangkokpost.com',
                'category' => 'news',
                'country' => 'TH',
            ],
            [
                'name' => 'The Star Malaysia',
                'domain' => 'thestar.com.my',
                'url' => 'https://www.thestar.com.my',
                'category' => 'news',
                'country' => 'MY',
            ],
            [
                'name' => 'Arab News',
                'domain' => 'arabnews.com',
                'url' => 'https://www.arabnews.com',
                'category' => 'news',
                'country' => 'SA',
            ],
            
            // Australia & Oceania
            [
                'name' => 'The Sydney Morning Herald',
                'domain' => 'smh.com.au',
                'url' => 'https://www.smh.com.au',
                'category' => 'news',
                'country' => 'AU',
            ],
            [
                'name' => 'The Australian',
                'domain' => 'theaustralian.com.au',
                'url' => 'https://www.theaustralian.com.au',
                'category' => 'news',
                'country' => 'AU',
            ],
            [
                'name' => 'ABC News Australia',
                'domain' => 'abc.net.au',
                'url' => 'https://www.abc.net.au/news',
                'category' => 'news',
                'country' => 'AU',
            ],
            [
                'name' => 'The Age',
                'domain' => 'theage.com.au',
                'url' => 'https://www.theage.com.au',
                'category' => 'news',
                'country' => 'AU',
            ],
            [
                'name' => 'NZ Herald',
                'domain' => 'nzherald.co.nz',
                'url' => 'https://www.nzherald.co.nz',
                'category' => 'news',
                'country' => 'NZ',
            ],
            
            // Latin America
            [
                'name' => 'Buenos Aires Times',
                'domain' => 'batimes.com.ar',
                'url' => 'https://www.batimes.com.ar',
                'category' => 'news',
                'country' => 'AR',
            ],
            [
                'name' => 'Brazil Reports',
                'domain' => 'brazilian.report',
                'url' => 'https://brazilian.report',
                'category' => 'news',
                'country' => 'BR',
            ],
            [
                'name' => 'Mexico News Daily',
                'domain' => 'mexiconewsdaily.com',
                'url' => 'https://mexiconewsdaily.com',
                'category' => 'news',
                'country' => 'MX',
            ],
            
            // International Wire Services
            [
                'name' => 'Reuters',
                'domain' => 'reuters.com',
                'url' => 'https://www.reuters.com',
                'category' => 'news',
                'country' => 'US',
            ],
            [
                'name' => 'Associated Press',
                'domain' => 'apnews.com',
                'url' => 'https://apnews.com',
                'category' => 'news',
                'country' => 'US',
            ],
            [
                'name' => 'Agence France-Presse',
                'domain' => 'afp.com',
                'url' => 'https://www.afp.com',
                'category' => 'news',
                'country' => 'FR',
            ],
            [
                'name' => 'United Press International',
                'domain' => 'upi.com',
                'url' => 'https://www.upi.com',
                'category' => 'news',
                'country' => 'US',
            ],
            [
                'name' => 'Bloomberg',
                'domain' => 'bloomberg.com',
                'url' => 'https://www.bloomberg.com',
                'category' => 'business',
                'country' => 'US',
            ],
            [
                'name' => 'Reuters',
                'domain' => 'reuters.com',
                'url' => 'https://www.reuters.com',
                'category' => 'news',
                'country' => 'GB',
            ],
            [
                'name' => 'Associated Press',
                'domain' => 'apnews.com',
                'url' => 'https://apnews.com',
                'category' => 'news',
                'country' => 'US',
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
     * Scrape actual Google News to discover sources dynamically
     */
    private function scrapeGoogleNews(int $limit): void
    {
        try {
            Log::info('Scraping Google News for sources');
            
            // Search multiple news topics to find diverse sources
            $topics = ['world', 'technology', 'business', 'science', 'health', 'sports', 'entertainment'];
            
            foreach ($topics as $topic) {
                if (count($this->discoveredSources) >= $limit) {
                    break;
                }
                
                $url = "https://news.google.com/topics/{$topic}?hl=en&gl=US&ceid=US:en";
                
                try {
                    $response = Http::timeout(10)
                        ->withHeaders([
                            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                        ])
                        ->get($url);
                    
                    if ($response->successful()) {
                        $html = $response->body();
                        
                        // Extract source URLs from Google News HTML
                        preg_match_all('/<a[^>]+href="\.\/publications\/([^"]+)"[^>]*>([^<]+)<\/a>/i', $html, $matches);
                        
                        if (!empty($matches[1])) {
                            foreach ($matches[1] as $idx => $sourceId) {
                                $sourceName = $matches[2][$idx] ?? '';
                                
                                // Try to find the actual domain from the page
                                if (preg_match('/data-n-tid="[^"]*-' . preg_quote($sourceId, '/') . '"[^>]+data-n-url="([^"]+)"/i', $html, $urlMatch)) {
                                    $sourceUrl = $urlMatch[1];
                                    $domain = $this->extractDomain($sourceUrl);
                                    
                                    if ($domain && $sourceName) {
                                        $this->addDiscoveredSource([
                                            'name' => trim($sourceName),
                                            'domain' => $domain,
                                            'url' => $sourceUrl,
                                            'category' => $topic,
                                            'country' => 'US',
                                            'source' => 'google_news_scrape',
                                        ]);
                                    }
                                }
                            }
                        }
                        
                        // Also extract direct article links to find source domains
                        preg_match_all('/<a[^>]+href="\.\/articles\/[^"]*"[^>]*data-n-tid="([^"]+)"[^>]*>/i', $html, $articleMatches);
                        preg_match_all('/https?:\/\/([^\/\s"]+)/i', $html, $domainMatches);
                        
                        if (!empty($domainMatches[1])) {
                            $uniqueDomains = array_unique($domainMatches[1]);
                            foreach ($uniqueDomains as $domain) {
                                if (count($this->discoveredSources) >= $limit) {
                                    break 2;
                                }
                                
                                // Filter out non-news domains
                                if ($this->looksLikeNewsDomain($domain)) {
                                    $this->addDiscoveredSource([
                                        'name' => $this->generateNameFromDomain($domain),
                                        'domain' => $domain,
                                        'url' => "https://{$domain}",
                                        'category' => $topic,
                                        'country' => 'US',
                                        'source' => 'google_news_scrape',
                                    ]);
                                }
                            }
                        }
                    }
                    
                    usleep(500000); // 500ms delay between requests
                } catch (Exception $e) {
                    Log::warning("Failed to scrape Google News for {$topic}: {$e->getMessage()}");
                }
            }
            
            Log::info('Google News scraping completed', ['found' => count($this->discoveredSources)]);
        } catch (Exception $e) {
            Log::warning('Google News scraping failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Discover from RSS aggregators
     */
    private function discoverFromRssAggregators(int $limit): void
    {
        try {
            Log::info('Discovering from RSS aggregators');
            
            // Popular RSS aggregators and their feed lists
            $aggregators = [
                'https://feedspot.com/infiniterss.php?_src=feed_list&followfeedid=4936987', // Top news feeds
                'https://www.alltop.com/rss', // Alltop aggregator
            ];
            
            foreach ($aggregators as $aggregatorUrl) {
                if (count($this->discoveredSources) >= $limit) {
                    break;
                }
                
                try {
                    $response = Http::timeout(10)->get($aggregatorUrl);
                    
                    if ($response->successful()) {
                        $content = $response->body();
                        
                        // Extract RSS feed URLs
                        preg_match_all('/<link[^>]*type="application\/rss\+xml"[^>]*href="([^"]+)"/i', $content, $rssMatches);
                        preg_match_all('/https?:\/\/([^\/\s"]+)\/[^\s"]*(?:rss|feed)[^\s"]*/i', $content, $feedMatches);
                        
                        $allFeeds = array_merge($rssMatches[1] ?? [], $feedMatches[0] ?? []);
                        
                        foreach ($allFeeds as $feedUrl) {
                            if (count($this->discoveredSources) >= $limit) {
                                break 2;
                            }
                            
                            $domain = $this->extractDomain($feedUrl);
                            if ($domain && $this->looksLikeNewsDomain($domain)) {
                                $this->addDiscoveredSource([
                                    'name' => $this->generateNameFromDomain($domain),
                                    'domain' => $domain,
                                    'url' => "https://{$domain}",
                                    'category' => 'general',
                                    'country' => 'US',
                                    'source' => 'rss_aggregator',
                                ]);
                            }
                        }
                    }
                    
                    usleep(500000);
                } catch (Exception $e) {
                    Log::warning("Failed to scrape RSS aggregator: {$e->getMessage()}");
                }
            }
            
            Log::info('RSS aggregator discovery completed');
        } catch (Exception $e) {
            Log::warning('RSS aggregator discovery failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Search for news sites using search engines
     */
    private function searchForNewsSites(int $limit): void
    {
        try {
            Log::info('Searching for news sites via search engines');
            
            $queries = [
                'breaking news today',
                'latest world news',
                'technology news blog',
                'business news website',
                'political news source',
                'sports news latest',
                'entertainment news today',
            ];
            
            foreach ($queries as $query) {
                if (count($this->discoveredSources) >= $limit) {
                    break;
                }
                
                // Use DuckDuckGo Instant Answer API (no key required)
                try {
                    $response = Http::timeout(10)->get('https://api.duckduckgo.com/', [
                        'q' => $query,
                        'format' => 'json',
                        'no_html' => 1,
                    ]);
                    
                    if ($response->successful()) {
                        $data = $response->json();
                        
                        // Extract URLs from results
                        $urls = [];
                        if (isset($data['RelatedTopics'])) {
                            foreach ($data['RelatedTopics'] as $topic) {
                                if (isset($topic['FirstURL'])) {
                                    $urls[] = $topic['FirstURL'];
                                }
                                if (isset($topic['Topics'])) {
                                    foreach ($topic['Topics'] as $subtopic) {
                                        if (isset($subtopic['FirstURL'])) {
                                            $urls[] = $subtopic['FirstURL'];
                                        }
                                    }
                                }
                            }
                        }
                        
                        foreach ($urls as $url) {
                            if (count($this->discoveredSources) >= $limit) {
                                break 2;
                            }
                            
                            $domain = $this->extractDomain($url);
                            if ($domain && $this->looksLikeNewsDomain($domain)) {
                                $this->addDiscoveredSource([
                                    'name' => $this->generateNameFromDomain($domain),
                                    'domain' => $domain,
                                    'url' => "https://{$domain}",
                                    'category' => 'general',
                                    'country' => 'US',
                                    'source' => 'search_engine',
                                ]);
                            }
                        }
                    }
                    
                    usleep(1000000); // 1 second delay for search API
                } catch (Exception $e) {
                    Log::warning("Search failed for '{$query}': {$e->getMessage()}");
                }
            }
            
            Log::info('Search engine discovery completed');
        } catch (Exception $e) {
            Log::warning('Search engine discovery failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Check if domain looks like a news/blog site
     */
    private function looksLikeNewsDomain(string $domain): bool
    {
        // Remove www. prefix
        $domain = preg_replace('/^www\./i', '', $domain);
        
        // Blacklist of non-news domains
        $blacklist = [
            'google.com', 'facebook.com', 'twitter.com', 'instagram.com', 'youtube.com',
            'reddit.com', 'linkedin.com', 'pinterest.com', 'tumblr.com', 'wordpress.org',
            'blogspot.com', 'medium.com', 'github.com', 'stackoverflow.com', 'wikipedia.org',
            'amazon.com', 'ebay.com', 'apple.com', 'microsoft.com',
        ];
        
        foreach ($blacklist as $blocked) {
            if (str_contains($domain, $blocked)) {
                return false;
            }
        }
        
        // News/blog domain indicators
        $indicators = [
            'news', 'times', 'post', 'herald', 'tribune', 'gazette', 'chronicle', 'journal',
            'daily', 'press', 'wire', 'report', 'blog', 'today', 'weekly', 'voice',
            'observer', 'guardian', 'telegraph', 'independent', 'nation', 'standard',
            'express', 'mirror', 'sun', 'star', 'dispatch', 'sentinel', 'examiner',
        ];
        
        foreach ($indicators as $indicator) {
            if (str_contains(strtolower($domain), $indicator)) {
                return true;
            }
        }
        
        // Check TLD - news sites often use .com, .net, .org, .co.uk, .ng, .za, etc.
        $validTlds = ['.com', '.net', '.org', '.news', '.co.', '.ng', '.za', '.ke', '.in', '.au', '.uk', '.ca'];
        foreach ($validTlds as $tld) {
            if (str_ends_with($domain, $tld)) {
                // Must have reasonable length (not just example.com)
                if (strlen($domain) > 8) {
                    return true;
                }
            }
        }
        
        return false;
    }

    /**
     * Generate a readable name from domain
     */
    private function generateNameFromDomain(string $domain): string
    {
        // Remove TLD and www
        $name = preg_replace('/^www\./i', '', $domain);
        $name = preg_replace('/\.(com|net|org|co\.uk|co\.za|ng|za|ke|in|au|ca|jp)$/i', '', $name);
        
        // Convert hyphens/underscores to spaces
        $name = str_replace(['-', '_', '.'], ' ', $name);
        
        // Capitalize words
        $name = ucwords($name);
        
        return $name;
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
        
        // Normalize domain
        $domain = strtolower(trim($domain));
        $domain = preg_replace('/^www\./i', '', $domain);

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
                $validationResult = $this->validateDomain($domain, $sourceData);
                if ($validationResult === true) {
                    $this->validatedSources[$domain] = $sourceData;
                } else {
                    // Track failure reason
                    $this->validationFailures[$domain] = [
                        'name' => $sourceData['name'] ?? 'Unknown',
                        'reason' => $validationResult,
                        'url' => $sourceData['url'] ?? 'https://' . $domain,
                    ];
                }
            } catch (Exception $e) {
                $this->validationFailures[$domain] = [
                    'name' => $sourceData['name'] ?? 'Unknown',
                    'reason' => 'Exception: ' . $e->getMessage(),
                    'url' => $sourceData['url'] ?? 'https://' . $domain,
                ];
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
     * Returns true if valid, or string with failure reason if invalid
     */
    private function validateDomain(string $domain, array $sourceData): bool|string
    {
        // Check if domain is reachable
        try {
            $response = Http::timeout(10)
                ->withOptions(['verify' => false]) // Skip SSL verification for discovery
                ->head('https://' . $domain);
                
            if (!$response->successful() && !in_array($response->status(), [301, 302, 403, 405])) {
                return "Not reachable (HTTP {$response->status()})";
            }
        } catch (Exception $e) {
            // Try HTTP if HTTPS fails
            try {
                $response = Http::timeout(5)->head('http://' . $domain);
                if (!$response->successful() && !in_array($response->status(), [301, 302, 403, 405])) {
                    return "Connection failed: " . substr($e->getMessage(), 0, 100);
                }
            } catch (Exception $e2) {
                return "Connection failed: " . substr($e->getMessage(), 0, 100);
            }
        }

        // Check domain legitimacy
        if (!$this->isLegitimateNewsDomain($domain)) {
            return "Not recognized as news domain";
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
            'youtube.com',
            'google.com',
            'wikipedia.org',
        ];

        foreach ($blacklist as $blocked) {
            if (str_contains($domain, $blocked)) {
                return false;
            }
        }

        // Accept known news domains
        if (in_array($domain, $this->getKnownNewsDomains())) {
            return true;
        }

        // Accept domains with news-like characteristics (including blogs)
        $newsKeywords = [
            'news', 'press', 'times', 'tribune', 'chronicle', 'gazette',
            'daily', 'post', 'wire', 'herald', 'journal', 'telegraph',
            'guardian', 'observer', 'examiner', 'reporter', 'bulletin',
            'citizen', 'nation', 'standard', 'monitor', 'today', 'world',
            'weekly', 'magazine', 'media', 'journal', 'afrique', 'mundo',
            'spiegel', 'monde', 'pais', 'local', 'independent', 'corriere',
            'ahram', 'star', 'globe', 'mail', 'financial', 'economist',
            'politico', 'hill', 'atlantic', 'vox', 'slate', 'salon',
            'eastafrican', 'african', 'europe', 'asia', 'time', 'cbc',
            'washington', 'punch', 'vanguard', 'hindu', 'scmp', 'smh',
            'age', 'australian', 'abc', 'brazil', 'afp', 'upi', 'report', 'ft',
            // Blog-related keywords
            'blog', 'blogger', 'blogspot', 'wordpress', 'medium', 'substack',
            'write', 'writer', 'column', 'columnist', 'opinion', 'editorial',
            'commentary', 'analysis', 'insight', 'perspective'
        ];

        foreach ($newsKeywords as $keyword) {
            if (str_contains(strtolower($domain), $keyword)) {
                return true;
            }
        }

        // Accept common news TLDs with reasonable domain names
        $newsTLDs = ['.news', '.press', '.media', '.ng', '.za', '.ke', '.africa'];
        foreach ($newsTLDs as $tld) {
            if (str_ends_with($domain, $tld)) {
                return true;
            }
        }

        return false;
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
