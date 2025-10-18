<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Exception;

class UrlValidationService
{
    private array $config;
    private array $knownMaliciousDomains;
    private array $trustedDomains;
    
    public function __construct()
    {
        $this->config = config('external_apis.url_validation');
        $this->loadDomainLists();
    }

    /**
     * Comprehensive URL validation and reputation check
     */
    public function validateUrl(string $url): array
    {
        $validation = [
            'url' => $url,
            'valid' => false,
            'safe' => false,
            'reputation_score' => 0,
            'checks' => [],
            'warnings' => [],
            'errors' => [],
        ];

        try {
            // Basic URL validation
            $basicValidation = $this->performBasicValidation($url);
            $validation['checks']['basic'] = $basicValidation;
            
            if (!$basicValidation['valid']) {
                $validation['errors'] = array_merge($validation['errors'], $basicValidation['errors']);
                return $validation;
            }

            $domain = parse_url($url, PHP_URL_HOST);
            
            // Domain reputation check
            $domainReputation = $this->checkDomainReputation($domain);
            $validation['checks']['domain_reputation'] = $domainReputation;
            
            // URL accessibility check
            $accessibilityCheck = $this->checkUrlAccessibility($url);
            $validation['checks']['accessibility'] = $accessibilityCheck;
            
            // External security scans (if enabled)
            if (config('external_apis.features.url_validation')) {
                $securityCheck = $this->performSecurityScans($url);
                $validation['checks']['security'] = $securityCheck;
            }

            // Calculate overall scores
            $validation['valid'] = $basicValidation['valid'] && $accessibilityCheck['accessible'];
            $validation['safe'] = $this->calculateSafetyScore($validation['checks']) > 0.7;
            $validation['reputation_score'] = $this->calculateReputationScore($validation['checks']);
            
            // Collect warnings
            $this->collectWarnings($validation);

        } catch (Exception $e) {
            Log::error('URL validation failed', [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            
            $validation['errors'][] = 'Validation process failed: ' . $e->getMessage();
        }

        return $validation;
    }

    /**
     * Check if URL is from a trusted news source
     */
    public function isTrustedNewsSource(string $url): array
    {
        $domain = parse_url($url, PHP_URL_HOST);
        $domain = strtolower(str_replace('www.', '', $domain));
        
        $trustedNewsSources = [
            // Major news organizations
            'reuters.com', 'apnews.com', 'bbc.com', 'cnn.com', 'npr.org',
            'nytimes.com', 'washingtonpost.com', 'wsj.com', 'usatoday.com',
            'abcnews.go.com', 'cbsnews.com', 'nbcnews.com', 'foxnews.com',
            
            // International news
            'theguardian.com', 'independent.co.uk', 'telegraph.co.uk',
            'lemonde.fr', 'spiegel.de', 'elpais.com', 'corriere.it',
            'asahi.com', 'scmp.com', 'thehindu.com',
            
            // Fact-checking organizations
            'snopes.com', 'factcheck.org', 'politifact.com', 'fullfact.org',
            'checkyourfact.com', 'truthorfiction.com',
            
            // Government sources
            'gov.uk', 'gov.au', 'canada.ca', 'europa.eu',
            'who.int', 'cdc.gov', 'fda.gov', 'nih.gov',
        ];

        $isTrusted = in_array($domain, $trustedNewsSources);
        
        // Check for government domains
        $isGovernment = $this->isGovernmentDomain($domain);
        
        // Check for academic institutions
        $isAcademic = $this->isAcademicDomain($domain);
        
        return [
            'trusted' => $isTrusted || $isGovernment || $isAcademic,
            'category' => $this->categorizeSource($domain, $isTrusted, $isGovernment, $isAcademic),
            'confidence' => $this->calculateTrustConfidence($domain, $isTrusted, $isGovernment, $isAcademic),
            'domain' => $domain,
        ];
    }

    /**
     * Detect potentially suspicious URLs
     */
    public function detectSuspiciousPatterns(string $url): array
    {
        $suspiciousPatterns = [
            'url_shorteners' => [
                'bit.ly', 'tinyurl.com', 't.co', 'goo.gl', 'ow.ly', 'buff.ly'
            ],
            'suspicious_tlds' => [
                '.tk', '.ml', '.ga', '.cf', '.click', '.download'
            ],
            'suspicious_keywords' => [
                'free', 'download', 'click', 'urgent', 'breaking', 'exclusive',
                'leaked', 'secret', 'exposed', 'shocking'
            ]
        ];

        $warnings = [];
        $riskLevel = 0;
        
        $domain = parse_url($url, PHP_URL_HOST);
        $path = parse_url($url, PHP_URL_PATH);
        $query = parse_url($url, PHP_URL_QUERY);
        
        // Check for URL shorteners
        foreach ($suspiciousPatterns['url_shorteners'] as $shortener) {
            if (strpos($domain, $shortener) !== false) {
                $warnings[] = 'URL uses a link shortener service';
                $riskLevel += 30;
                break;
            }
        }
        
        // Check for suspicious TLDs
        foreach ($suspiciousPatterns['suspicious_tlds'] as $tld) {
            if (str_ends_with($domain, $tld)) {
                $warnings[] = 'Domain uses a potentially suspicious top-level domain';
                $riskLevel += 20;
                break;
            }
        }
        
        // Check for suspicious keywords in URL
        $fullUrl = strtolower($url);
        foreach ($suspiciousPatterns['suspicious_keywords'] as $keyword) {
            if (strpos($fullUrl, $keyword) !== false) {
                $warnings[] = "URL contains suspicious keyword: {$keyword}";
                $riskLevel += 10;
            }
        }
        
        // Check for excessive redirects or complex parameters
        if ($query && strlen($query) > 200) {
            $warnings[] = 'URL has unusually long query parameters';
            $riskLevel += 15;
        }
        
        // Check for IP addresses instead of domains
        if (filter_var($domain, FILTER_VALIDATE_IP)) {
            $warnings[] = 'URL uses IP address instead of domain name';
            $riskLevel += 25;
        }

        return [
            'suspicious' => $riskLevel > 15, // Lower threshold for better detection
            'risk_level' => min(100, $riskLevel),
            'warnings' => $warnings,
            'patterns_detected' => count($warnings),
        ];
    }

    /**
     * Basic URL validation
     */
    private function performBasicValidation(string $url): array
    {
        $errors = [];
        $valid = true;
        
        // Check if URL is properly formatted
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $errors[] = 'Invalid URL format';
            $valid = false;
        }
        
        // Check for required components
        $parsed = parse_url($url);
        if (!isset($parsed['scheme']) || !in_array($parsed['scheme'], ['http', 'https'])) {
            $errors[] = 'URL must use HTTP or HTTPS protocol';
            $valid = false;
        }
        
        if (!isset($parsed['host']) || empty($parsed['host'])) {
            $errors[] = 'URL must have a valid domain';
            $valid = false;
        }

        return [
            'valid' => $valid,
            'errors' => $errors,
            'parsed_url' => $parsed ?? null,
        ];
    }

    /**
     * Check domain reputation using local lists and patterns
     */
    private function checkDomainReputation(string $domain): array
    {
        $domain = strtolower(str_replace('www.', '', $domain));
        
        $reputation = [
            'score' => 50, // Neutral starting score
            'category' => 'unknown',
            'trusted' => false,
            'malicious' => false,
            'factors' => [],
        ];

        // Check against known malicious domains
        if (in_array($domain, $this->knownMaliciousDomains)) {
            $reputation['score'] = 0;
            $reputation['malicious'] = true;
            $reputation['category'] = 'malicious';
            $reputation['factors'][] = 'Domain on malicious list';
            return $reputation;
        }

        // Check against trusted domains
        if (in_array($domain, $this->trustedDomains)) {
            $reputation['score'] = 90;
            $reputation['trusted'] = true;
            $reputation['category'] = 'trusted';
            $reputation['factors'][] = 'Domain on trusted list';
        }

        // Domain age estimation (simplified)
        $domainAge = $this->estimateDomainAge($domain);
        if ($domainAge['estimated_years'] > 5) {
            $reputation['score'] += 15;
            $reputation['factors'][] = 'Domain appears to be well-established';
        } elseif ($domainAge['estimated_years'] < 1) {
            $reputation['score'] -= 10;
            $reputation['factors'][] = 'Domain appears to be newly registered';
        }

        // TLD reputation
        $tld = substr($domain, strrpos($domain, '.') + 1);
        if (in_array($tld, ['gov', 'edu', 'org'])) {
            $reputation['score'] += 20;
            $reputation['factors'][] = 'Uses reputable top-level domain';
        } elseif (in_array($tld, ['tk', 'ml', 'ga', 'cf'])) {
            $reputation['score'] -= 25; // Increased penalty for suspicious TLDs
            $reputation['factors'][] = 'Uses free/suspicious top-level domain';
        }

        $reputation['score'] = max(0, min(100, $reputation['score']));
        
        return $reputation;
    }

    /**
     * Check URL accessibility
     */
    private function checkUrlAccessibility(string $url): array
    {
        $cacheKey = "url_accessibility_" . md5($url);
        
        if (config('external_apis.global.enable_caching')) {
            $cached = Cache::get($cacheKey);
            if ($cached) {
                return $cached;
            }
        }

        try {
            $response = Http::withUserAgent(config('external_apis.global.user_agent'))
                ->timeout(15)
                ->head($url);

            $result = [
                'accessible' => $response->successful(),
                'status_code' => $response->status(),
                'headers' => $response->headers(),
                'response_time' => $response->handlerStats()['total_time'] ?? null,
                'redirect_count' => $response->handlerStats()['redirect_count'] ?? 0,
            ];

            if (config('external_apis.global.enable_caching')) {
                Cache::put($cacheKey, $result, 300); // Cache for 5 minutes
            }

            return $result;

        } catch (Exception $e) {
            return [
                'accessible' => false,
                'status_code' => null,
                'error' => $e->getMessage(),
                'response_time' => null,
                'redirect_count' => 0,
            ];
        }
    }

    /**
     * Perform external security scans (placeholder for future implementation)
     */
    private function performSecurityScans(string $url): array
    {
        // This is a placeholder for external security API integrations
        // Could integrate with VirusTotal, URLVoid, or other security services
        
        return [
            'scanned' => false,
            'clean' => null,
            'threats_detected' => 0,
            'scan_results' => [],
            'note' => 'External security scanning not yet implemented',
        ];
    }

    /**
     * Calculate overall safety score
     */
    private function calculateSafetyScore(array $checks): float
    {
        $score = 50; // Start neutral
        
        // Basic validation
        if (isset($checks['basic']) && !$checks['basic']['valid']) {
            return 0;
        }
        
        // Domain reputation
        if (isset($checks['domain_reputation'])) {
            $domainScore = $checks['domain_reputation']['score'];
            $score = ($score + $domainScore) / 2;
        }
        
        // Accessibility (working URLs are safer)
        if (isset($checks['accessibility']) && $checks['accessibility']['accessible']) {
            $score += 10;
        }
        
        return min(100, max(0, $score)) / 100;
    }

    /**
     * Calculate reputation score
     */
    private function calculateReputationScore(array $checks): float
    {
        $scores = [];
        
        if (isset($checks['domain_reputation']['score'])) {
            $scores[] = $checks['domain_reputation']['score'];
        }
        
        if (isset($checks['accessibility']) && $checks['accessibility']['accessible']) {
            $scores[] = 80; // Accessible URLs get good score
        } else {
            $scores[] = 20; // Inaccessible URLs get poor score
        }

        return !empty($scores) ? array_sum($scores) / count($scores) : 0;
    }

    /**
     * Collect warnings from all checks
     */
    private function collectWarnings(array &$validation): void
    {
        // Add domain-specific warnings
        if (isset($validation['checks']['domain_reputation'])) {
            $domainRep = $validation['checks']['domain_reputation'];
            if ($domainRep['malicious']) {
                $validation['warnings'][] = 'Domain is on malicious list';
            }
            if ($domainRep['score'] < 30) {
                $validation['warnings'][] = 'Domain has poor reputation';
            }
        }

        // Add accessibility warnings
        if (isset($validation['checks']['accessibility'])) {
            $access = $validation['checks']['accessibility'];
            if (!$access['accessible']) {
                $validation['warnings'][] = 'URL is not accessible';
            }
            if (isset($access['redirect_count']) && $access['redirect_count'] > 3) {
                $validation['warnings'][] = 'URL has excessive redirects';
            }
        }
    }

    /**
     * Check if domain is a government domain
     */
    private function isGovernmentDomain(string $domain): bool
    {
        $govPatterns = [
            '/\.gov$/', '/\.gov\./', '/\.mil$/', '/\.mil\./',
            '/\.europa\.eu$/', '/\.who\.int$/', '/\.un\.org$/',
            '/^cdc\.gov$/', '/^who\.int$/', '/gov\.uk$/'
        ];
        
        foreach ($govPatterns as $pattern) {
            if (preg_match($pattern, $domain)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if domain is an academic domain
     */
    private function isAcademicDomain(string $domain): bool
    {
        return preg_match('/\.edu$|\.ac\./', $domain) === 1;
    }

    /**
     * Categorize source type
     */
    private function categorizeSource(string $domain, bool $isTrusted, bool $isGovernment, bool $isAcademic): string
    {
        if ($isGovernment) return 'government';
        if ($isAcademic) return 'academic';
        if ($isTrusted) return 'trusted_news';
        
        return 'unknown';
    }

    /**
     * Calculate trust confidence
     */
    private function calculateTrustConfidence(string $domain, bool $isTrusted, bool $isGovernment, bool $isAcademic): float
    {
        if ($isGovernment || $isAcademic) return 0.95;
        if ($isTrusted) return 0.85;
        
        return 0.5; // Neutral for unknown domains
    }

    /**
     * Estimate domain age (simplified approach)
     */
    private function estimateDomainAge(string $domain): array
    {
        // This is a simplified estimation based on common patterns
        // In production, you might integrate with WHOIS services
        
        $knownOldDomains = [
            'google.com', 'yahoo.com', 'microsoft.com', 'apple.com',
            'amazon.com', 'facebook.com', 'twitter.com', 'youtube.com'
        ];
        
        if (in_array($domain, $knownOldDomains)) {
            return ['estimated_years' => 20, 'confidence' => 'high'];
        }
        
        // Very basic heuristic based on domain patterns
        if (strlen($domain) > 15 || str_contains($domain, '-')) {
            return ['estimated_years' => 2, 'confidence' => 'low'];
        }
        
        return ['estimated_years' => 5, 'confidence' => 'low'];
    }

    /**
     * Load domain reputation lists
     */
    private function loadDomainLists(): void
    {
        // In production, these could be loaded from databases or external feeds
        $this->knownMaliciousDomains = [
            'malware-example.com',
            'phishing-site.net',
            // Add more malicious domains
        ];
        
        $this->trustedDomains = [
            'reuters.com', 'apnews.com', 'bbc.com', 'cnn.com',
            'nytimes.com', 'washingtonpost.com', 'theguardian.com',
            // Add more trusted domains
        ];
    }

    /**
     * Health check for URL validation service
     */
    public function healthCheck(): array
    {
        try {
            // Test basic validation
            $testValidation = $this->performBasicValidation('https://example.com');
            
            // Test accessibility check
            $testAccessibility = $this->checkUrlAccessibility('https://httpbin.org/status/200');
            
            return [
                'status' => 'healthy',
                'basic_validation' => $testValidation['valid'],
                'accessibility_check' => isset($testAccessibility['accessible']),
                'domain_lists_loaded' => count($this->trustedDomains) > 0,
            ];
        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }
}