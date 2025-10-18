<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Source;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use Exception;

class ContentProvenanceService
{
    protected array $config;
    
    public function __construct()
    {
        $this->config = config('verifysource.provenance');
    }
    
    /**
     * Analyze content provenance from search matches
     */
    public function analyzeContentProvenance(string $content, array $matches): array
    {
        $cacheKey = 'provenance:' . hash('sha256', $content . serialize($matches));
        
        // Check cache first
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }
        
        $analysis = [
            'confidence' => 0.0,
            'original_source' => null,
            'publication_timeline' => [],
            'propagation_pattern' => [],
            'content_variations' => [],
            'suspicious_patterns' => [],
            'evidence_summary' => '',
        ];
        
        try {
            // Step 1: Create publication timeline
            $timeline = $this->createPublicationTimeline($matches);
            $analysis['publication_timeline'] = $timeline;
            
            // Step 2: Identify potential original source
            $originalSource = $this->identifyOriginalSource($timeline, $content);
            $analysis['original_source'] = $originalSource;
            
            // Step 3: Analyze propagation patterns
            $propagationPattern = $this->analyzeContentPropagation($timeline);
            $analysis['propagation_pattern'] = $propagationPattern;
            
            // Step 4: Detect content variations
            $variations = $this->detectContentVariations($matches);
            $analysis['content_variations'] = $variations;
            
            // Step 5: Identify suspicious patterns
            $suspiciousPatterns = $this->detectSuspiciousPatterns($timeline, $propagationPattern);
            $analysis['suspicious_patterns'] = $suspiciousPatterns;
            
            // Step 6: Calculate overall confidence
            $confidence = $this->calculateProvenanceConfidence($analysis);
            $analysis['confidence'] = $confidence;
            
            // Step 7: Generate evidence summary
            $analysis['evidence_summary'] = $this->generateEvidenceSummary($analysis);
            
            // Cache for 1 hour
            Cache::put($cacheKey, $analysis, now()->addHour());
            
            return $analysis;
            
        } catch (Exception $e) {
            Log::error('Content provenance analysis failed', [
                'error' => $e->getMessage(),
                'content_length' => strlen($content),
                'matches_count' => count($matches),
            ]);
            
            $analysis['error'] = $e->getMessage();
            return $analysis;
        }
    }
    
    /**
     * Create a timeline of content publications
     */
    protected function createPublicationTimeline(array $matches): array
    {
        $timeline = [];
        
        foreach ($matches as $match) {
            if (empty($match['published_at']) || empty($match['source_id'])) {
                continue;
            }
            
            $publishedAt = is_numeric($match['published_at']) 
                ? Carbon::createFromTimestamp($match['published_at'])
                : Carbon::parse($match['published_at']);
                
            $timeline[] = [
                'article_id' => $match['id'],
                'source_id' => $match['source_id'],
                'source_name' => $match['source_name'] ?? 'Unknown',
                'title' => $match['title'],
                'url' => $match['url'],
                'published_at' => $publishedAt,
                'quality_score' => $match['quality_score'] ?? 0,
                'match_score' => $match['hybrid_score'] ?? $match['score'] ?? 0,
            ];
        }
        
        // Sort by publication date
        usort($timeline, fn($a, $b) => $a['published_at']->compare($b['published_at']));
        
        return $timeline;
    }
    
    /**
     * Identify the most likely original source
     */
    protected function identifyOriginalSource(array $timeline, string $content): ?array
    {
        if (empty($timeline)) {
            return null;
        }
        
        $candidates = [];
        $earliestDate = $timeline[0]['published_at'];
        $timeWindowHours = $this->config['minimum_publication_gap'];
        
        // Find all sources published within the time window of the earliest publication
        foreach ($timeline as $entry) {
            $timeDifference = $earliestDate->diffInHours($entry['published_at']);
            
            if ($timeDifference <= $timeWindowHours) {
                $candidates[] = $entry;
            } else {
                break; // Timeline is sorted, so we can stop here
            }
        }
        
        if (empty($candidates)) {
            return null;
        }
        
        // If only one candidate, it's likely the original
        if (count($candidates) === 1) {
            return array_merge($candidates[0], [
                'originality_confidence' => 0.9,
                'reasoning' => 'Single earliest publication found',
            ]);
        }
        
        // Multiple candidates - use additional criteria to determine original
        $scoredCandidates = [];
        
        foreach ($candidates as $candidate) {
            $score = $this->calculateOriginalityScore($candidate, $timeline);
            $scoredCandidates[] = array_merge($candidate, ['originality_score' => $score]);
        }
        
        // Sort by originality score
        usort($scoredCandidates, fn($a, $b) => $b['originality_score'] <=> $a['originality_score']);
        
        $topCandidate = $scoredCandidates[0];
        $confidenceGap = count($scoredCandidates) > 1 
            ? $topCandidate['originality_score'] - $scoredCandidates[1]['originality_score']
            : 1.0;
            
        return array_merge($topCandidate, [
            'originality_confidence' => min(0.95, $topCandidate['originality_score'] + ($confidenceGap * 0.2)),
            'reasoning' => $this->generateOriginalityReasoning($topCandidate, $candidates),
        ]);
    }
    
    /**
     * Calculate originality score for a candidate source
     */
    protected function calculateOriginalityScore(array $candidate, array $fullTimeline): float
    {
        $score = 0.0;
        
        // Base score for being among the earliest
        $score += 0.3;
        
        // Boost for higher quality score
        $qualityScore = $candidate['quality_score'] ?? 0;
        $score += ($qualityScore / 100) * 0.2;
        
        // Boost for higher content match score
        $matchScore = $candidate['match_score'] ?? 0;
        $score += $matchScore * 0.2;
        
        // Boost for authoritative sources (higher credibility)
        $source = Source::find($candidate['source_id']);
        if ($source && $source->credibility_score) {
            $score += $source->credibility_score * 0.2;
        }
        
        // Penalty if many other sources published very quickly after this one
        $rapidFollowers = 0;
        $candidateTime = $candidate['published_at'];
        
        foreach ($fullTimeline as $entry) {
            if ($entry['article_id'] === $candidate['article_id']) {
                continue;
            }
            
            $timeDiff = $candidateTime->diffInHours($entry['published_at']);
            if ($timeDiff <= 2 && $entry['published_at']->gt($candidateTime)) {
                $rapidFollowers++;
            }
        }
        
        // If too many rapid followers, might indicate this was copied quickly
        if ($rapidFollowers > 5) {
            $score -= 0.1;
        }
        
        return max(0.0, min(1.0, $score));
    }
    
    /**
     * Analyze how content propagated across sources
     */
    protected function analyzeContentPropagation(array $timeline): array
    {
        if (count($timeline) < 2) {
            return [
                'pattern_type' => 'single_source',
                'propagation_speed' => 0,
                'viral_coefficient' => 0,
                'clusters' => [],
                'suspicious_activity' => false,
            ];
        }
        
        $propagation = [
            'pattern_type' => 'unknown',
            'propagation_speed' => 0,
            'viral_coefficient' => 0,
            'clusters' => [],
            'suspicious_activity' => false,
        ];
        
        // Calculate propagation speed (articles per hour)
        $firstPublication = $timeline[0]['published_at'];
        $lastPublication = end($timeline)['published_at'];
        $totalHours = max(1, $firstPublication->diffInHours($lastPublication));
        $propagation['propagation_speed'] = count($timeline) / $totalHours;
        
        // Detect temporal clusters
        $clusters = $this->detectTemporalClusters($timeline);
        $propagation['clusters'] = $clusters;
        
        // Determine pattern type
        $propagation['pattern_type'] = $this->classifyPropagationPattern($timeline, $clusters);
        
        // Calculate viral coefficient (how quickly it spread)
        $propagation['viral_coefficient'] = $this->calculateViralCoefficient($timeline);
        
        // Detect suspicious activity
        $propagation['suspicious_activity'] = $this->detectSuspiciousPropagation($timeline, $clusters);
        
        return $propagation;
    }
    
    /**
     * Detect temporal clusters in publication timeline
     */
    protected function detectTemporalClusters(array $timeline): array
    {
        $clusters = [];
        $currentCluster = [];
        $clusterThresholdHours = 6; // Articles within 6 hours are considered a cluster
        
        foreach ($timeline as $i => $entry) {
            if (empty($currentCluster)) {
                $currentCluster = [$entry];
                continue;
            }
            
            $lastEntry = end($currentCluster);
            $timeDiff = $lastEntry['published_at']->diffInHours($entry['published_at']);
            
            if ($timeDiff <= $clusterThresholdHours) {
                $currentCluster[] = $entry;
            } else {
                // Save current cluster if it has multiple articles
                if (count($currentCluster) > 1) {
                    $clusters[] = [
                        'start_time' => $currentCluster[0]['published_at'],
                        'end_time' => end($currentCluster)['published_at'],
                        'article_count' => count($currentCluster),
                        'sources' => array_unique(array_column($currentCluster, 'source_name')),
                        'articles' => $currentCluster,
                    ];
                }
                
                // Start new cluster
                $currentCluster = [$entry];
            }
        }
        
        // Don't forget the last cluster
        if (count($currentCluster) > 1) {
            $clusters[] = [
                'start_time' => $currentCluster[0]['published_at'],
                'end_time' => end($currentCluster)['published_at'],
                'article_count' => count($currentCluster),
                'sources' => array_unique(array_column($currentCluster, 'source_name')),
                'articles' => $currentCluster,
            ];
        }
        
        return $clusters;
    }
    
    /**
     * Classify the type of propagation pattern
     */
    protected function classifyPropagationPattern(array $timeline, array $clusters): string
    {
        $totalArticles = count($timeline);
        $totalClusters = count($clusters);
        
        if ($totalArticles === 1) {
            return 'single_source';
        }
        
        if ($totalArticles <= 3) {
            return 'limited_propagation';
        }
        
        $firstDay = $timeline[0]['published_at']->startOfDay();
        $articlesFirstDay = count(array_filter($timeline, fn($entry) => 
            $entry['published_at']->startOfDay()->eq($firstDay)
        ));
        
        if ($articlesFirstDay > $totalArticles * 0.8) {
            return 'viral_burst';
        }
        
        if ($totalClusters > 0) {
            $largestCluster = max(array_column($clusters, 'article_count'));
            if ($largestCluster > $totalArticles * 0.6) {
                return 'clustered_propagation';
            }
        }
        
        $timeSpanDays = $timeline[0]['published_at']->diffInDays(end($timeline)['published_at']);
        if ($timeSpanDays > 7) {
            return 'gradual_spread';
        }
        
        return 'organic_propagation';
    }
    
    /**
     * Calculate viral coefficient
     */
    protected function calculateViralCoefficient(array $timeline): float
    {
        if (count($timeline) < 2) {
            return 0.0;
        }
        
        $firstPublication = $timeline[0]['published_at'];
        $hoursElapsed = 0;
        $coefficient = 0.0;
        
        for ($i = 1; $i < count($timeline); $i++) {
            $currentHours = $firstPublication->diffInHours($timeline[$i]['published_at']);
            if ($currentHours > $hoursElapsed) {
                $articlesInPeriod = $i + 1;
                $periodCoefficient = $articlesInPeriod / max(1, $currentHours);
                $coefficient = max($coefficient, $periodCoefficient);
                $hoursElapsed = $currentHours;
            }
        }
        
        return min(10.0, $coefficient); // Cap at 10 articles per hour
    }
    
    /**
     * Detect content variations across matches
     */
    protected function detectContentVariations(array $matches): array
    {
        $variations = [
            'title_variations' => [],
            'content_similarity_range' => [],
            'common_phrases' => [],
            'unique_elements' => [],
        ];
        
        // Analyze title variations
        $titles = array_column($matches, 'title');
        $variations['title_variations'] = $this->analyzeTitleVariations($titles);
        
        // Analyze content similarity range
        $scores = array_column($matches, 'hybrid_score');
        if (empty($scores)) {
            $scores = array_column($matches, 'score');
        }
        
        if (!empty($scores)) {
            $variations['content_similarity_range'] = [
                'min' => min($scores),
                'max' => max($scores),
                'average' => array_sum($scores) / count($scores),
                'std_dev' => $this->calculateStandardDeviation($scores),
            ];
        }
        
        return $variations;
    }
    
    /**
     * Analyze title variations to detect patterns
     */
    protected function analyzeTitleVariations(array $titles): array
    {
        $variations = [
            'total_unique_titles' => count(array_unique($titles)),
            'most_common_title' => '',
            'variation_patterns' => [],
        ];
        
        $titleCounts = array_count_values($titles);
        arsort($titleCounts);
        
        $variations['most_common_title'] = array_key_first($titleCounts);
        
        // Detect common patterns in variations
        $patterns = [];
        foreach (array_keys($titleCounts) as $title) {
            $cleanTitle = strtolower(preg_replace('/[^\w\s]/', '', $title));
            $words = explode(' ', $cleanTitle);
            
            foreach ($words as $word) {
                if (strlen($word) > 3) { // Only count significant words
                    if (!isset($patterns[$word])) {
                        $patterns[$word] = 0;
                    }
                    $patterns[$word]++;
                }
            }
        }
        
        // Keep only words that appear in multiple titles
        $variations['variation_patterns'] = array_filter($patterns, fn($count) => $count > 1);
        arsort($variations['variation_patterns']);
        
        return $variations;
    }
    
    /**
     * Detect suspicious propagation patterns
     */
    protected function detectSuspiciousPatterns(array $timeline, array $propagation): array
    {
        $suspicious = [];
        
        // Too rapid propagation
        if ($propagation['propagation_speed'] > 5) { // More than 5 articles per hour
            $suspicious[] = [
                'type' => 'rapid_propagation',
                'severity' => 'medium',
                'description' => 'Content propagated unusually quickly across multiple sources',
                'evidence' => "Propagation speed: {$propagation['propagation_speed']} articles/hour",
            ];
        }
        
        // Simultaneous publications
        $simultaneousPublications = $this->detectSimultaneousPublications($timeline);
        if ($simultaneousPublications > 3) {
            $suspicious[] = [
                'type' => 'simultaneous_publications',
                'severity' => 'high',
                'description' => 'Multiple sources published identical content simultaneously',
                'evidence' => "{$simultaneousPublications} articles published within minutes of each other",
            ];
        }
        
        // Unusual time patterns (e.g., publications during off-hours)
        $offHoursPublications = $this->countOffHoursPublications($timeline);
        if ($offHoursPublications > count($timeline) * 0.7) {
            $suspicious[] = [
                'type' => 'off_hours_publishing',
                'severity' => 'low',
                'description' => 'High percentage of publications during off-peak hours',
                'evidence' => "{$offHoursPublications} out of " . count($timeline) . " publications during off-hours",
            ];
        }
        
        return $suspicious;
    }
    
    /**
     * Detect simultaneous publications (within 1 hour)
     */
    protected function detectSimultaneousPublications(array $timeline): int
    {
        $simultaneous = 0;
        $threshold = 60; // 1 hour in minutes
        
        for ($i = 0; $i < count($timeline) - 1; $i++) {
            for ($j = $i + 1; $j < count($timeline); $j++) {
                $timeDiff = $timeline[$i]['published_at']->diffInMinutes($timeline[$j]['published_at']);
                if ($timeDiff <= $threshold) {
                    $simultaneous++;
                } else {
                    break; // Timeline is sorted, no need to check further
                }
            }
        }
        
        return $simultaneous;
    }
    
    /**
     * Count publications during off-hours (late night/early morning)
     */
    protected function countOffHoursPublications(array $timeline): int
    {
        $offHours = 0;
        
        foreach ($timeline as $entry) {
            $hour = $entry['published_at']->hour;
            // Consider 11 PM to 6 AM as off-hours
            if ($hour >= 23 || $hour <= 6) {
                $offHours++;
            }
        }
        
        return $offHours;
    }
    
    /**
     * Calculate overall provenance confidence
     */
    protected function calculateProvenanceConfidence(array $analysis): float
    {
        $confidence = 0.0;
        $factors = 0;
        
        // Factor 1: Original source identification
        if ($analysis['original_source']) {
            $confidence += $analysis['original_source']['originality_confidence'] * 0.4;
            $factors++;
        }
        
        // Factor 2: Publication pattern naturalness
        $propagation = $analysis['propagation_pattern'];
        if ($propagation['pattern_type'] !== 'unknown') {
            $naturalPatterns = ['organic_propagation', 'gradual_spread', 'limited_propagation'];
            $patternScore = in_array($propagation['pattern_type'], $naturalPatterns) ? 0.8 : 0.4;
            $confidence += $patternScore * 0.3;
            $factors++;
        }
        
        // Factor 3: Absence of suspicious patterns
        $suspiciousCount = count($analysis['suspicious_patterns']);
        $suspiciousScore = max(0.2, 1.0 - ($suspiciousCount * 0.2));
        $confidence += $suspiciousScore * 0.3;
        $factors++;
        
        return $factors > 0 ? $confidence / $factors : 0.0;
    }
    
    /**
     * Generate evidence summary
     */
    protected function generateEvidenceSummary(array $analysis): string
    {
        $summary = [];
        
        if ($analysis['original_source']) {
            $originalSource = $analysis['original_source'];
            $confidence = round($originalSource['originality_confidence'] * 100, 1);
            $summary[] = "Original source identified with {$confidence}% confidence: {$originalSource['source_name']}";
        }
        
        $timelineCount = count($analysis['publication_timeline']);
        if ($timelineCount > 1) {
            $propagationType = $analysis['propagation_pattern']['pattern_type'] ?? 'unknown';
            $summary[] = "Content found across {$timelineCount} sources with {$propagationType} propagation pattern";
        }
        
        $suspiciousCount = count($analysis['suspicious_patterns']);
        if ($suspiciousCount > 0) {
            $summary[] = "{$suspiciousCount} suspicious pattern(s) detected requiring further investigation";
        } else {
            $summary[] = "No suspicious propagation patterns detected";
        }
        
        return implode('. ', $summary);
    }
    
    /**
     * Generate originality reasoning
     */
    protected function generateOriginalityReasoning(array $candidate, array $allCandidates): string
    {
        $reasons = [];
        
        if (count($allCandidates) === 1) {
            $reasons[] = "earliest publication found";
        } else {
            $reasons[] = "highest originality score among early publications";
        }
        
        if ($candidate['quality_score'] > 70) {
            $reasons[] = "high content quality score";
        }
        
        return "Selected based on: " . implode(", ", $reasons);
    }
    
    /**
     * Calculate standard deviation
     */
    protected function calculateStandardDeviation(array $values): float
    {
        if (count($values) < 2) {
            return 0.0;
        }
        
        $mean = array_sum($values) / count($values);
        $variance = array_sum(array_map(fn($x) => pow($x - $mean, 2), $values)) / count($values);
        
        return sqrt($variance);
    }
}