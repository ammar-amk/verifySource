<?php

namespace Tests\Unit\Services;

use App\Services\UrlValidationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UrlValidationServiceTest extends TestCase
{
    protected UrlValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new UrlValidationService;
    }

    public function test_validates_valid_url()
    {
        Http::fake([
            'https://example.com' => Http::response(null, 200),
        ]);

        $result = $this->service->validateUrl('https://example.com');

        $this->assertTrue($result['valid']);
        $this->assertNotEmpty($result['checks']);
        $this->assertArrayHasKey('basic', $result['checks']);
        $this->assertArrayHasKey('domain_reputation', $result['checks']);
        $this->assertArrayHasKey('accessibility', $result['checks']);
    }

    public function test_rejects_invalid_url()
    {
        $result = $this->service->validateUrl('not-a-valid-url');

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertContains('Invalid URL format', $result['errors']);
    }

    public function test_detects_trusted_news_sources()
    {
        $trustedSources = [
            'https://reuters.com/article',
            'https://bbc.com/news',
            'https://apnews.com/story',
        ];

        foreach ($trustedSources as $url) {
            $result = $this->service->isTrustedNewsSource($url);

            $this->assertTrue($result['trusted'], "Failed for URL: {$url}");
            $this->assertEquals('trusted_news', $result['category']);
            $this->assertGreaterThan(0.8, $result['confidence']);
        }
    }

    public function test_detects_government_domains()
    {
        $govSources = [
            'https://cdc.gov/health',
            'https://gov.uk/guidance',
            'https://who.int/news',
        ];

        foreach ($govSources as $url) {
            $result = $this->service->isTrustedNewsSource($url);

            $this->assertTrue($result['trusted'], "Failed for URL: {$url}");
            $this->assertEquals('government', $result['category']);
            $this->assertGreaterThan(0.9, $result['confidence']);
        }
    }

    public function test_detects_academic_domains()
    {
        $academicSources = [
            'https://harvard.edu/research',
            'https://cambridge.ac.uk/study',
        ];

        foreach ($academicSources as $url) {
            $result = $this->service->isTrustedNewsSource($url);

            $this->assertTrue($result['trusted'], "Failed for URL: {$url}");
            $this->assertEquals('academic', $result['category']);
            $this->assertGreaterThan(0.9, $result['confidence']);
        }
    }

    public function test_detects_suspicious_patterns()
    {
        $suspiciousUrls = [
            'https://bit.ly/shortlink' => 'URL shortener',
            'https://suspicious.tk/page' => 'Suspicious TLD',
            'http://192.168.1.1/content' => 'IP address',
            'https://example.com/free-download-click-now' => 'Suspicious keywords',
        ];

        foreach ($suspiciousUrls as $url => $expectedWarning) {
            $result = $this->service->detectSuspiciousPatterns($url);

            $this->assertTrue($result['suspicious'], "Failed to detect suspicious pattern in: {$url}");
            $this->assertGreaterThan(0, $result['risk_level']);
            $this->assertNotEmpty($result['warnings']);
        }
    }

    public function test_accessibility_check_with_successful_response()
    {
        Http::fake([
            'https://example.com' => Http::response(null, 200, ['Content-Type' => 'text/html']),
        ]);

        $result = $this->service->validateUrl('https://example.com');

        $this->assertTrue($result['checks']['accessibility']['accessible']);
        $this->assertEquals(200, $result['checks']['accessibility']['status_code']);
    }

    public function test_accessibility_check_with_failed_response()
    {
        Http::fake([
            'https://example.com' => Http::response(null, 404),
        ]);

        $result = $this->service->validateUrl('https://example.com');

        $this->assertFalse($result['checks']['accessibility']['accessible']);
        $this->assertEquals(404, $result['checks']['accessibility']['status_code']);
    }

    public function test_caches_accessibility_results()
    {
        Cache::shouldReceive('get')
            ->once()
            ->with(\Mockery::type('string'))
            ->andReturn(null);

        Cache::shouldReceive('put')
            ->once()
            ->with(\Mockery::type('string'), \Mockery::type('array'), 300);

        Http::fake([
            'https://example.com' => Http::response(null, 200),
        ]);

        $this->service->validateUrl('https://example.com');
    }

    public function test_handles_network_errors_gracefully()
    {
        Http::fake([
            'https://example.com' => function () {
                throw new \Exception('Network error');
            },
        ]);

        $result = $this->service->validateUrl('https://example.com');

        $this->assertFalse($result['checks']['accessibility']['accessible']);
        $this->assertArrayHasKey('error', $result['checks']['accessibility']);
    }

    public function test_calculates_reputation_scores()
    {
        Http::fake([
            'https://trusted-site.com' => Http::response(null, 200),
            'https://unknown-site.net' => Http::response(null, 200),
        ]);

        // Test trusted domain
        $trustedResult = $this->service->validateUrl('https://trusted-site.com');
        $this->assertGreaterThan(50, $trustedResult['reputation_score']);

        // Test unknown domain
        $unknownResult = $this->service->validateUrl('https://unknown-site.net');
        $this->assertLessThanOrEqual(70, $unknownResult['reputation_score']);
    }

    public function test_health_check()
    {
        Http::fake([
            'https://httpbin.org/status/200' => Http::response(null, 200),
        ]);

        $health = $this->service->healthCheck();

        $this->assertEquals('healthy', $health['status']);
        $this->assertTrue($health['basic_validation']);
        $this->assertTrue($health['accessibility_check']);
        $this->assertTrue($health['domain_lists_loaded']);
    }

    public function test_validates_urls_with_different_protocols()
    {
        $validUrls = [
            'https://example.com',
            'http://example.com',
        ];

        $invalidUrls = [
            'ftp://example.com',
            'file:///local/path',
        ];

        foreach ($validUrls as $url) {
            $result = $this->service->validateUrl($url);
            $this->assertTrue($result['checks']['basic']['valid'], "Should accept URL: {$url}");
        }

        foreach ($invalidUrls as $url) {
            $result = $this->service->validateUrl($url);
            $this->assertFalse($result['checks']['basic']['valid'], "Should reject URL: {$url}");
        }
    }

    public function test_domain_reputation_factors()
    {
        Http::fake(['*' => Http::response(null, 200)]);

        // Test government domain
        $govResult = $this->service->validateUrl('https://cdc.gov');
        $this->assertGreaterThan(70, $govResult['reputation_score']);

        // Test suspicious TLD
        $suspiciousResult = $this->service->validateUrl('https://example.tk');
        $this->assertLessThan(55, $suspiciousResult['reputation_score']);
    }

    public function test_collects_comprehensive_warnings()
    {
        Http::fake([
            'https://bit.ly/test' => Http::response(null, 404),
        ]);

        $result = $this->service->validateUrl('https://bit.ly/test');

        $this->assertNotEmpty($result['warnings']);

        // Should have warnings about URL shortener and inaccessibility
        $warningText = implode(' ', $result['warnings']);
        $this->assertStringContainsString('accessible', $warningText);
    }
}
