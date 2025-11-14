<?php

namespace Tests\Feature\Services;

use Tests\TestCase;
use App\Services\ExternalApiService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;

class ExternalApiIntegrationTest extends TestCase
{
    protected ExternalApiService $service;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Enable all features for testing
        config([
            'external_apis.features.wayback_machine' => true,
            'external_apis.features.fact_checking' => true,
            'external_apis.features.news_apis' => true,
            'external_apis.features.url_validation' => true,
        ]);
        
        $this->service = app(ExternalApiService::class);
    }

    public function test_comprehensive_verification_integration()
    {
        // Mock external API calls
        Http::fake([
            // Wayback Machine API
            'web.archive.org/*' => Http::response([
                'archived_snapshots' => [
                    'closest' => [
                        'available' => true,
                        'url' => 'http://web.archive.org/web/20230615120000/https://example.com',
                        'timestamp' => '20230615120000',
                        'status' => '200'
                    ]
                ]
            ]),
            
            // Google Fact Check API
            'factchecktools.googleapis.com/*' => Http::response([
                'claims' => [
                    [
                        'text' => 'Test claim',
                        'claimReview' => [[
                            'publisher' => ['name' => 'FactChecker'],
                            'reviewRating' => ['ratingValue' => 4, 'bestRating' => 5],
                            'textualRating' => 'Mostly True'
                        ]]
                    ]
                ]
            ]),
            
            // NewsAPI
            'newsapi.org/*' => Http::response([
                'status' => 'ok',
                'totalResults' => 3,
                'articles' => [
                    [
                        'title' => 'Similar News Article 1',
                        'source' => ['name' => 'Reuters'],
                        'publishedAt' => '2023-06-15T12:00:00Z',
                        'url' => 'https://reuters.com/article1'
                    ],
                    [
                        'title' => 'Similar News Article 2', 
                        'source' => ['name' => 'AP News'],
                        'publishedAt' => '2023-06-15T11:30:00Z',
                        'url' => 'https://apnews.com/article2'
                    ]
                ]
            ]),
            
            // Guardian API
            'content.guardianapis.com/*' => Http::response([
                'response' => [
                    'status' => 'ok',
                    'total' => 2,
                    'results' => [
                        [
                            'webTitle' => 'Related Guardian Article',
                            'webPublicationDate' => '2023-06-15T10:00:00Z',
                            'webUrl' => 'https://theguardian.com/article1'
                        ]
                    ]
                ]
            ]),
            
            // URL accessibility checks
            'https://example.com' => Http::response(null, 200, [
                'Content-Type' => 'text/html',
                'Server' => 'nginx'
            ]),
            
            'https://httpbin.org/status/200' => Http::response(null, 200),
            
            // FactCheck.org scraping
            'factcheck.org/*' => Http::response('
                <html>
                    <body>
                        <div class="rating">
                            <span class="rating-label">TRUE</span>
                        </div>
                    </body>
                </html>
            ', 200, ['Content-Type' => 'text/html']),
        ]);

        $testData = [
            'url' => 'https://example.com/news-article',
            'title' => 'Important Breaking News Story',
            'content' => 'This is a test news article content that needs verification.',
        ];

        $result = $this->service->performComprehensiveVerification($testData);

        // Assert basic structure
        $this->assertArrayHasKey('external_checks', $result);
        $this->assertArrayHasKey('overall_assessment', $result);
        $this->assertArrayHasKey('confidence_score', $result);

        // Assert URL validation was performed
        $this->assertArrayHasKey('url_validation', $result['external_checks']);
        $urlCheck = $result['external_checks']['url_validation'];
        $this->assertArrayHasKey('valid', $urlCheck);
        $this->assertArrayHasKey('safe', $urlCheck);
        $this->assertArrayHasKey('reputation_score', $urlCheck);

        // Assert Wayback Machine integration
        $this->assertArrayHasKey('wayback_machine', $result['external_checks']);
        $waybackCheck = $result['external_checks']['wayback_machine'];
        $this->assertTrue($waybackCheck['enabled']);

        // Assert fact-checking integration
        $this->assertArrayHasKey('fact_checking', $result['external_checks']);
        $factCheck = $result['external_checks']['fact_checking'];
        $this->assertTrue($factCheck['enabled']);

        // Assert news cross-reference integration
        $this->assertArrayHasKey('news_cross_reference', $result['external_checks']);
        $newsCheck = $result['external_checks']['news_cross_reference'];
        $this->assertTrue($newsCheck['enabled']);

        // Assert confidence score is calculated
        $this->assertIsFloat($result['confidence_score']);
        $this->assertGreaterThanOrEqual(0, $result['confidence_score']);
        $this->assertLessThanOrEqual(1, $result['confidence_score']);
    }

    public function test_quick_verification_with_trusted_source()
    {
        Http::fake([
            'https://reuters.com/*' => Http::response(null, 200),
            'web.archive.org/*' => Http::response([
                'archived_snapshots' => [
                    'closest' => ['available' => true]
                ]
            ]),
        ]);

        $result = $this->service->performQuickVerification(
            'https://reuters.com/business/news-story',
            'Reuters Business News'
        );

        $this->assertTrue($result['safe']);
        $this->assertGreaterThan(0.7, $result['trust_score']);
        $this->assertArrayHasKey('quick_checks', $result);
        
        // Should recognize Reuters as trusted
        $sourceCheck = $result['quick_checks']['source_trust'];
        $this->assertTrue($sourceCheck['trusted']);
        $this->assertEquals('trusted_news', $sourceCheck['category']);
    }

    public function test_quick_verification_with_suspicious_url()
    {
        Http::fake([
            'https://bit.ly/*' => Http::response(null, 301, [
                'Location' => 'https://suspicious-site.tk/malware'
            ]),
        ]);

        $result = $this->service->performQuickVerification('https://bit.ly/suspicious123');

        $this->assertFalse($result['safe']);
        $this->assertLessThan(0.5, $result['trust_score']);
        $this->assertNotEmpty($result['warnings']);
        
        // Should detect URL shortener and suspicious patterns
        $suspiciousCheck = $result['quick_checks']['suspicious_patterns'];
        $this->assertTrue($suspiciousCheck['suspicious']);
        $this->assertGreaterThan(20, $suspiciousCheck['risk_level']);
    }

    public function test_timestamp_verification_with_wayback_machine()
    {
        Http::fake([
            'web.archive.org/*' => Http::response([
                'archived_snapshots' => [
                    'closest' => [
                        'available' => true,
                        'url' => 'http://web.archive.org/web/20230615120000/https://example.com',
                        'timestamp' => '20230615120000',
                        'status' => '200'
                    ]
                ]
            ]),
        ]);

        $result = $this->service->verifyTimestamp(
            'https://example.com/article',
            '2023-06-15'
        );

        $this->assertArrayHasKey('accurate', $result);
        $this->assertArrayHasKey('confidence', $result);
        $this->assertArrayHasKey('verification', $result);
    }

    public function test_enhanced_metadata_for_trusted_news_source()
    {
        Http::fake([
            'https://bbc.com/*' => Http::response(null, 200),
            'web.archive.org/*' => Http::response([
                'archived_snapshots' => [
                    'closest' => [
                        'available' => true,
                        'timestamp' => '20200101120000'
                    ]
                ]
            ]),
            'newsapi.org/*' => Http::response([
                'status' => 'ok',
                'articles' => [
                    ['title' => 'Similar BBC Article', 'source' => ['name' => 'BBC']]
                ]
            ]),
            'content.guardianapis.com/*' => Http::response([
                'response' => ['status' => 'ok', 'results' => []]
            ]),
            'factcheck.org/*' => Http::response('<html><body></body></html>'),
            'factchecktools.googleapis.com/*' => Http::response(['claims' => []]),
        ]);

        $testData = [
            'url' => 'https://bbc.com/news/world-europe-12345',
            'title' => 'BBC News Report',
            'content' => 'BBC news content for verification',
        ];

        $result = $this->service->getEnhancedMetadata($testData);

        $this->assertArrayHasKey('enhanced_data', $result);
        $this->assertArrayHasKey('credibility_indicators', $result);

        // Should recognize BBC as trusted source
        $domainAnalysis = $result['enhanced_data']['domain_analysis'];
        $this->assertTrue($domainAnalysis['trusted_source']);
        $this->assertEquals('trusted_news', $domainAnalysis['source_category']);

        // Should have positive credibility indicators
        $indicators = $result['credibility_indicators'];
        $this->assertNotEmpty($indicators['positive']);
    }

    public function test_handles_api_failures_gracefully()
    {
        // Simulate API failures
        Http::fake([
            'web.archive.org/*' => function () {
                throw new \Exception('Wayback API timeout');
            },
            'factchecktools.googleapis.com/*' => Http::response(null, 429), // Rate limited
            'newsapi.org/*' => Http::response(['error' => 'API key invalid'], 401),
            'content.guardianapis.com/*' => function () {
                throw new \Exception('Network error');
            },
            'https://example.com' => Http::response(null, 200),
        ]);

        $testData = [
            'url' => 'https://example.com/article',
            'title' => 'Test Article',
        ];

        $result = $this->service->performComprehensiveVerification($testData);

        // Should still return a result structure even with failures
        $this->assertArrayHasKey('external_checks', $result);
        $this->assertArrayHasKey('confidence_score', $result);
        
        // URL validation should still work
        $this->assertArrayHasKey('url_validation', $result['external_checks']);
        
        // Other services should have error information
        $this->assertArrayHasKey('wayback_machine', $result['external_checks']);
        $this->assertArrayHasKey('fact_checking', $result['external_checks']);
        $this->assertArrayHasKey('news_cross_reference', $result['external_checks']);
    }

    public function test_respects_feature_toggles()
    {
        // Disable specific features
        Config::set('external_apis.features.wayback_machine', false);
        Config::set('external_apis.features.fact_checking', false);

        Http::fake([
            'https://example.com' => Http::response(null, 200),
            'newsapi.org/*' => Http::response(['status' => 'ok', 'articles' => []]),
            'content.guardianapis.com/*' => Http::response(['response' => ['status' => 'ok', 'results' => []]]),
        ]);

        $testData = [
            'url' => 'https://example.com/article',
            'title' => 'Test Article',
        ];

        $result = $this->service->performComprehensiveVerification($testData);

        // Should respect feature flags
        $waybackCheck = $result['external_checks']['wayback_machine'];
        $this->assertFalse($waybackCheck['enabled']);

        $factCheck = $result['external_checks']['fact_checking'];
        $this->assertFalse($factCheck['enabled']);

        // News API should still work (not disabled)
        $newsCheck = $result['external_checks']['news_cross_reference'];
        $this->assertArrayHasKey('enabled', $newsCheck);
        $this->assertTrue($newsCheck['enabled']);
    }

    public function test_health_check_integration()
    {
        Http::fake([
            'https://httpbin.org/status/200' => Http::response(null, 200),
            'web.archive.org/*' => Http::response(['archived_snapshots' => []]),
            'factchecktools.googleapis.com/*' => Http::response(['claims' => []]),
            'newsapi.org/*' => Http::response(['status' => 'ok', 'articles' => []]),
            'content.guardianapis.com/*' => Http::response(['response' => ['status' => 'ok']]),
            'factcheck.org/*' => Http::response('<html><body></body></html>'),
            'https://example.com' => Http::response(null, 200),
        ]);

        $health = $this->service->healthCheck();

        $this->assertArrayHasKey('overall_status', $health);
        $this->assertArrayHasKey('services', $health);
        $this->assertArrayHasKey('timestamp', $health);

        // Should check all services
        $services = $health['services'];
        $this->assertArrayHasKey('wayback_machine', $services);
        $this->assertArrayHasKey('fact_check_api', $services);
        $this->assertArrayHasKey('news_api', $services);
        $this->assertArrayHasKey('url_validation', $services);
    }

    public function test_calculates_realistic_confidence_scores()
    {
        // Test with high-quality sources
        Http::fake([
            'https://reuters.com/*' => Http::response(null, 200),
            'web.archive.org/*' => Http::response([
                'archived_snapshots' => ['closest' => ['available' => true]]
            ]),
            'factchecktools.googleapis.com/*' => Http::response([
                'claims' => [[
                    'claimReview' => [[
                        'reviewRating' => ['ratingValue' => 5, 'bestRating' => 5],
                        'textualRating' => 'True'
                    ]]
                ]]
            ]),
            'newsapi.org/*' => Http::response([
                'status' => 'ok',
                'totalResults' => 10,
                'articles' => array_fill(0, 10, [
                    'title' => 'Similar trusted article',
                    'source' => ['name' => 'Reuters'],
                    'publishedAt' => '2023-06-15T12:00:00Z'
                ])
            ]),
            'content.guardianapis.com/*' => Http::response([
                'response' => ['status' => 'ok', 'results' => []]
            ]),
            'factcheck.org/*' => Http::response('<div class="rating-label">TRUE</div>'),
        ]);

        $highQualityData = [
            'url' => 'https://reuters.com/trusted-news',
            'title' => 'Reuters Breaking News',
            'content' => 'Verified news content'
        ];

        $result = $this->service->performComprehensiveVerification($highQualityData);
        $this->assertGreaterThan(0.7, $result['confidence_score']);

        // Test with low-quality/suspicious sources
        Http::fake([
            'https://suspicious.tk/*' => Http::response(null, 200),
            'web.archive.org/*' => Http::response(['archived_snapshots' => []]),
            'factchecktools.googleapis.com/*' => Http::response([
                'claims' => [[
                    'claimReview' => [[
                        'reviewRating' => ['ratingValue' => 1, 'bestRating' => 5],
                        'textualRating' => 'False'
                    ]]
                ]]
            ]),
            'newsapi.org/*' => Http::response(['status' => 'ok', 'articles' => []]),
            'content.guardianapis.com/*' => Http::response([
                'response' => ['status' => 'ok', 'results' => []]
            ]),
            'factcheck.org/*' => Http::response('<div class="rating-label">FALSE</div>'),
        ]);

        $lowQualityData = [
            'url' => 'https://suspicious.tk/fake-news',
            'title' => 'Unverified Claim',
            'content' => 'Suspicious content'
        ];

        $result2 = $this->service->performComprehensiveVerification($lowQualityData);
        $this->assertLessThan(0.4, $result2['confidence_score']);
    }
}