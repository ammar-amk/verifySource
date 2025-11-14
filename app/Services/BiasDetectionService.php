<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Exception;

class BiasDetectionService
{
    /**
     * Analyze bias in content comprehensively
     */
    public function analyzeBias(string $content, array $metadata = []): array
    {
        try {
            $analysis = [
                'political_bias_score' => 0.0,
                'emotional_bias_score' => 0.0,
                'factual_reporting_score' => 0.0,
                'neutrality_score' => 0.0,
                'political_leaning' => 'neutral',
                'bias_classification' => 'minimal',
                'detected_patterns' => [],
                'language_analysis' => [],
                'confidence_metrics' => [],
                'explanation' => '',
            ];

            // 1. Analyze political bias
            $analysis['political_bias_score'] = $this->analyzePoliticalBias($content, $metadata);
            
            // 2. Analyze emotional bias and loaded language
            $analysis['emotional_bias_score'] = $this->analyzeEmotionalBias($content);
            
            // 3. Analyze factual reporting quality
            $analysis['factual_reporting_score'] = $this->analyzeFactualReporting($content);
            
            // 4. Calculate neutrality score
            $analysis['neutrality_score'] = $this->calculateNeutralityScore($analysis);
            
            // 5. Determine political leaning
            $analysis['political_leaning'] = $this->determinePoliticalLeaning($content, $analysis);
            
            // 6. Classify bias level
            $analysis['bias_classification'] = $this->classifyBiasLevel($analysis);
            
            // 7. Detect bias patterns
            $analysis['detected_patterns'] = $this->detectBiasPatterns($content);
            
            // 8. Analyze language characteristics
            $analysis['language_analysis'] = $this->analyzeLanguageCharacteristics($content);
            
            // 9. Calculate confidence metrics
            $analysis['confidence_metrics'] = $this->calculateConfidenceMetrics($content, $analysis);
            
            // 10. Generate explanation
            $analysis['explanation'] = $this->generateBiasExplanation($analysis);

            return $analysis;

        } catch (Exception $e) {
            Log::error('Bias detection analysis failed', [
                'error' => $e->getMessage(),
                'content_length' => strlen($content)
            ]);
            
            // Return default analysis on error
            return [
                'political_bias_score' => 50.0,
                'emotional_bias_score' => 50.0,
                'factual_reporting_score' => 50.0,
                'neutrality_score' => 50.0,
                'political_leaning' => 'unknown',
                'bias_classification' => 'unknown',
                'detected_patterns' => [],
                'language_analysis' => [],
                'confidence_metrics' => ['analysis_failed' => true],
                'explanation' => 'Bias analysis failed - manual review required',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Analyze political bias in content
     */
    private function analyzePoliticalBias(string $content, array $metadata): float
    {
        $score = 0.0; // Center score (0 = far left, 50 = neutral, 100 = far right)
        $indicators = 0;

        // Political keyword analysis
        $politicalAnalysis = $this->analyzePoliticalKeywords($content);
        $score += $politicalAnalysis['bias_adjustment'];
        $indicators++;

        // Source framing analysis
        $framingAnalysis = $this->analyzeFraming($content);
        $score += $framingAnalysis['bias_adjustment'];
        $indicators++;

        // Topic-specific bias patterns
        $topicAnalysis = $this->analyzeTopicSpecificBias($content);
        $score += $topicAnalysis['bias_adjustment'];
        $indicators++;

        return $indicators > 0 ? $score / $indicators : 50.0;
    }

    /**
     * Analyze political keywords and terminology
     */
    private function analyzePoliticalKeywords(string $content): array
    {
        $leftLeaningTerms = [
            'progressive', 'social justice', 'inequality', 'diversity', 'inclusion',
            'climate change', 'environmental protection', 'worker rights', 'union',
            'public option', 'medicare for all', 'wealth tax', 'corporate greed',
            'systemic racism', 'gun control', 'reproductive rights'
        ];

        $rightLeaningTerms = [
            'conservative', 'traditional values', 'law and order', 'border security',
            'fiscal responsibility', 'free market', 'deregulation', 'tax cuts',
            'second amendment', 'religious freedom', 'pro-life', 'family values',
            'national security', 'immigration control', 'business friendly'
        ];

        $neutralTerms = [
            'bipartisan', 'compromise', 'moderate', 'balanced approach',
            'evidence-based', 'pragmatic', 'consensus', 'cross-party'
        ];

        $content = strtolower($content);
        
        $leftCount = 0;
        $rightCount = 0;
        $neutralCount = 0;

        foreach ($leftLeaningTerms as $term) {
            $leftCount += substr_count($content, $term);
        }

        foreach ($rightLeaningTerms as $term) {
            $rightCount += substr_count($content, $term);
        }

        foreach ($neutralTerms as $term) {
            $neutralCount += substr_count($content, $term);
        }

        $totalPolitical = $leftCount + $rightCount;
        
        if ($totalPolitical == 0) {
            $biasAdjustment = 50.0; // Neutral
        } else {
            // Calculate bias direction
            $rightRatio = $rightCount / $totalPolitical;
            $biasAdjustment = 50.0 + ($rightRatio - 0.5) * 60; // Scale to 0-100
        }

        // Neutral language reduces bias
        if ($neutralCount > 0) {
            $biasAdjustment += ($neutralCount * 2); // Move toward center
            $biasAdjustment = min(100, max(0, $biasAdjustment));
        }

        return [
            'bias_adjustment' => $biasAdjustment,
            'left_terms' => $leftCount,
            'right_terms' => $rightCount,
            'neutral_terms' => $neutralCount,
        ];
    }

    /**
     * Analyze framing and perspective
     */
    private function analyzeFraming(string $content): array
    {
        $biasIndicators = 0;
        $totalFrames = 0;

        // Negative framing patterns
        $negativeFraming = [
            '/\b(?:slammed|blasted|attacked|destroyed|demolished)\b/i',
            '/\b(?:controversial|divisive|radical|extreme)\b/i',
            '/\b(?:fails to|refuses to|ignores|dismisses)\b/i',
        ];

        // Positive framing patterns
        $positiveFraming = [
            '/\b(?:praised|celebrated|acclaimed|endorsed|supported)\b/i',
            '/\b(?:successful|effective|innovative|groundbreaking)\b/i',
            '/\b(?:promotes|advances|champions|delivers)\b/i',
        ];

        // Neutral framing patterns
        $neutralFraming = [
            '/\b(?:reported|stated|announced|said|indicated)\b/i',
            '/\b(?:according to|sources say|officials confirm)\b/i',
        ];

        foreach ($negativeFraming as $pattern) {
            $matches = preg_match_all($pattern, $content);
            $biasIndicators -= $matches;
            $totalFrames += $matches;
        }

        foreach ($positiveFraming as $pattern) {
            $matches = preg_match_all($pattern, $content);
            $biasIndicators += $matches;
            $totalFrames += $matches;
        }

        foreach ($neutralFraming as $pattern) {
            $matches = preg_match_all($pattern, $content);
            $totalFrames += $matches;
        }

        $framingBias = $totalFrames > 0 ? ($biasIndicators / $totalFrames) * 25 : 0;
        $biasAdjustment = 50.0 + $framingBias;

        return [
            'bias_adjustment' => max(0, min(100, $biasAdjustment)),
            'negative_frames' => abs(min(0, $biasIndicators)),
            'positive_frames' => max(0, $biasIndicators),
            'total_frames' => $totalFrames,
        ];
    }

    /**
     * Analyze topic-specific bias patterns
     */
    private function analyzeTopicSpecificBias(string $content): array
    {
        $topicBias = 0.0;
        $content = strtolower($content);

        // Economic topics
        if (preg_match('/\b(?:economy|economic|financial|market|business)\b/', $content)) {
            $economicBias = $this->analyzeEconomicBias($content);
            $topicBias += $economicBias;
        }

        // Social issues
        if (preg_match('/\b(?:social|society|rights|equality|discrimination)\b/', $content)) {
            $socialBias = $this->analyzeSocialBias($content);
            $topicBias += $socialBias;
        }

        // Immigration topics
        if (preg_match('/\b(?:immigration|immigrant|border|refugee)\b/', $content)) {
            $immigrationBias = $this->analyzeImmigrationBias($content);
            $topicBias += $immigrationBias;
        }

        return [
            'bias_adjustment' => 50.0 + $topicBias,
            'topic_bias_detected' => abs($topicBias) > 5,
        ];
    }

    /**
     * Analyze economic bias patterns
     */
    private function analyzeEconomicBias(string $content): float
    {
        $leftEconomicTerms = ['corporate greed', 'wealth inequality', 'worker exploitation', 'tax the rich'];
        $rightEconomicTerms = ['job creators', 'business friendly', 'economic growth', 'free market'];

        $leftCount = 0;
        $rightCount = 0;

        foreach ($leftEconomicTerms as $term) {
            if (stripos($content, $term) !== false) $leftCount++;
        }

        foreach ($rightEconomicTerms as $term) {
            if (stripos($content, $term) !== false) $rightCount++;
        }

        $total = $leftCount + $rightCount;
        return $total > 0 ? (($rightCount - $leftCount) / $total) * 15 : 0;
    }

    /**
     * Analyze social bias patterns
     */
    private function analyzeSocialBias(string $content): float
    {
        $leftSocialTerms = ['systemic racism', 'social justice', 'marginalized communities', 'privilege'];
        $rightSocialTerms = ['traditional values', 'merit-based', 'personal responsibility', 'individual rights'];

        $leftCount = 0;
        $rightCount = 0;

        foreach ($leftSocialTerms as $term) {
            if (stripos($content, $term) !== false) $leftCount++;
        }

        foreach ($rightSocialTerms as $term) {
            if (stripos($content, $term) !== false) $rightCount++;
        }

        $total = $leftCount + $rightCount;
        return $total > 0 ? (($rightCount - $leftCount) / $total) * 15 : 0;
    }

    /**
     * Analyze immigration bias patterns
     */
    private function analyzeImmigrationBias(string $content): float
    {
        $leftImmigrationTerms = ['dreamers', 'asylum seekers', 'undocumented workers', 'family separation'];
        $rightImmigrationTerms = ['illegal aliens', 'border security', 'immigration enforcement', 'national sovereignty'];

        $leftCount = 0;
        $rightCount = 0;

        foreach ($leftImmigrationTerms as $term) {
            if (stripos($content, $term) !== false) $leftCount++;
        }

        foreach ($rightImmigrationTerms as $term) {
            if (stripos($content, $term) !== false) $rightCount++;
        }

        $total = $leftCount + $rightCount;
        return $total > 0 ? (($rightCount - $leftCount) / $total) * 20 : 0;
    }

    /**
     * Analyze emotional bias and loaded language
     */
    private function analyzeEmotionalBias(string $content): float
    {
        $emotionalScore = 0.0;
        $factors = 0;

        // 1. Analyze emotional language intensity
        $intensityScore = $this->analyzeEmotionalIntensity($content);
        $emotionalScore += $intensityScore;
        $factors++;

        // 2. Analyze loaded/charged language
        $loadedLanguageScore = $this->analyzeLoadedLanguage($content);
        $emotionalScore += $loadedLanguageScore;
        $factors++;

        // 3. Analyze sentiment extremity
        $sentimentScore = $this->analyzeSentimentExtremity($content);
        $emotionalScore += $sentimentScore;
        $factors++;

        return $factors > 0 ? $emotionalScore / $factors : 50.0;
    }

    /**
     * Analyze emotional intensity in language
     */
    private function analyzeEmotionalIntensity(string $content): float
    {
        $highIntensityWords = [
            'outrageous', 'shocking', 'devastating', 'horrifying', 'disgusting',
            'brilliant', 'amazing', 'incredible', 'fantastic', 'terrible',
            'awful', 'horrible', 'wonderful', 'spectacular', 'disastrous'
        ];

        $moderateIntensityWords = [
            'concerning', 'worrying', 'impressive', 'notable', 'significant',
            'important', 'interesting', 'surprising', 'unusual', 'remarkable'
        ];

        $content = strtolower($content);
        $wordCount = str_word_count($content);
        
        $highIntensity = 0;
        $moderateIntensity = 0;

        foreach ($highIntensityWords as $word) {
            $highIntensity += substr_count($content, $word);
        }

        foreach ($moderateIntensityWords as $word) {
            $moderateIntensity += substr_count($content, $word);
        }

        $intensityRatio = $wordCount > 0 ? (($highIntensity * 2 + $moderateIntensity) / $wordCount) * 100 : 0;

        // Convert to bias score (higher intensity = higher bias)
        if ($intensityRatio > 3) {
            return 80.0;
        } elseif ($intensityRatio > 2) {
            return 65.0;
        } elseif ($intensityRatio > 1) {
            return 55.0;
        } else {
            return 35.0;
        }
    }

    /**
     * Analyze loaded/charged language
     */
    private function analyzeLoadedLanguage(string $content): float
    {
        $loadedTerms = [
            // Politically charged
            'extremist', 'radical', 'fanatic', 'zealot', 'activist',
            'regime', 'propaganda', 'brainwashing', 'indoctrination',
            
            // Emotionally charged
            'destroy', 'devastate', 'obliterate', 'annihilate',
            'savior', 'hero', 'villain', 'enemy', 'threat',
            
            // Divisive language
            'us vs them', 'enemy of the people', 'fake news',
            'conspiracy', 'cover-up', 'scandal'
        ];

        $content = strtolower($content);
        $wordCount = str_word_count($content);
        
        $loadedCount = 0;
        foreach ($loadedTerms as $term) {
            $loadedCount += substr_count($content, $term);
        }

        $loadedRatio = $wordCount > 0 ? ($loadedCount / $wordCount) * 100 : 0;

        if ($loadedRatio > 2) {
            return 90.0;
        } elseif ($loadedRatio > 1) {
            return 70.0;
        } elseif ($loadedRatio > 0.5) {
            return 60.0;
        } else {
            return 30.0;
        }
    }

    /**
     * Analyze sentiment extremity
     */
    private function analyzeSentimentExtremity(string $content): float
    {
        // Simple sentiment analysis using positive/negative word lists
        $positiveWords = [
            'good', 'great', 'excellent', 'wonderful', 'fantastic', 'amazing',
            'success', 'victory', 'triumph', 'achievement', 'progress'
        ];

        $negativeWords = [
            'bad', 'terrible', 'awful', 'horrible', 'disgusting', 'failure',
            'disaster', 'catastrophe', 'crisis', 'problem', 'issue'
        ];

        $content = strtolower($content);
        $wordCount = str_word_count($content);

        $positiveCount = 0;
        $negativeCount = 0;

        foreach ($positiveWords as $word) {
            $positiveCount += substr_count($content, $word);
        }

        foreach ($negativeWords as $word) {
            $negativeCount += substr_count($content, $word);
        }

        $sentimentBalance = abs($positiveCount - $negativeCount);
        $sentimentTotal = $positiveCount + $negativeCount;

        if ($sentimentTotal == 0) {
            return 30.0; // Neutral sentiment
        }

        $extremityRatio = $sentimentBalance / $sentimentTotal;

        // Higher extremity indicates more bias
        if ($extremityRatio > 0.8) {
            return 85.0;
        } elseif ($extremityRatio > 0.6) {
            return 70.0;
        } elseif ($extremityRatio > 0.4) {
            return 55.0;
        } else {
            return 35.0;
        }
    }

    /**
     * Analyze factual reporting quality
     */
    private function analyzeFactualReporting(string $content): float
    {
        $score = 50.0; // Base score
        $factors = 0;

        // 1. Check for opinion vs fact markers
        $factualityScore = $this->analyzeFactualityMarkers($content);
        $score += $factualityScore;
        $factors++;

        // 2. Check for hedging language (uncertainty indicators)
        $hedgingScore = $this->analyzeHedgingLanguage($content);
        $score += $hedgingScore;
        $factors++;

        // 3. Check for attribution and sourcing
        $attributionScore = $this->analyzeAttribution($content);
        $score += $attributionScore;
        $factors++;

        return $factors > 0 ? $score / $factors : 50.0;
    }

    /**
     * Analyze factuality markers
     */
    private function analyzeFactualityMarkers(string $content): float
    {
        $factualMarkers = [
            'data shows', 'research indicates', 'studies reveal', 'according to',
            'statistics show', 'evidence suggests', 'reports confirm', 'documented'
        ];

        $opinionMarkers = [
            'i believe', 'in my opinion', 'i think', 'it seems to me',
            'arguably', 'presumably', 'supposedly', 'allegedly'
        ];

        $content = strtolower($content);
        
        $factualCount = 0;
        $opinionCount = 0;

        foreach ($factualMarkers as $marker) {
            $factualCount += substr_count($content, $marker);
        }

        foreach ($opinionMarkers as $marker) {
            $opinionCount += substr_count($content, $marker);
        }

        $total = $factualCount + $opinionCount;
        
        if ($total == 0) {
            return 50.0; // Neutral
        }

        $factualRatio = $factualCount / $total;
        return $factualRatio * 100;
    }

    /**
     * Analyze hedging language
     */
    private function analyzeHedgingLanguage(string $content): float
    {
        $hedgingTerms = [
            'might', 'could', 'may', 'perhaps', 'possibly', 'likely',
            'appears to', 'seems to', 'suggests', 'indicates', 'implies'
        ];

        $certaintyTerms = [
            'definitely', 'certainly', 'absolutely', 'without doubt',
            'clearly', 'obviously', 'undoubtedly', 'proven'
        ];

        $content = strtolower($content);
        
        $hedgingCount = 0;
        $certaintyCount = 0;

        foreach ($hedgingTerms as $term) {
            $hedgingCount += substr_count($content, $term);
        }

        foreach ($certaintyTerms as $term) {
            $certaintyCount += substr_count($content, $term);
        }

        $total = $hedgingCount + $certaintyCount;
        
        if ($total == 0) {
            return 50.0;
        }

        // Appropriate hedging indicates good factual reporting
        $hedgingRatio = $hedgingCount / $total;
        
        if ($hedgingRatio >= 0.3 && $hedgingRatio <= 0.7) {
            return 80.0; // Balanced use of hedging
        } elseif ($hedgingRatio < 0.3) {
            return 40.0; // Too certain/dogmatic
        } else {
            return 45.0; // Too uncertain
        }
    }

    /**
     * Analyze attribution patterns
     */
    private function analyzeAttribution(string $content): float
    {
        $attributionPatterns = [
            '/(?:"[^"]*")/s', // Quoted text
            '/\b(?:according to|said|stated|reported|claimed|announced)\b/i',
            '/\b(?:spokesperson|official|expert|analyst|researcher)\b/i',
        ];

        $matches = 0;
        foreach ($attributionPatterns as $pattern) {
            $matches += preg_match_all($pattern, $content);
        }

        $wordCount = str_word_count($content);
        $attributionDensity = $wordCount > 0 ? ($matches / $wordCount) * 100 : 0;

        if ($attributionDensity >= 2) {
            return 90.0;
        } elseif ($attributionDensity >= 1) {
            return 75.0;
        } elseif ($attributionDensity >= 0.5) {
            return 60.0;
        } else {
            return 35.0;
        }
    }

    /**
     * Calculate neutrality score
     */
    private function calculateNeutralityScore(array $analysis): float
    {
        // Higher neutrality = lower bias
        $politicalBias = abs($analysis['political_bias_score'] - 50); // Distance from center
        $emotionalBias = $analysis['emotional_bias_score'];
        
        $neutralityScore = 100 - ($politicalBias + $emotionalBias) / 2;
        
        // Factual reporting boosts neutrality
        $neutralityScore += ($analysis['factual_reporting_score'] - 50) * 0.3;
        
        return max(0, min(100, $neutralityScore));
    }

    /**
     * Determine political leaning
     */
    private function determinePoliticalLeaning(string $content, array $analysis): string
    {
        $politicalScore = $analysis['political_bias_score'];
        
        if ($politicalScore >= 70) {
            return 'right-leaning';
        } elseif ($politicalScore >= 55) {
            return 'center-right';
        } elseif ($politicalScore >= 45) {
            return 'neutral';
        } elseif ($politicalScore >= 30) {
            return 'center-left';
        } else {
            return 'left-leaning';
        }
    }

    /**
     * Classify bias level
     */
    private function classifyBiasLevel(array $analysis): string
    {
        $overallBias = ($analysis['political_bias_score'] + $analysis['emotional_bias_score']) / 2;
        $neutrality = $analysis['neutrality_score'];
        
        if ($neutrality >= 80) {
            return 'minimal';
        } elseif ($neutrality >= 60) {
            return 'slight';
        } elseif ($neutrality >= 40) {
            return 'moderate';
        } elseif ($neutrality >= 20) {
            return 'significant';
        } else {
            return 'extreme';
        }
    }

    /**
     * Detect specific bias patterns
     */
    private function detectBiasPatterns(string $content): array
    {
        $patterns = [];
        
        // Strawman arguments
        if (preg_match('/\b(?:critics say|opponents claim|some argue)\b.*\b(?:but|however|actually)\b/i', $content)) {
            $patterns[] = 'Potential strawman argument detected';
        }
        
        // False dichotomy
        if (preg_match('/\b(?:either|only two|must choose|no alternative)\b/i', $content)) {
            $patterns[] = 'Potential false dichotomy';
        }
        
        // Loaded questions
        if (preg_match('/\b(?:why do|how can|when will).*(?:still|continue to|refuse to)\b/i', $content)) {
            $patterns[] = 'Loaded question structure';
        }
        
        // Cherry-picking language
        if (preg_match('/\b(?:conveniently ignores|fails to mention|overlooks)\b/i', $content)) {
            $patterns[] = 'Potential selective reporting';
        }
        
        return $patterns;
    }

    /**
     * Analyze language characteristics
     */
    private function analyzeLanguageCharacteristics(string $content): array
    {
        return [
            'average_sentence_length' => $this->calculateAverageSentenceLength($content),
            'complex_words_ratio' => $this->calculateComplexWordsRatio($content),
            'emotional_language_density' => $this->calculateEmotionalLanguageDensity($content),
            'certainty_language_ratio' => $this->calculateCertaintyLanguageRatio($content),
        ];
    }

    /**
     * Calculate average sentence length
     */
    private function calculateAverageSentenceLength(string $content): float
    {
        $sentences = preg_split('/[.!?]+/', $content);
        $sentences = array_filter($sentences, fn($s) => trim($s) !== '');
        
        if (empty($sentences)) {
            return 0;
        }
        
        $totalWords = 0;
        foreach ($sentences as $sentence) {
            $totalWords += str_word_count($sentence);
        }
        
        return $totalWords / count($sentences);
    }

    /**
     * Calculate complex words ratio
     */
    private function calculateComplexWordsRatio(string $content): float
    {
        $words = str_word_count(strtolower($content), 1);
        $complexWords = 0;
        
        foreach ($words as $word) {
            if (strlen($word) > 6 || $this->countSyllablesInWord($word) > 2) {
                $complexWords++;
            }
        }
        
        return count($words) > 0 ? ($complexWords / count($words)) * 100 : 0;
    }

    /**
     * Calculate emotional language density
     */
    private function calculateEmotionalLanguageDensity(string $content): float
    {
        $emotionalWords = [
            'love', 'hate', 'fear', 'anger', 'joy', 'sadness', 'disgust',
            'outrage', 'fury', 'devastation', 'elation', 'horror', 'delight'
        ];
        
        $content = strtolower($content);
        $emotionalCount = 0;
        
        foreach ($emotionalWords as $word) {
            $emotionalCount += substr_count($content, $word);
        }
        
        $totalWords = str_word_count($content);
        return $totalWords > 0 ? ($emotionalCount / $totalWords) * 100 : 0;
    }

    /**
     * Calculate certainty language ratio
     */
    private function calculateCertaintyLanguageRatio(string $content): float
    {
        $certaintyWords = ['definitely', 'certainly', 'absolutely', 'without doubt', 'clearly', 'obviously'];
        $uncertaintyWords = ['might', 'could', 'may', 'perhaps', 'possibly', 'likely'];
        
        $content = strtolower($content);
        $certaintyCount = 0;
        $uncertaintyCount = 0;
        
        foreach ($certaintyWords as $word) {
            $certaintyCount += substr_count($content, $word);
        }
        
        foreach ($uncertaintyWords as $word) {
            $uncertaintyCount += substr_count($content, $word);
        }
        
        $total = $certaintyCount + $uncertaintyCount;
        return $total > 0 ? ($certaintyCount / $total) * 100 : 50;
    }

    /**
     * Count syllables in a word
     */
    private function countSyllablesInWord(string $word): int
    {
        $word = strtolower($word);
        $word = preg_replace('/[^a-z]/', '', $word);
        
        if (strlen($word) <= 3) {
            return 1;
        }

        $word = preg_replace('/(?:[aeiou]){2,}/', 'a', $word);
        $word = preg_replace('/^[^aeiou]*[aeiou]/', 'a', $word);
        
        $syllables = preg_match_all('/[aeiou]/', $word);
        
        if (preg_match('/[^aeiou]e$/', $word)) {
            $syllables--;
        }

        return max(1, $syllables);
    }

    /**
     * Calculate confidence metrics
     */
    private function calculateConfidenceMetrics(string $content, array $analysis): array
    {
        $wordCount = str_word_count($content);
        
        return [
            'content_length_adequacy' => min(100, ($wordCount / 200) * 100), // Adequate at 200+ words
            'analysis_certainty' => $this->calculateAnalysisCertainty($analysis),
            'pattern_consistency' => $this->calculatePatternConsistency($content),
            'overall_confidence' => $this->calculateOverallConfidence($content, $analysis),
        ];
    }

    /**
     * Calculate analysis certainty
     */
    private function calculateAnalysisCertainty(array $analysis): float
    {
        // Higher confidence when bias indicators are clear
        $politicalCertainty = abs($analysis['political_bias_score'] - 50) * 2; // 0-100
        $emotionalCertainty = $analysis['emotional_bias_score'];
        
        return ($politicalCertainty + $emotionalCertainty) / 2;
    }

    /**
     * Calculate pattern consistency
     */
    private function calculatePatternConsistency(string $content): float
    {
        // Check if bias patterns are consistent throughout the content
        $paragraphs = explode("\n\n", $content);
        if (count($paragraphs) < 2) {
            return 70.0; // Default for short content
        }
        
        $biasScores = [];
        foreach ($paragraphs as $paragraph) {
            if (trim($paragraph) !== '') {
                $paragraphAnalysis = $this->analyzePoliticalBias($paragraph, []);
                $biasScores[] = $paragraphAnalysis;
            }
        }
        
        if (count($biasScores) < 2) {
            return 70.0;
        }
        
        // Calculate variance in bias scores
        $mean = array_sum($biasScores) / count($biasScores);
        $variance = array_sum(array_map(fn($x) => pow($x - $mean, 2), $biasScores)) / count($biasScores);
        $stdDev = sqrt($variance);
        
        // Lower standard deviation = higher consistency
        return max(0, 100 - $stdDev);
    }

    /**
     * Calculate overall confidence
     */
    private function calculateOverallConfidence(string $content, array $analysis): float
    {
        $metrics = $analysis['confidence_metrics'] ?? [];
        
        $factors = [
            ($metrics['content_length_adequacy'] ?? 50.0) * 0.3,
            ($metrics['analysis_certainty'] ?? 50.0) * 0.4,
            ($metrics['pattern_consistency'] ?? 50.0) * 0.3,
        ];
        
        return array_sum($factors);
    }

    /**
     * Generate bias explanation
     */
    private function generateBiasExplanation(array $analysis): string
    {
        $explanation = [];
        
        // Political bias explanation
        if ($analysis['political_leaning'] !== 'neutral') {
            $explanation[] = "Content shows {$analysis['political_leaning']} perspective";
        }
        
        // Emotional bias explanation
        if ($analysis['emotional_bias_score'] > 60) {
            $explanation[] = "high emotional language intensity";
        } elseif ($analysis['emotional_bias_score'] > 40) {
            $explanation[] = "moderate emotional language";
        } else {
            $explanation[] = "neutral emotional tone";
        }
        
        // Factual reporting explanation
        if ($analysis['factual_reporting_score'] < 40) {
            $explanation[] = "limited factual attribution";
        } elseif ($analysis['factual_reporting_score'] > 70) {
            $explanation[] = "good factual attribution";
        }
        
        // Detected patterns
        if (!empty($analysis['detected_patterns'])) {
            $explanation[] = "bias patterns detected: " . implode(', ', $analysis['detected_patterns']);
        }
        
        return !empty($explanation) ? 
            ucfirst(implode(', ', $explanation)) . '.' : 
            'Bias analysis completed with neutral findings.';
    }

    /**
     * Health check for the service
     */
    public function healthCheck(): array
    {
        try {
            // Test with sample content
            $testContent = "This is a neutral test article. According to research, facts are important. The data shows objective reporting.";
            $result = $this->analyzeBias($testContent);
            
            return [
                'status' => isset($result['political_bias_score']) ? 'healthy' : 'degraded',
                'test_neutrality_score' => $result['neutrality_score'] ?? null,
            ];
        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }
}