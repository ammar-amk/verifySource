<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;

class ContentQualityService
{
    /**
     * Analyze content quality comprehensively
     */
    public function analyzeContent(string $content, array $metadata = []): array
    {
        try {
            $analysis = [
                'overall_quality_score' => 0.0,
                'readability_score' => 0.0,
                'fact_density_score' => 0.0,
                'citation_score' => 0.0,
                'structure_score' => 0.0,
                'language_quality_score' => 0.0,
                'quality_indicators' => [],
                'quality_detractors' => [],
                'recommendations' => [],
            ];

            // 1. Analyze readability
            $analysis['readability_score'] = $this->analyzeReadability($content);

            // 2. Analyze fact density and informational content
            $analysis['fact_density_score'] = $this->analyzeFactDensity($content, $metadata);

            // 3. Analyze citations and references
            $analysis['citation_score'] = $this->analyzeCitations($content);

            // 4. Analyze content structure
            $analysis['structure_score'] = $this->analyzeStructure($content, $metadata);

            // 5. Analyze language quality
            $analysis['language_quality_score'] = $this->analyzeLanguageQuality($content);

            // 6. Collect quality indicators and detractors
            $analysis['quality_indicators'] = $this->collectQualityIndicators($content, $metadata, $analysis);
            $analysis['quality_detractors'] = $this->collectQualityDetractors($content, $metadata, $analysis);

            // 7. Generate recommendations
            $analysis['recommendations'] = $this->generateQualityRecommendations($analysis);

            // 8. Calculate overall quality score
            $analysis['overall_quality_score'] = $this->calculateOverallQualityScore($analysis);

            return $analysis;

        } catch (Exception $e) {
            Log::error('Content quality analysis failed', [
                'error' => $e->getMessage(),
                'content_length' => strlen($content),
            ]);

            // Return default analysis on error
            return [
                'overall_quality_score' => 50.0,
                'readability_score' => 50.0,
                'fact_density_score' => 50.0,
                'citation_score' => 50.0,
                'structure_score' => 50.0,
                'language_quality_score' => 50.0,
                'quality_indicators' => [],
                'quality_detractors' => ['Analysis failed'],
                'recommendations' => ['Manual review required'],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Analyze readability using multiple metrics
     */
    private function analyzeReadability(string $content): float
    {
        if (empty($content)) {
            return 0.0;
        }

        $score = 0.0;
        $factors = 0;

        // 1. Flesch Reading Ease approximation
        $fleschScore = $this->calculateFleschReadingEase($content);
        if ($fleschScore !== null) {
            $score += $fleschScore;
            $factors++;
        }

        // 2. Average sentence length
        $avgSentenceLength = $this->calculateAverageSentenceLength($content);
        if ($avgSentenceLength > 0) {
            // Optimal sentence length is around 15-20 words
            if ($avgSentenceLength >= 10 && $avgSentenceLength <= 25) {
                $score += 80.0;
            } elseif ($avgSentenceLength >= 8 && $avgSentenceLength <= 30) {
                $score += 65.0;
            } else {
                $score += 40.0;
            }
            $factors++;
        }

        // 3. Paragraph structure
        $paragraphScore = $this->analyzeParagraphStructure($content);
        $score += $paragraphScore;
        $factors++;

        return $factors > 0 ? $score / $factors : 50.0;
    }

    /**
     * Calculate Flesch Reading Ease score
     */
    private function calculateFleschReadingEase(string $content): ?float
    {
        $sentences = $this->countSentences($content);
        $words = $this->countWords($content);
        $syllables = $this->countSyllables($content);

        if ($sentences == 0 || $words == 0) {
            return null;
        }

        $avgSentenceLength = $words / $sentences;
        $avgSyllablesPerWord = $syllables / $words;

        $fleschScore = 206.835 - (1.015 * $avgSentenceLength) - (84.6 * $avgSyllablesPerWord);

        // Convert to 0-100 scale where higher is better
        return max(0, min(100, $fleschScore));
    }

    /**
     * Count sentences in text
     */
    private function countSentences(string $content): int
    {
        $content = preg_replace('/\s+/', ' ', trim($content));

        return preg_match_all('/[.!?]+/', $content);
    }

    /**
     * Count words in text
     */
    private function countWords(string $content): int
    {
        return str_word_count($content);
    }

    /**
     * Estimate syllable count
     */
    private function countSyllables(string $content): int
    {
        $words = str_word_count(strtolower($content), 1);
        $syllables = 0;

        foreach ($words as $word) {
            $syllables += $this->countSyllablesInWord($word);
        }

        return $syllables;
    }

    /**
     * Count syllables in a single word
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
     * Calculate average sentence length
     */
    private function calculateAverageSentenceLength(string $content): float
    {
        $sentences = $this->countSentences($content);
        $words = $this->countWords($content);

        return $sentences > 0 ? $words / $sentences : 0;
    }

    /**
     * Analyze paragraph structure
     */
    private function analyzeParagraphStructure(string $content): float
    {
        $paragraphs = explode("\n\n", $content);
        $paragraphs = array_filter($paragraphs, fn ($p) => trim($p) !== '');

        if (count($paragraphs) < 2) {
            return 30.0; // Poor structure
        }

        $avgParagraphLength = array_sum(array_map('str_word_count', $paragraphs)) / count($paragraphs);

        // Optimal paragraph length is around 50-150 words
        if ($avgParagraphLength >= 40 && $avgParagraphLength <= 200) {
            return 85.0;
        } elseif ($avgParagraphLength >= 20 && $avgParagraphLength <= 300) {
            return 70.0;
        } else {
            return 50.0;
        }
    }

    /**
     * Analyze fact density and informational content
     */
    private function analyzeFactDensity(string $content, array $metadata): float
    {
        $score = 0.0;
        $factors = 0;

        // 1. Check for dates and temporal references
        $dateScore = $this->analyzeTemporalReferences($content);
        $score += $dateScore;
        $factors++;

        // 2. Check for numerical data and statistics
        $numericalScore = $this->analyzeNumericalContent($content);
        $score += $numericalScore;
        $factors++;

        // 3. Check for proper nouns and specific references
        $specificityScore = $this->analyzeContentSpecificity($content);
        $score += $specificityScore;
        $factors++;

        // 4. Check for quotes and attribution
        $attributionScore = $this->analyzeAttribution($content);
        $score += $attributionScore;
        $factors++;

        return $factors > 0 ? $score / $factors : 50.0;
    }

    /**
     * Analyze temporal references in content
     */
    private function analyzeTemporalReferences(string $content): float
    {
        $datePatterns = [
            '/\b\d{1,2}\/\d{1,2}\/\d{2,4}\b/',          // Date formats
            '/\b\d{4}-\d{2}-\d{2}\b/',                  // ISO date format
            '/\b(?:January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{1,2},?\s+\d{4}\b/i',
            '/\b(?:Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday)\b/i',
            '/\b(?:yesterday|today|tomorrow|last\s+week|next\s+month|this\s+year)\b/i',
        ];

        $matches = 0;
        foreach ($datePatterns as $pattern) {
            $matches += preg_match_all($pattern, $content);
        }

        $wordCount = $this->countWords($content);
        $datesDensity = $wordCount > 0 ? ($matches / $wordCount) * 1000 : 0;

        // Score based on appropriate density of temporal references
        if ($datesDensity >= 2) {
            return 90.0;
        } elseif ($datesDensity >= 1) {
            return 75.0;
        } elseif ($datesDensity >= 0.5) {
            return 60.0;
        } else {
            return 40.0;
        }
    }

    /**
     * Analyze numerical content and statistics
     */
    private function analyzeNumericalContent(string $content): float
    {
        $numericalPatterns = [
            '/\b\d+(?:\.\d+)?%\b/',                     // Percentages
            '/\$\d+(?:,\d{3})*(?:\.\d{2})?\b/',         // Monetary amounts
            '/\b\d+(?:,\d{3})*\b/',                     // Large numbers with commas
            '/\b(?:million|billion|trillion|thousand)\b/i',
        ];

        $matches = 0;
        foreach ($numericalPatterns as $pattern) {
            $matches += preg_match_all($pattern, $content);
        }

        $wordCount = $this->countWords($content);
        $numericalDensity = $wordCount > 0 ? ($matches / $wordCount) * 100 : 0;

        if ($numericalDensity >= 3) {
            return 95.0;
        } elseif ($numericalDensity >= 1.5) {
            return 80.0;
        } elseif ($numericalDensity >= 0.5) {
            return 65.0;
        } else {
            return 45.0;
        }
    }

    /**
     * Analyze content specificity
     */
    private function analyzeContentSpecificity(string $content): float
    {
        // Count proper nouns (capitalized words that aren't sentence starters)
        $words = explode(' ', $content);
        $properNouns = 0;
        $totalWords = 0;

        for ($i = 1; $i < count($words); $i++) {
            $word = trim($words[$i], '.,!?;:');
            if (ctype_alpha($word) && ctype_upper($word[0])) {
                $properNouns++;
            }
            if (ctype_alpha($word)) {
                $totalWords++;
            }
        }

        $properNounDensity = $totalWords > 0 ? ($properNouns / $totalWords) * 100 : 0;

        if ($properNounDensity >= 8) {
            return 90.0;
        } elseif ($properNounDensity >= 5) {
            return 75.0;
        } elseif ($properNounDensity >= 2) {
            return 60.0;
        } else {
            return 40.0;
        }
    }

    /**
     * Analyze attribution and quotes
     */
    private function analyzeAttribution(string $content): float
    {
        $attributionPatterns = [
            '/(?:"[^"]*")/s',                           // Quoted text
            '/(?:\'[^\']*\')/s',                        // Single quoted text
            '/\b(?:according to|said|stated|reported|claimed|announced)\b/i',
            '/\b(?:spokesperson|official|expert|analyst|researcher)\b/i',
        ];

        $matches = 0;
        foreach ($attributionPatterns as $pattern) {
            $matches += preg_match_all($pattern, $content);
        }

        $wordCount = $this->countWords($content);
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
     * Analyze citations and references
     */
    private function analyzeCitations(string $content): float
    {
        $citationPatterns = [
            '/\bhttps?:\/\/[^\s]+/i',                   // URLs
            '/\b(?:study|research|report|survey|analysis)\s+(?:by|from|published|conducted)\b/i',
            '/\b(?:university|institute|center|foundation)\b/i',
            '/\[\d+\]/',                                // Reference numbers
            '/\([^)]*\d{4}[^)]*\)/',                   // Publication years in parentheses
        ];

        $matches = 0;
        foreach ($citationPatterns as $pattern) {
            $matches += preg_match_all($pattern, $content);
        }

        $wordCount = $this->countWords($content);
        $citationDensity = $wordCount > 0 ? ($matches / $wordCount) * 100 : 0;

        if ($citationDensity >= 1.5) {
            return 95.0;
        } elseif ($citationDensity >= 0.8) {
            return 80.0;
        } elseif ($citationDensity >= 0.3) {
            return 65.0;
        } else {
            return 30.0;
        }
    }

    /**
     * Analyze content structure
     */
    private function analyzeStructure(string $content, array $metadata): float
    {
        $score = 0.0;
        $factors = 0;

        // 1. Check for proper headline structure
        if (isset($metadata['title']) && ! empty($metadata['title'])) {
            $headlineScore = $this->analyzeHeadlineQuality($metadata['title']);
            $score += $headlineScore;
            $factors++;
        }

        // 2. Check for subheadings and organization
        $organizationScore = $this->analyzeContentOrganization($content);
        $score += $organizationScore;
        $factors++;

        // 3. Check content length appropriateness
        $lengthScore = $this->analyzeContentLength($content);
        $score += $lengthScore;
        $factors++;

        return $factors > 0 ? $score / $factors : 50.0;
    }

    /**
     * Analyze headline quality
     */
    private function analyzeHeadlineQuality(string $title): float
    {
        $score = 50.0;

        $wordCount = str_word_count($title);

        // Optimal headline length is 6-12 words
        if ($wordCount >= 6 && $wordCount <= 12) {
            $score += 30.0;
        } elseif ($wordCount >= 4 && $wordCount <= 15) {
            $score += 15.0;
        }

        // Check for clickbait patterns (negative)
        $clickbaitPatterns = [
            '/you won\'t believe/i',
            '/shocking/i',
            '/\d+\s+(?:reasons?|ways?|things?|secrets?)/i',
            '/doctors hate/i',
            '/one weird trick/i',
            '/what happened next/i',
        ];

        foreach ($clickbaitPatterns as $pattern) {
            if (preg_match($pattern, $title)) {
                $score -= 40.0;
                break;
            }
        }

        // Check for question headlines (can be good for engagement)
        if (preg_match('/\?$/', $title)) {
            $score += 5.0;
        }

        return max(0.0, min(100.0, $score));
    }

    /**
     * Analyze content organization
     */
    private function analyzeContentOrganization(string $content): float
    {
        $score = 50.0;

        // Check for bullet points or numbered lists
        if (preg_match_all('/^\s*[\*\-\•]\s+/m', $content) > 0) {
            $score += 15.0;
        }

        if (preg_match_all('/^\s*\d+\.\s+/m', $content) > 0) {
            $score += 15.0;
        }

        // Check for section breaks or clear paragraph structure
        $paragraphs = explode("\n\n", $content);
        if (count($paragraphs) >= 3) {
            $score += 20.0;
        }

        return min(100.0, $score);
    }

    /**
     * Analyze content length appropriateness
     */
    private function analyzeContentLength(string $content): float
    {
        $wordCount = $this->countWords($content);

        // For news articles, 300-2000 words is typically appropriate
        if ($wordCount >= 300 && $wordCount <= 2000) {
            return 85.0;
        } elseif ($wordCount >= 150 && $wordCount <= 3000) {
            return 70.0;
        } elseif ($wordCount >= 100) {
            return 55.0;
        } else {
            return 25.0;
        }
    }

    /**
     * Analyze language quality
     */
    private function analyzeLanguageQuality(string $content): float
    {
        $score = 50.0;
        $factors = 0;

        // 1. Check for spelling/grammar patterns
        $grammarScore = $this->analyzeGrammarPatterns($content);
        $score += $grammarScore;
        $factors++;

        // 2. Check vocabulary diversity
        $vocabularyScore = $this->analyzeVocabularyDiversity($content);
        $score += $vocabularyScore;
        $factors++;

        return $factors > 0 ? $score / $factors : 50.0;
    }

    /**
     * Analyze grammar patterns
     */
    private function analyzeGrammarPatterns(string $content): float
    {
        $score = 70.0; // Assume good grammar by default

        // Check for common grammar issues
        $grammarIssues = [
            '/\bits\s+its\s/i',                        // its/it's confusion
            '/\byour\s+you\'re\s/i',                   // your/you're confusion
            '/\bthere\s+their\s+they\'re\s/i',         // there/their/they're
            '/\s{2,}/',                                // Multiple spaces
            '/[.!?]{2,}/',                             // Multiple punctuation
        ];

        $issues = 0;
        foreach ($grammarIssues as $pattern) {
            $issues += preg_match_all($pattern, $content);
        }

        $wordCount = $this->countWords($content);
        $issueRate = $wordCount > 0 ? ($issues / $wordCount) * 1000 : 0;

        if ($issueRate > 5) {
            $score -= 30.0;
        } elseif ($issueRate > 2) {
            $score -= 15.0;
        }

        return max(0.0, min(100.0, $score));
    }

    /**
     * Analyze vocabulary diversity
     */
    private function analyzeVocabularyDiversity(string $content): float
    {
        $words = str_word_count(strtolower($content), 1);
        $uniqueWords = array_unique($words);

        if (empty($words)) {
            return 0.0;
        }

        $diversityRatio = count($uniqueWords) / count($words);

        if ($diversityRatio >= 0.6) {
            return 90.0;
        } elseif ($diversityRatio >= 0.5) {
            return 75.0;
        } elseif ($diversityRatio >= 0.4) {
            return 60.0;
        } else {
            return 40.0;
        }
    }

    /**
     * Collect quality indicators
     */
    private function collectQualityIndicators(string $content, array $metadata, array $analysis): array
    {
        $indicators = [];

        if ($analysis['readability_score'] >= 75) {
            $indicators[] = 'Good readability and clarity';
        }

        if ($analysis['fact_density_score'] >= 75) {
            $indicators[] = 'Rich factual content';
        }

        if ($analysis['citation_score'] >= 70) {
            $indicators[] = 'Well-cited and referenced';
        }

        if ($analysis['structure_score'] >= 75) {
            $indicators[] = 'Well-structured content';
        }

        if ($analysis['language_quality_score'] >= 75) {
            $indicators[] = 'High language quality';
        }

        // Check for specific quality markers
        if (preg_match('/\b(?:according to|sources say|experts|research|study|data shows)\b/i', $content)) {
            $indicators[] = 'Uses authoritative sources';
        }

        if (preg_match('/\bhttps?:\/\/[^\s]+/i', $content)) {
            $indicators[] = 'Includes external links';
        }

        return $indicators;
    }

    /**
     * Collect quality detractors
     */
    private function collectQualityDetractors(string $content, array $metadata, array $analysis): array
    {
        $detractors = [];

        if ($analysis['readability_score'] < 40) {
            $detractors[] = 'Poor readability';
        }

        if ($analysis['fact_density_score'] < 40) {
            $detractors[] = 'Low factual content';
        }

        if ($analysis['citation_score'] < 30) {
            $detractors[] = 'Lacks citations and references';
        }

        if ($analysis['structure_score'] < 40) {
            $detractors[] = 'Poor content structure';
        }

        // Check for specific quality issues
        if (isset($metadata['title'])) {
            $clickbaitPatterns = [
                '/you won\'t believe/i',
                '/shocking/i',
                '/\d+\s+(?:reasons?|ways?|things?)/i',
            ];

            foreach ($clickbaitPatterns as $pattern) {
                if (preg_match($pattern, $metadata['title'])) {
                    $detractors[] = 'Clickbait headline';
                    break;
                }
            }
        }

        if ($this->countWords($content) < 150) {
            $detractors[] = 'Very short content';
        }

        if (preg_match_all('/[A-Z]{3,}/', $content) > 5) {
            $detractors[] = 'Excessive use of capital letters';
        }

        return $detractors;
    }

    /**
     * Generate quality recommendations
     */
    private function generateQualityRecommendations(array $analysis): array
    {
        $recommendations = [];

        if ($analysis['readability_score'] < 60) {
            $recommendations[] = 'Improve readability with shorter sentences and simpler language';
        }

        if ($analysis['fact_density_score'] < 60) {
            $recommendations[] = 'Add more specific facts, data, and concrete details';
        }

        if ($analysis['citation_score'] < 50) {
            $recommendations[] = 'Include more citations and references to sources';
        }

        if ($analysis['structure_score'] < 60) {
            $recommendations[] = 'Improve content organization with better paragraphing and structure';
        }

        return $recommendations;
    }

    /**
     * Calculate overall quality score
     */
    private function calculateOverallQualityScore(array $analysis): float
    {
        $weights = [
            'readability_score' => 0.25,
            'fact_density_score' => 0.25,
            'citation_score' => 0.20,
            'structure_score' => 0.15,
            'language_quality_score' => 0.15,
        ];

        $score = 0.0;
        foreach ($weights as $component => $weight) {
            $score += $analysis[$component] * $weight;
        }

        return max(0.0, min(100.0, $score));
    }

    /**
     * Health check for the service
     */
    public function healthCheck(): array
    {
        try {
            // Test with sample content
            $testContent = 'This is a test article with proper sentences. It includes some facts and numbers like 42% and $1,000. The content is structured well with good readability.';
            $result = $this->analyzeContent($testContent);

            return [
                'status' => isset($result['overall_quality_score']) ? 'healthy' : 'degraded',
                'test_score' => $result['overall_quality_score'] ?? null,
            ];
        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }
}
