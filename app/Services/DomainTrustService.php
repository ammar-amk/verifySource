<?php

namespace App\Services;

use App\Models\DomainTrustScore;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DomainTrustService
{
    /**
     * Analyze domain trust and credibility
     */
    public function analyzeDomain(string $domain): DomainTrustScore
    {
        $cacheKey = "domain_trust_{$domain}";

        if (config('credibility.caching.enabled')) {
            $cached = Cache::get($cacheKey);
            if ($cached) {
                return $cached;
            }
        }

        try {
            // Check if we already have a recent analysis
            $existingScore = DomainTrustScore::where('domain', $domain)
                ->where('analyzed_at', '>=', now()->subDays(config('credibility.domain_analysis_frequency_days')))
                ->first();

            if ($existingScore) {
                Cache::put($cacheKey, $existingScore, config('credibility.caching.domain_scores_ttl'));

                return $existingScore;
            }

            // Perform comprehensive domain analysis
            $trustAnalysis = $this->performDomainAnalysis($domain);

            // Calculate overall trust score
            $trustScore = $this->calculateDomainTrustScore($trustAnalysis);

            // Create or update domain trust score
            $domainTrustScore = DomainTrustScore::updateOrCreate(
                ['domain' => $domain],
                [
                    'trust_score' => $trustScore,
                    'domain_age_score' => $trustAnalysis['domain_age_score'],
                    'ssl_security_score' => $trustAnalysis['ssl_security_score'],
                    'reputation_score' => $trustAnalysis['reputation_score'],
                    'infrastructure_score' => $trustAnalysis['infrastructure_score'],
                    'trust_factors' => $trustAnalysis['trust_factors'],
                    'risk_factors' => $trustAnalysis['risk_factors'],
                    'security_indicators' => $trustAnalysis['security_indicators'],
                    'credibility_classification' => $this->classifyDomainCredibility($trustScore),
                    'analyzed_at' => now(),
                ]
            );

            // Cache the result
            if (config('credibility.caching.enabled')) {
                Cache::put($cacheKey, $domainTrustScore, config('credibility.caching.domain_scores_ttl'));
            }

            return $domainTrustScore;

        } catch (Exception $e) {
            Log::error('Domain trust analysis failed', [
                'domain' => $domain,
                'error' => $e->getMessage(),
            ]);

            // Return a default neutral score if analysis fails
            return DomainTrustScore::updateOrCreate(
                ['domain' => $domain],
                [
                    'trust_score' => 50.0,
                    'domain_age_score' => 50.0,
                    'ssl_security_score' => 50.0,
                    'reputation_score' => 50.0,
                    'infrastructure_score' => 50.0,
                    'trust_factors' => ['error' => 'Analysis failed'],
                    'risk_factors' => ['analysis_error' => $e->getMessage()],
                    'security_indicators' => [],
                    'credibility_classification' => 'unknown',
                    'analyzed_at' => now(),
                ]
            );
        }
    }

    /**
     * Quick domain analysis for immediate assessment
     */
    public function quickDomainAnalysis(string $domain): DomainTrustScore
    {
        try {
            $quickAnalysis = [
                'domain_age_score' => $this->quickDomainAgeCheck($domain),
                'ssl_security_score' => $this->quickSSLCheck($domain),
                'reputation_score' => $this->quickReputationCheck($domain),
                'infrastructure_score' => 50.0, // Default for quick analysis
                'trust_factors' => [],
                'risk_factors' => [],
                'security_indicators' => [],
            ];

            // Calculate basic trust score
            $trustScore = (
                $quickAnalysis['domain_age_score'] * 0.3 +
                $quickAnalysis['ssl_security_score'] * 0.3 +
                $quickAnalysis['reputation_score'] * 0.4
            );

            return DomainTrustScore::updateOrCreate(
                ['domain' => $domain],
                [
                    'trust_score' => $trustScore,
                    'domain_age_score' => $quickAnalysis['domain_age_score'],
                    'ssl_security_score' => $quickAnalysis['ssl_security_score'],
                    'reputation_score' => $quickAnalysis['reputation_score'],
                    'infrastructure_score' => $quickAnalysis['infrastructure_score'],
                    'trust_factors' => $quickAnalysis['trust_factors'],
                    'risk_factors' => $quickAnalysis['risk_factors'],
                    'security_indicators' => $quickAnalysis['security_indicators'],
                    'credibility_classification' => $this->classifyDomainCredibility($trustScore),
                    'analyzed_at' => now(),
                ]
            );

        } catch (Exception $e) {
            Log::error('Quick domain analysis failed', [
                'domain' => $domain,
                'error' => $e->getMessage(),
            ]);

            return $this->getDefaultDomainScore($domain);
        }
    }

    /**
     * Perform comprehensive domain analysis
     */
    private function performDomainAnalysis(string $domain): array
    {
        $analysis = [
            'domain_age_score' => 0.0,
            'ssl_security_score' => 0.0,
            'reputation_score' => 0.0,
            'infrastructure_score' => 0.0,
            'trust_factors' => [],
            'risk_factors' => [],
            'security_indicators' => [],
        ];

        // 1. Domain age and registration analysis
        $analysis['domain_age_score'] = $this->analyzeDomainAge($domain);

        // 2. SSL/TLS security analysis
        $analysis['ssl_security_score'] = $this->analyzeSSLSecurity($domain);

        // 3. Domain reputation analysis
        $analysis['reputation_score'] = $this->analyzeDomainReputation($domain);

        // 4. Infrastructure and hosting analysis
        $analysis['infrastructure_score'] = $this->analyzeInfrastructure($domain);

        // 5. Collect trust and risk factors
        $analysis['trust_factors'] = $this->collectTrustFactors($domain, $analysis);
        $analysis['risk_factors'] = $this->collectRiskFactors($domain, $analysis);
        $analysis['security_indicators'] = $this->collectSecurityIndicators($domain);

        return $analysis;
    }

    /**
     * Analyze domain age and registration details
     */
    private function analyzeDomainAge(string $domain): float
    {
        try {
            // This would integrate with WHOIS APIs to get domain registration data
            // For now, we'll use heuristics and known domain lists

            $score = 50.0; // Base score

            // Check against known established domains
            $establishedDomains = config('credibility.known_sources.highly_trusted', []);
            if (in_array($domain, $establishedDomains)) {
                return 95.0;
            }

            // Check against known new domains (would be populated from WHOIS data)
            $suspiciouslyNewDomains = config('credibility.suspicious_patterns.new_domains', []);
            if (in_array($domain, $suspiciouslyNewDomains)) {
                return 20.0;
            }

            // Heuristic: Government and academic domains are typically older
            if (preg_match('/\.(gov|edu|ac\.)/', $domain)) {
                $score += 30.0;
            }

            // Major news organizations (heuristic based on common patterns)
            if (preg_match('/(news|times|post|guardian|reuters|ap|bbc|cnn)/', $domain)) {
                $score += 20.0;
            }

            return min(100.0, max(0.0, $score));

        } catch (Exception $e) {
            Log::warning('Domain age analysis failed', ['domain' => $domain, 'error' => $e->getMessage()]);

            return 50.0;
        }
    }

    /**
     * Analyze SSL/TLS security configuration
     */
    private function analyzeSSLSecurity(string $domain): float
    {
        try {
            $score = 0.0;

            // Check if HTTPS is available
            $httpsResponse = $this->checkHTTPSAvailability($domain);
            if ($httpsResponse['available']) {
                $score += 50.0;

                // Check SSL certificate validity
                if ($httpsResponse['valid_certificate']) {
                    $score += 30.0;
                }

                // Check for modern TLS version
                if ($httpsResponse['modern_tls']) {
                    $score += 20.0;
                }
            } else {
                // No HTTPS is a major security concern for news sites
                $score = 10.0;
            }

            return min(100.0, max(0.0, $score));

        } catch (Exception $e) {
            Log::warning('SSL security analysis failed', ['domain' => $domain, 'error' => $e->getMessage()]);

            return 50.0;
        }
    }

    /**
     * Analyze domain reputation from various sources
     */
    private function analyzeDomainReputation(string $domain): float
    {
        try {
            $score = 50.0; // Base neutral score

            // Check against blacklists and reputation databases
            $reputationChecks = $this->performReputationChecks($domain);

            if ($reputationChecks['blacklisted']) {
                return 0.0;
            }

            if ($reputationChecks['whitelisted']) {
                $score += 40.0;
            }

            // Check social signals (if available)
            if (isset($reputationChecks['social_trust_score'])) {
                $score += $reputationChecks['social_trust_score'] * 0.2;
            }

            // Check external validation
            if (isset($reputationChecks['external_validations'])) {
                $score += count($reputationChecks['external_validations']) * 5.0;
            }

            return min(100.0, max(0.0, $score));

        } catch (Exception $e) {
            Log::warning('Domain reputation analysis failed', ['domain' => $domain, 'error' => $e->getMessage()]);

            return 50.0;
        }
    }

    /**
     * Analyze infrastructure and hosting quality
     */
    private function analyzeInfrastructure(string $domain): float
    {
        try {
            $score = 50.0; // Base score

            // Check hosting provider reputation
            $hostingInfo = $this->analyzeHostingProvider($domain);
            $score += $hostingInfo['reputation_score'] * 0.3;

            // Check for CDN usage (indicates professional setup)
            if ($hostingInfo['uses_cdn']) {
                $score += 15.0;
            }

            // Check server response reliability
            $reliabilityScore = $this->checkServerReliability($domain);
            $score += $reliabilityScore * 0.2;

            return min(100.0, max(0.0, $score));

        } catch (Exception $e) {
            Log::warning('Infrastructure analysis failed', ['domain' => $domain, 'error' => $e->getMessage()]);

            return 50.0;
        }
    }

    /**
     * Check HTTPS availability and certificate validity
     */
    private function checkHTTPSAvailability(string $domain): array
    {
        try {
            $response = Http::timeout(10)->get("https://{$domain}");

            return [
                'available' => $response->successful(),
                'valid_certificate' => true, // Would need more sophisticated SSL cert validation
                'modern_tls' => true, // Would need to check TLS version
            ];
        } catch (Exception $e) {
            return [
                'available' => false,
                'valid_certificate' => false,
                'modern_tls' => false,
            ];
        }
    }

    /**
     * Perform reputation checks against various databases
     */
    private function performReputationChecks(string $domain): array
    {
        $checks = [
            'blacklisted' => false,
            'whitelisted' => false,
            'social_trust_score' => null,
            'external_validations' => [],
        ];

        // Check against known lists from configuration
        $knownSources = config('credibility.known_sources');

        if (in_array($domain, $knownSources['highly_trusted'])) {
            $checks['whitelisted'] = true;
        }

        if (in_array($domain, $knownSources['unreliable_sources'])) {
            $checks['blacklisted'] = true;
        }

        // Additional reputation checks would go here
        // This could integrate with services like VirusTotal, Google Safe Browsing, etc.

        return $checks;
    }

    /**
     * Analyze hosting provider reputation
     */
    private function analyzeHostingProvider(string $domain): array
    {
        try {
            // This would integrate with IP geolocation and hosting detection services
            // For now, return default values
            return [
                'reputation_score' => 50.0,
                'uses_cdn' => false,
                'hosting_country' => 'unknown',
                'hosting_provider' => 'unknown',
            ];
        } catch (Exception $e) {
            return [
                'reputation_score' => 50.0,
                'uses_cdn' => false,
                'hosting_country' => 'unknown',
                'hosting_provider' => 'unknown',
            ];
        }
    }

    /**
     * Check server reliability and response times
     */
    private function checkServerReliability(string $domain): float
    {
        try {
            $startTime = microtime(true);
            $response = Http::timeout(10)->get("https://{$domain}");
            $responseTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

            if (! $response->successful()) {
                return 20.0;
            }

            // Score based on response time
            if ($responseTime < 500) {
                return 90.0;
            } elseif ($responseTime < 1000) {
                return 70.0;
            } elseif ($responseTime < 2000) {
                return 50.0;
            } else {
                return 30.0;
            }
        } catch (Exception $e) {
            return 20.0;
        }
    }

    /**
     * Collect trust factors for the domain
     */
    private function collectTrustFactors(string $domain, array $analysis): array
    {
        $factors = [];

        if ($analysis['domain_age_score'] > 70) {
            $factors[] = 'Well-established domain';
        }

        if ($analysis['ssl_security_score'] > 80) {
            $factors[] = 'Strong SSL/TLS security';
        }

        if ($analysis['reputation_score'] > 70) {
            $factors[] = 'Good domain reputation';
        }

        if ($analysis['infrastructure_score'] > 70) {
            $factors[] = 'Professional hosting infrastructure';
        }

        // Check for government/academic domains
        if (preg_match('/\.(gov|edu)$/', $domain)) {
            $factors[] = 'Government or academic institution';
        }

        return $factors;
    }

    /**
     * Collect risk factors for the domain
     */
    private function collectRiskFactors(string $domain, array $analysis): array
    {
        $factors = [];

        if ($analysis['domain_age_score'] < 30) {
            $factors[] = 'Very new domain registration';
        }

        if ($analysis['ssl_security_score'] < 50) {
            $factors[] = 'Inadequate SSL/TLS security';
        }

        if ($analysis['reputation_score'] < 30) {
            $factors[] = 'Poor domain reputation';
        }

        // Check for suspicious TLDs
        $suspiciousTlds = ['.tk', '.ml', '.ga', '.cf', '.click', '.download'];
        foreach ($suspiciousTlds as $tld) {
            if (str_ends_with($domain, $tld)) {
                $factors[] = 'Suspicious top-level domain';
                break;
            }
        }

        return $factors;
    }

    /**
     * Collect security indicators
     */
    private function collectSecurityIndicators(string $domain): array
    {
        $indicators = [];

        try {
            // Check for basic security headers (would need more sophisticated analysis)
            $response = Http::timeout(10)->get("https://{$domain}");

            if ($response->hasHeader('Strict-Transport-Security')) {
                $indicators[] = 'HSTS enabled';
            }

            if ($response->hasHeader('Content-Security-Policy')) {
                $indicators[] = 'Content Security Policy configured';
            }

        } catch (Exception $e) {
            // Ignore errors for security indicator collection
        }

        return $indicators;
    }

    /**
     * Calculate overall domain trust score
     */
    private function calculateDomainTrustScore(array $analysis): float
    {
        $weights = config('credibility.domain_trust_weights', [
            'domain_age' => 0.25,
            'ssl_security' => 0.25,
            'reputation' => 0.35,
            'infrastructure' => 0.15,
        ]);

        $score =
            ($analysis['domain_age_score'] * $weights['domain_age']) +
            ($analysis['ssl_security_score'] * $weights['ssl_security']) +
            ($analysis['reputation_score'] * $weights['reputation']) +
            ($analysis['infrastructure_score'] * $weights['infrastructure']);

        return max(0.0, min(100.0, $score));
    }

    /**
     * Classify domain credibility based on trust score
     */
    private function classifyDomainCredibility(float $trustScore): string
    {
        if ($trustScore >= 80) {
            return 'highly_trusted';
        }
        if ($trustScore >= 65) {
            return 'trusted';
        }
        if ($trustScore >= 50) {
            return 'neutral';
        }
        if ($trustScore >= 30) {
            return 'questionable';
        }

        return 'untrusted';
    }

    /**
     * Quick domain age check
     */
    private function quickDomainAgeCheck(string $domain): float
    {
        // Quick heuristic-based domain age assessment
        $knownSources = config('credibility.known_sources');

        if (in_array($domain, $knownSources['highly_trusted'])) {
            return 90.0;
        }

        if (preg_match('/\.(gov|edu)$/', $domain)) {
            return 85.0;
        }

        return 50.0; // Default neutral score
    }

    /**
     * Quick SSL check
     */
    private function quickSSLCheck(string $domain): float
    {
        try {
            $response = Http::timeout(5)->get("https://{$domain}");

            return $response->successful() ? 80.0 : 20.0;
        } catch (Exception $e) {
            return 20.0;
        }
    }

    /**
     * Quick reputation check
     */
    private function quickReputationCheck(string $domain): float
    {
        $knownSources = config('credibility.known_sources');

        if (in_array($domain, $knownSources['highly_trusted'])) {
            return 95.0;
        }

        if (in_array($domain, $knownSources['trusted_news'])) {
            return 80.0;
        }

        if (in_array($domain, $knownSources['unreliable_sources'])) {
            return 10.0;
        }

        return 50.0; // Default neutral score
    }

    /**
     * Get default domain score when analysis fails
     */
    private function getDefaultDomainScore(string $domain): DomainTrustScore
    {
        return new DomainTrustScore([
            'domain' => $domain,
            'trust_score' => 50.0,
            'domain_age_score' => 50.0,
            'ssl_security_score' => 50.0,
            'reputation_score' => 50.0,
            'infrastructure_score' => 50.0,
            'trust_factors' => [],
            'risk_factors' => ['Analysis failed - default score applied'],
            'security_indicators' => [],
            'credibility_classification' => 'unknown',
            'analyzed_at' => now(),
        ]);
    }

    /**
     * Health check for the service
     */
    public function healthCheck(): array
    {
        try {
            // Test basic connectivity
            $testDomain = 'google.com';
            $response = Http::timeout(5)->get("https://{$testDomain}");

            return [
                'status' => $response->successful() ? 'healthy' : 'degraded',
                'connectivity' => $response->successful(),
                'response_time' => $response->transferStats?->getTransferTime() ?? null,
            ];
        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }
}
