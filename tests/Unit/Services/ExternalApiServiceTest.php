<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\ExternalApiService;
use App\Services\WaybackMachineService;
use App\Services\FactCheckApiService;
use App\Services\NewsApiService;
use App\Services\UrlValidationService;
use Mockery;

class ExternalApiServiceTest extends TestCase
{
    protected ExternalApiService $service;
    protected $waybackMock;
    protected $factCheckMock;
    protected $newsApiMock;
    protected $urlValidationMock;

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
        
        $this->waybackMock = Mockery::mock(WaybackMachineService::class);
        $this->factCheckMock = Mockery::mock(FactCheckApiService::class);
        $this->newsApiMock = Mockery::mock(NewsApiService::class);
        $this->urlValidationMock = Mockery::mock(UrlValidationService::class);
        
        $this->service = new ExternalApiService(
            $this->waybackMock,
            $this->factCheckMock,
            $this->newsApiMock,
            $this->urlValidationMock
        );
    }

    public function test_comprehensive_verification_with_all_services()
    {
        $testData = [
            'url' => 'https://example.com/article',
            'title' => 'Test Article Title',
            'content' => 'Test article content for verification.',
        ];

        // Mock URL validation
        $this->urlValidationMock
            ->shouldReceive('validateUrl')
            ->once()
            ->with($testData['url'])
            ->andReturn([
                'valid' => true,
                'safe' => true,
                'reputation_score' => 85,
                'warnings' => [],
            ]);

        // Mock Wayback verification
        $this->waybackMock
            ->shouldReceive('checkAvailability')
            ->once()
            ->with($testData['url'])
            ->andReturn([
                'available' => true,
                'total_snapshots' => 10,
            ]);
            
        $this->waybackMock
            ->shouldReceive('getSnapshots')
            ->once()
            ->with($testData['url'], 5)
            ->andReturn([
                ['timestamp' => '20230101120000', 'status' => '200'],
                ['timestamp' => '20230601120000', 'status' => '200'],
            ]);

        // Mock fact-checking
        $this->factCheckMock
            ->shouldReceive('verifyContent')
            ->once()
            ->with($testData['title'])
            ->andReturn([
                'overall_rating' => 78,
                'confidence' => 0.85,
                'warnings' => [],
            ]);

        // Mock news cross-reference
        $this->newsApiMock
            ->shouldReceive('crossReference')
            ->once()
            ->with($testData['title'], $testData['url'])
            ->andReturn([
                'verification' => [
                    'authenticity_score' => 0.82,
                ],
                'similar_articles' => 5,
                'warnings' => [],
            ]);

        $result = $this->service->performComprehensiveVerification($testData);

        $this->assertEquals($testData['url'], $result['url']);
        $this->assertEquals($testData['title'], $result['title']);
        $this->assertArrayHasKey('external_checks', $result);
        $this->assertArrayHasKey('url_validation', $result['external_checks']);
        $this->assertArrayHasKey('wayback_machine', $result['external_checks']);
        $this->assertArrayHasKey('fact_checking', $result['external_checks']);
        $this->assertArrayHasKey('news_cross_reference', $result['external_checks']);
        $this->assertArrayHasKey('overall_assessment', $result);
        $this->assertGreaterThan(0.5, $result['confidence_score']);
    }

    public function test_quick_verification()
    {
        $url = 'https://reuters.com/news/article';
        $title = 'Breaking News Title';

        // Mock URL validation
        $this->urlValidationMock
            ->shouldReceive('validateUrl')
            ->once()
            ->with($url)
            ->andReturn([
                'valid' => true,
                'safe' => true,
                'reputation_score' => 90,
                'warnings' => [],
            ]);

        // Mock trusted source check
        $this->urlValidationMock
            ->shouldReceive('isTrustedNewsSource')
            ->once()
            ->with($url)
            ->andReturn([
                'trusted' => true,
                'category' => 'trusted_news',
                'confidence' => 0.95,
            ]);

        // Mock suspicious pattern detection
        $this->urlValidationMock
            ->shouldReceive('detectSuspiciousPatterns')
            ->once()
            ->with($url)
            ->andReturn([
                'suspicious' => false,
                'risk_level' => 5,
                'warnings' => [],
            ]);

        // Mock Wayback availability check
        $this->waybackMock
            ->shouldReceive('checkAvailability')
            ->once()
            ->with($url)
            ->andReturn([
                'available' => true,
            ]);

        $result = $this->service->performQuickVerification($url, $title);

        $this->assertEquals($url, $result['url']);
        $this->assertEquals($title, $result['title']);
        $this->assertArrayHasKey('quick_checks', $result);
        $this->assertTrue($result['safe']);
        $this->assertGreaterThan(0.7, $result['trust_score']);
    }

    public function test_timestamp_verification()
    {
        $url = 'https://example.com/article';
        $claimedDate = '2023-06-15';

        $this->waybackMock
            ->shouldReceive('verifyTimestamp')
            ->once()
            ->with($url, $claimedDate)
            ->andReturn([
                'timestamp_accurate' => true,
                'confidence' => 0.92,
                'closest_snapshot' => '20230615120000',
            ]);

        $result = $this->service->verifyTimestamp($url, $claimedDate);

        $this->assertEquals($url, $result['url']);
        $this->assertEquals($claimedDate, $result['claimed_date']);
        $this->assertTrue($result['accurate']);
        $this->assertEquals(0.92, $result['confidence']);
    }

    public function test_enhanced_metadata_retrieval()
    {
        $testData = [
            'url' => 'https://bbc.com/news/article',
            'title' => 'Important News Story',
            'content' => 'News content for analysis.',
        ];

        // Mock URL validation
        $this->urlValidationMock
            ->shouldReceive('validateUrl')
            ->once()
            ->with($testData['url'])
            ->andReturn([
                'reputation_score' => 88,
            ]);

        // Mock trusted source check
        $this->urlValidationMock
            ->shouldReceive('isTrustedNewsSource')
            ->once()
            ->with($testData['url'])
            ->andReturn([
                'trusted' => true,
                'category' => 'trusted_news',
                'confidence' => 0.95,
            ]);

        // Mock Wayback snapshot summary
        $this->waybackMock
            ->shouldReceive('getSnapshotSummary')
            ->once()
            ->with($testData['url'])
            ->andReturn([
                'total_snapshots' => 25,
                'first_snapshot' => '20200315120000',
                'latest_snapshot' => '20231215120000',
            ]);

        // Mock news cross-reference
        $this->newsApiMock
            ->shouldReceive('crossReference')
            ->once()
            ->with($testData['title'], $testData['url'])
            ->andReturn([
                'similar_articles' => [
                    ['title' => 'Similar Article 1', 'source' => 'Reuters'],
                    ['title' => 'Similar Article 2', 'source' => 'AP News'],
                ],
            ]);

        // Mock fact-checking
        $this->factCheckMock
            ->shouldReceive('verifyContent')
            ->once()
            ->with($testData['content'])
            ->andReturn([
                'google_fact_check' => ['results' => []],
                'factcheck_org' => ['rating' => 'True'],
            ]);

        $result = $this->service->getEnhancedMetadata($testData);

        $this->assertEquals($testData['url'], $result['url']);
        $this->assertArrayHasKey('enhanced_data', $result);
        $this->assertArrayHasKey('domain_analysis', $result['enhanced_data']);
        $this->assertArrayHasKey('historical_presence', $result['enhanced_data']);
        $this->assertArrayHasKey('external_sources', $result);
        $this->assertArrayHasKey('credibility_indicators', $result);
    }

    public function test_handles_service_failures_gracefully()
    {
        $testData = [
            'url' => 'https://example.com/article',
            'title' => 'Test Article',
        ];

        // Mock URL validation failure
        $this->urlValidationMock
            ->shouldReceive('validateUrl')
            ->once()
            ->andThrow(new \Exception('URL validation failed'));

        // Other services should still be called
        $this->waybackMock
            ->shouldReceive('checkAvailability')
            ->once()
            ->andReturn(['available' => false]);
            
        $this->waybackMock
            ->shouldReceive('getSnapshots')
            ->never();

        $this->factCheckMock
            ->shouldReceive('verifyContent')
            ->once()
            ->andReturn(['overall_rating' => 50]);

        $this->newsApiMock
            ->shouldReceive('crossReference')
            ->once()
            ->andReturn(['verification' => ['authenticity_score' => 0.5]]);

        $result = $this->service->performComprehensiveVerification($testData);

        $this->assertArrayHasKey('external_checks', $result);
        // The service should have error information in the URL validation check
        $this->assertArrayHasKey('url_validation', $result['external_checks']);
        $urlCheck = $result['external_checks']['url_validation'];
        $this->assertArrayHasKey('error', $urlCheck);
    }

    public function test_confidence_score_calculation()
    {
        $testData = [
            'url' => 'https://example.com/article',
            'title' => 'Test Article',
        ];

        // Mock high-confidence results
        $this->urlValidationMock
            ->shouldReceive('validateUrl')
            ->once()
            ->andReturn(['reputation_score' => 95, 'safe' => true, 'warnings' => []]);

        $this->waybackMock
            ->shouldReceive('checkAvailability')
            ->once()
            ->andReturn(['available' => true]);
            
        $this->waybackMock
            ->shouldReceive('getSnapshots')
            ->once()
            ->andReturn([['timestamp' => '20230101120000']]);

        $this->factCheckMock
            ->shouldReceive('verifyContent')
            ->once()
            ->andReturn(['overall_rating' => 90, 'warnings' => []]);

        $this->newsApiMock
            ->shouldReceive('crossReference')
            ->once()
            ->andReturn([
                'verification' => ['authenticity_score' => 0.95],
                'warnings' => [],
            ]);

        $result = $this->service->performComprehensiveVerification($testData);

        $this->assertGreaterThan(0.8, $result['confidence_score']);
    }

    public function test_health_check_all_services()
    {
        $this->waybackMock
            ->shouldReceive('healthCheck')
            ->once()
            ->andReturn(['status' => 'healthy']);

        $this->factCheckMock
            ->shouldReceive('healthCheck')
            ->once()
            ->andReturn(['status' => 'healthy']);

        $this->newsApiMock
            ->shouldReceive('healthCheck')
            ->once()
            ->andReturn(['status' => 'healthy']);

        $this->urlValidationMock
            ->shouldReceive('healthCheck')
            ->once()
            ->andReturn(['status' => 'healthy']);

        $result = $this->service->healthCheck();

        $this->assertEquals('healthy', $result['overall_status']);
        $this->assertArrayHasKey('services', $result);
        $this->assertCount(4, $result['services']);
    }

    public function test_health_check_with_degraded_services()
    {
        $this->waybackMock
            ->shouldReceive('healthCheck')
            ->once()
            ->andReturn(['status' => 'healthy']);

        $this->factCheckMock
            ->shouldReceive('healthCheck')
            ->once()
            ->andReturn(['status' => 'unhealthy', 'error' => 'API limit exceeded']);

        $this->newsApiMock
            ->shouldReceive('healthCheck')
            ->once()
            ->andReturn(['status' => 'healthy']);

        $this->urlValidationMock
            ->shouldReceive('healthCheck')
            ->once()
            ->andReturn(['status' => 'healthy']);

        $result = $this->service->healthCheck();

        $this->assertEquals('degraded', $result['overall_status']);
    }

    public function test_collects_warnings_from_all_sources()
    {
        $testData = [
            'url' => 'https://suspicious-site.tk/article',
            'title' => 'Questionable Article',
        ];

        $this->urlValidationMock
            ->shouldReceive('validateUrl')
            ->once()
            ->andReturn([
                'safe' => false,
                'reputation_score' => 30,
                'warnings' => ['Suspicious domain TLD'],
            ]);

        $this->waybackMock
            ->shouldReceive('checkAvailability')
            ->once()
            ->andReturn(['available' => false]);
            
        $this->waybackMock
            ->shouldReceive('getSnapshots')
            ->never();

        $this->factCheckMock
            ->shouldReceive('verifyContent')
            ->once()
            ->andReturn([
                'overall_rating' => 25,
                'warnings' => ['Low credibility sources found'],
            ]);

        $this->newsApiMock
            ->shouldReceive('crossReference')
            ->once()
            ->andReturn([
                'verification' => ['authenticity_score' => 0.2],
                'warnings' => ['No similar articles found in trusted sources'],
            ]);

        $result = $this->service->performComprehensiveVerification($testData);

        $this->assertNotEmpty($result['warnings']);
        $this->assertLessThan(0.3, $result['confidence_score']);
        
        // Should include warning about low confidence
        $warningText = implode(' ', $result['warnings']);
        $this->assertStringContainsString('Low confidence', $warningText);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}