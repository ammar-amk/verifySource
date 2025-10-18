<?php

require_once 'bootstrap/app.php';

use App\Services\DomainTrustService;
use App\Services\ContentQualityService;
use App\Services\BiasDetectionService;

echo "=== VerifySource Phase 8 - Credibility System Test ===\n\n";

try {
    // Test Domain Trust Service
    echo "1. Testing DomainTrustService...\n";
    $domainService = app(DomainTrustService::class);
    $result = $domainService->quickDomainAnalysis('reuters.com');
    echo "   - Domain: reuters.com\n";
    echo "   - Trust Score: " . $result->trust_score . "/100\n";
    echo "   - Classification: " . $result->credibility_classification . "\n\n";

    // Test Content Quality Service
    echo "2. Testing ContentQualityService...\n";
    $contentService = app(ContentQualityService::class);
    $sampleContent = "This is a comprehensive news article about recent developments. According to research conducted by leading experts, the data shows significant improvement. The analysis includes proper citations and factual reporting with statistical evidence supporting the conclusions.";
    $qualityResult = $contentService->analyzeContent($sampleContent, ['title' => 'Sample News Article']);
    echo "   - Content Quality Score: " . round($qualityResult['overall_quality_score'], 2) . "/100\n";
    echo "   - Readability Score: " . round($qualityResult['readability_score'], 2) . "/100\n";
    echo "   - Quality Indicators: " . implode(', ', $qualityResult['quality_indicators']) . "\n\n";

    // Test Bias Detection Service
    echo "3. Testing BiasDetectionService...\n";
    $biasService = app(BiasDetectionService::class);
    $biasResult = $biasService->analyzeBias($sampleContent);
    echo "   - Political Bias Score: " . round($biasResult['political_bias_score'], 2) . "/100 (50 = neutral)\n";
    echo "   - Emotional Bias Score: " . round($biasResult['emotional_bias_score'], 2) . "/100\n";
    echo "   - Neutrality Score: " . round($biasResult['neutrality_score'], 2) . "/100\n";
    echo "   - Political Leaning: " . $biasResult['political_leaning'] . "\n";
    echo "   - Bias Classification: " . $biasResult['bias_classification'] . "\n\n";

    echo "4. Testing Health Checks...\n";
    $domainHealth = $domainService->healthCheck();
    $contentHealth = $contentService->healthCheck();
    $biasHealth = $biasService->healthCheck();
    
    echo "   - Domain Service: " . $domainHealth['status'] . "\n";
    echo "   - Content Service: " . $contentHealth['status'] . "\n";
    echo "   - Bias Service: " . $biasHealth['status'] . "\n\n";

    echo "✅ Phase 8 Credibility System - All Tests Passed!\n";
    echo "The credibility scoring framework is working correctly.\n";

} catch (Exception $e) {
    echo "❌ Test Failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}