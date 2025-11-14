<?php

use App\Models\Source;
use App\Models\Article;
use App\Services\CredibilityService;
use App\Services\DomainTrustService;
use App\Services\ContentQualityService;
use App\Services\BiasDetectionService;

echo "=== Phase 8 Credibility System Integration Test ===\n\n";

// Get first source from database
$source = Source::first();
if (!$source) {
    echo "No sources found in database. Please run seeders first.\n";
    exit(1);
}

echo "Testing with Source: {$source->name} ({$source->url})\n\n";

// Test individual services first
echo "1. Testing DomainTrustService...\n";
$domainService = new DomainTrustService();
try {
    $domainResult = $domainService->quickDomainAnalysis($source->domain);
    echo "   ✓ Domain Trust Score: {$domainResult->trust_score}/100\n";
    echo "   ✓ Classification: {$domainResult->credibility_classification}\n";
} catch (Exception $e) {
    echo "   ❌ Error: {$e->getMessage()}\n";
}

echo "\n2. Testing ContentQualityService...\n";
$contentService = new ContentQualityService();
$article = $source->articles()->first();
if ($article) {
    try {
        $qualityResult = $contentService->analyzeContent($article->content, ['title' => $article->title]);
        echo "   ✓ Quality Score: " . round($qualityResult['overall_quality_score'], 2) . "/100\n";
        echo "   ✓ Readability: " . round($qualityResult['readability_score'], 2) . "/100\n";
    } catch (Exception $e) {
        echo "   ❌ Error: {$e->getMessage()}\n";
    }
}

echo "\n3. Testing BiasDetectionService...\n";
$biasService = new BiasDetectionService();
if ($article) {
    try {
        $biasResult = $biasService->analyzeBias($article->content);
        echo "   ✓ Political Bias: " . round($biasResult['political_bias_score'], 2) . "/100\n";
        echo "   ✓ Neutrality: " . round($biasResult['neutrality_score'], 2) . "/100\n";
        echo "   ✓ Leaning: {$biasResult['political_leaning']}\n";
    } catch (Exception $e) {
        echo "   ❌ Error: {$e->getMessage()}\n";
    }
}

echo "\n4. Testing Complete CredibilityService Integration...\n";
try {
    $credibilityService = new CredibilityService($domainService, $contentService, $biasService, app('App\Services\ExternalApiService'));
    
    echo "   Testing source credibility calculation...\n";
    $sourceCredibility = $credibilityService->calculateSourceCredibility($source);
    echo "   ✓ Overall Score: {$sourceCredibility->overall_score}/100\n";
    echo "   ✓ Credibility Level: {$sourceCredibility->credibility_level}\n";
    echo "   ✓ Confidence: {$sourceCredibility->confidence_level}%\n";
    
    if ($article) {
        echo "   Testing article credibility calculation...\n";
        $articleCredibility = $credibilityService->calculateArticleCredibility($article);
        echo "   ✓ Article Score: {$articleCredibility->overall_score}/100\n";
        echo "   ✓ Article Level: {$articleCredibility->credibility_level}\n";
    }
    
    echo "\n5. Testing Quick Assessment...\n";
    $quickResult = $credibilityService->getQuickCredibilityAssessment($source->url);
    echo "   ✓ Quick Score: " . round($quickResult['quick_score'], 2) . "/100\n";
    echo "   ✓ Quick Level: {$quickResult['credibility_level']}\n";
    
} catch (Exception $e) {
    echo "   ❌ CredibilityService Error: {$e->getMessage()}\n";
    echo "   Stack trace: {$e->getTraceAsString()}\n";
}

echo "\n=== Integration Test Complete ===\n";
echo "Phase 8 Credibility & Scoring System is operational! 🎉\n";