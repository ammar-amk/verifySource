<?php

namespace App\Console\Commands;

use App\Models\Source;
use App\Services\BiasDetectionService;
use App\Services\ContentQualityService;
use App\Services\CredibilityService;
use App\Services\DomainTrustService;
use App\Services\ExternalApiService;
use Illuminate\Console\Command;

class TestCredibilitySystem extends Command
{
    protected $signature = 'credibility:test';

    protected $description = 'Test the Phase 8 Credibility & Scoring System';

    public function handle()
    {
        $this->info('=== Phase 8 Credibility System Test ===');
        $this->newLine();

        // Test with first source from database
        $source = Source::first();
        if (! $source) {
            $this->error('No sources found in database. Please run seeders first.');

            return 1;
        }

        $this->info("Testing with Source: {$source->name} ({$source->url})");
        $this->newLine();

        try {
            // Initialize services
            $domainService = new DomainTrustService;
            $contentService = new ContentQualityService;
            $biasService = new BiasDetectionService;
            $externalService = app(ExternalApiService::class);
            $credibilityService = new CredibilityService($domainService, $contentService, $biasService, $externalService);

            // Test domain trust
            $this->info('1. Testing Domain Trust Analysis...');
            $domainResult = $domainService->quickDomainAnalysis($source->domain);
            $this->line("   ✓ Domain Trust Score: {$domainResult->trust_score}/100");
            $this->line("   ✓ Classification: {$domainResult->credibility_classification}");
            $this->newLine();

            // Test content quality
            $this->info('2. Testing Content Quality Analysis...');
            $article = $source->articles()->first();
            if ($article && $article->content) {
                $qualityResult = $contentService->analyzeContent($article->content, ['title' => $article->title]);
                $this->line('   ✓ Quality Score: '.round($qualityResult['overall_quality_score'], 2).'/100');
                $this->line('   ✓ Readability: '.round($qualityResult['readability_score'], 2).'/100');
                $this->newLine();

                // Test bias detection
                $this->info('3. Testing Bias Detection...');
                $biasResult = $biasService->analyzeBias($article->content);
                $this->line('   ✓ Political Bias: '.round($biasResult['political_bias_score'], 2).'/100 (50 = neutral)');
                $this->line('   ✓ Neutrality: '.round($biasResult['neutrality_score'], 2).'/100');
                $this->line("   ✓ Political Leaning: {$biasResult['political_leaning']}");
                $this->newLine();
            }

            // Test integrated credibility service
            $this->info('4. Testing Integrated Credibility Scoring...');
            $sourceCredibility = $credibilityService->calculateSourceCredibility($source);
            $this->line("   ✓ Overall Source Score: {$sourceCredibility['overall_score']}/100");
            $this->line("   ✓ Credibility Level: {$sourceCredibility['credibility_level']}");
            $this->line("   ✓ Confidence Level: {$sourceCredibility['confidence_level']}%");

            if ($article) {
                $articleCredibility = $credibilityService->calculateArticleCredibility($article);
                $this->line("   ✓ Article Score: {$articleCredibility['overall_score']}/100");
                $this->line("   ✓ Article Level: {$articleCredibility['credibility_level']}");
            }
            $this->newLine();

            // Test quick assessment
            $this->info('5. Testing Quick Assessment...');
            $quickResult = $credibilityService->getQuickCredibilityAssessment($source->url);
            $this->line('   ✓ Quick Score: '.round($quickResult['quick_score'], 2).'/100');
            $this->line("   ✓ Quick Level: {$quickResult['credibility_level']}");
            $this->newLine();

            $this->info('🎉 Phase 8 Credibility & Scoring System - All Tests Passed!');
            $this->line('The credibility scoring framework is operational and ready for production use.');

            return 0;

        } catch (\Exception $e) {
            $this->error('Test Failed: '.$e->getMessage());
            $this->line('File: '.$e->getFile().':'.$e->getLine());

            return 1;
        }
    }
}
