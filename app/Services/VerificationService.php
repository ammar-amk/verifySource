<?php

namespace App\Services;

use App\Models\Article;
use App\Models\VerificationRequest;
use App\Models\VerificationResult;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class VerificationService
{
    protected ContentMatchingService $contentMatching;

    protected WaybackMachineService $waybackMachine;

    protected ContentProvenanceService $provenance;

    protected CredibilityService $credibility;

    protected VerificationResultService $verificationResults;

    protected array $config;

    public function __construct(
        ContentMatchingService $contentMatching,
        WaybackMachineService $waybackMachine,
        ContentProvenanceService $provenance,
        CredibilityService $credibility,
        VerificationResultService $verificationResults
    ) {
        $this->contentMatching = $contentMatching;
        $this->waybackMachine = $waybackMachine;
        $this->provenance = $provenance;
        $this->credibility = $credibility;
        $this->verificationResults = $verificationResults;
        $this->config = config('verifysource.verification');
    }

    /**
     * Perform comprehensive content verification
     */
    public function verifyContent(string $content, array $options = []): array
    {
        $startTime = microtime(true);
        $cacheKey = 'verification:'.hash('sha256', $content.serialize($options));

        // Check cache first
        if ($this->config['cache_duration'] > 0) {
            $cached = Cache::get($cacheKey);
            if ($cached) {
                return $cached;
            }
        }

        $verification = [
            'content_hash' => hash('sha256', $content),
            'verification_id' => uniqid('ver_'),
            'timestamp' => now(),
            'content_length' => strlen($content),
            'status' => 'processing',
            'confidence' => 0.0,
            'evidence' => [],
            'findings' => [],
            'original_source' => null,
            'credibility_score' => 0.0,
            'processing_time' => 0,
        ];

        try {
            // Step 1: Find similar content and potential sources
            Log::info('Starting content verification', ['verification_id' => $verification['verification_id']]);

            $searchResults = $this->contentMatching->findMatches($content, array_merge([
                'limit' => $this->config['max_results'],
            ], $options));

            $verification['search_results'] = [
                'total_matches' => count($searchResults['matches']),
                'duplicate_likelihood' => $searchResults['duplicate_likelihood'],
                'processing_time' => $searchResults['processing_time'],
            ];

            // Step 2: Analyze content provenance
            if ($this->config['evidence_requirements']['provenance_tracking']) {
                $provenanceAnalysis = $this->provenance->analyzeContentProvenance(
                    $content,
                    $searchResults['matches']
                );

                $verification['provenance'] = $provenanceAnalysis;
                $verification['evidence'][] = [
                    'type' => 'provenance_analysis',
                    'confidence' => $provenanceAnalysis['confidence'],
                    'details' => $provenanceAnalysis['summary'],
                ];
            }

            // Step 3: Verify timestamps with Wayback Machine
            if ($this->config['evidence_requirements']['timestamp_verification'] && ! empty($searchResults['matches'])) {
                $timestampVerification = $this->verifyTimestamps($searchResults['matches']);

                $verification['timestamp_verification'] = $timestampVerification;
                $verification['evidence'][] = [
                    'type' => 'timestamp_verification',
                    'confidence' => $timestampVerification['confidence'],
                    'details' => $timestampVerification['summary'],
                ];
            }

            // Step 4: Analyze source credibility
            if ($this->config['evidence_requirements']['source_verification'] && ! empty($searchResults['matches'])) {
                $credibilityAnalysis = $this->analyzeSourceCredibility($searchResults['matches']);

                $verification['credibility_analysis'] = $credibilityAnalysis;
                $verification['credibility_score'] = $credibilityAnalysis['overall_score'];
                $verification['evidence'][] = [
                    'type' => 'credibility_analysis',
                    'confidence' => $credibilityAnalysis['confidence'],
                    'details' => $credibilityAnalysis['summary'],
                ];
            }

            // Step 5: Find original source
            $originalSource = $this->contentMatching->findContentSource($content);
            if ($originalSource['found_source']) {
                $verification['original_source'] = $originalSource['original_article'];
                $verification['evidence'][] = [
                    'type' => 'original_source_identification',
                    'confidence' => $originalSource['confidence'],
                    'details' => 'Original source identified: '.($originalSource['source_name'] ?? 'Unknown'),
                ];
            }

            // Step 6: Calculate overall confidence and generate findings
            $verification = $this->calculateOverallConfidence($verification);
            $verification = $this->generateFindings($verification);

            $verification['status'] = 'completed';
            $verification['processing_time'] = round((microtime(true) - $startTime) * 1000, 2);

            // Cache the result
            if ($this->config['cache_duration'] > 0) {
                Cache::put($cacheKey, $verification, now()->addSeconds($this->config['cache_duration']));
            }

            Log::info('Content verification completed', [
                'verification_id' => $verification['verification_id'],
                'confidence' => $verification['confidence'],
                'processing_time' => $verification['processing_time'],
            ]);

            return $verification;

        } catch (Exception $e) {
            Log::error('Content verification failed', [
                'verification_id' => $verification['verification_id'],
                'error' => $e->getMessage(),
            ]);

            $verification['status'] = 'failed';
            $verification['error'] = $e->getMessage();
            $verification['processing_time'] = round((microtime(true) - $startTime) * 1000, 2);

            return $verification;
        }
    }

    /**
     * Verify an article and store results
     */
    public function verifyArticle(Article $article): array
    {
        $content = $article->title.' '.$article->content;
        $verification = $this->verifyContent($content, [
            'exclude_article_id' => $article->id,
            'include_source_analysis' => true,
        ]);

        // Store verification result
        $verificationResult = $this->verificationResults->storeVerificationResult(
            $article,
            $verification
        );

        return array_merge($verification, [
            'verification_result_id' => $verificationResult->id,
            'article_id' => $article->id,
        ]);
    }

    /**
     * Process a verification request
     */
    public function processVerificationRequest(VerificationRequest $request): array
    {
        $verification = $this->verifyContent($request->content, [
            'verification_request_id' => $request->id,
            'user_context' => $request->metadata,
        ]);

        // Update request status
        $request->update([
            'status' => $verification['status'],
            'confidence_score' => $verification['confidence'],
            'processed_at' => now(),
        ]);

        // Store detailed results
        $verificationResult = $this->verificationResults->storeVerificationRequestResult(
            $request,
            $verification
        );

        return array_merge($verification, [
            'verification_result_id' => $verificationResult->id,
            'verification_request_id' => $request->id,
        ]);
    }

    /**
     * Verify timestamps using Wayback Machine
     */
    protected function verifyTimestamps(array $matches): array
    {
        $timestampResults = [];
        $totalConfidence = 0;
        $verifiedCount = 0;

        foreach ($matches as $match) {
            if (empty($match['url']) || empty($match['published_at'])) {
                continue;
            }

            $waybackResult = $this->waybackMachine->checkUrlAvailability(
                $match['url'],
                Carbon::createFromTimestamp($match['published_at'])
            );

            if ($waybackResult['success']) {
                $timestampResults[] = [
                    'url' => $match['url'],
                    'claimed_date' => $match['published_at'],
                    'wayback_data' => $waybackResult,
                    'verified' => $waybackResult['timestamp_verified'],
                    'confidence' => $waybackResult['confidence'],
                ];

                if ($waybackResult['timestamp_verified']) {
                    $totalConfidence += $waybackResult['confidence'];
                    $verifiedCount++;
                }
            }
        }

        $overallConfidence = $verifiedCount > 0 ? $totalConfidence / $verifiedCount : 0;

        return [
            'total_checked' => count($timestampResults),
            'verified_count' => $verifiedCount,
            'confidence' => $overallConfidence,
            'results' => $timestampResults,
            'summary' => $this->generateTimestampSummary($timestampResults),
        ];
    }

    /**
     * Analyze credibility of sources in matches
     */
    protected function analyzeSourceCredibility(array $matches): array
    {
        $sourceScores = [];
        $totalScore = 0;
        $sourceCount = 0;

        foreach ($matches as $match) {
            if (empty($match['source_id'])) {
                continue;
            }

            $sourceId = $match['source_id'];

            if (! isset($sourceScores[$sourceId])) {
                $credibilityScore = $this->credibility->calculateSourceCredibility($sourceId);
                $sourceScores[$sourceId] = [
                    'source_id' => $sourceId,
                    'source_name' => $match['source_name'] ?? 'Unknown',
                    'credibility_score' => $credibilityScore['overall_score'],
                    'factors' => $credibilityScore['factors'],
                    'match_count' => 0,
                ];
            }

            $sourceScores[$sourceId]['match_count']++;
            $totalScore += $sourceScores[$sourceId]['credibility_score'];
            $sourceCount++;
        }

        $overallScore = $sourceCount > 0 ? $totalScore / $sourceCount : 0;

        // Sort by credibility score
        uasort($sourceScores, fn ($a, $b) => $b['credibility_score'] <=> $a['credibility_score']);

        return [
            'overall_score' => $overallScore,
            'confidence' => min($overallScore, 1.0),
            'source_count' => $sourceCount,
            'sources' => array_values($sourceScores),
            'summary' => $this->generateCredibilitySummary($sourceScores, $overallScore),
        ];
    }

    /**
     * Calculate overall verification confidence
     */
    protected function calculateOverallConfidence(array $verification): array
    {
        $weights = config('verifysource.scoring.verification_weights');
        $totalWeight = 0;
        $weightedScore = 0;

        // Timestamp verification score
        if (isset($verification['timestamp_verification'])) {
            $score = $verification['timestamp_verification']['confidence'];
            $weightedScore += $score * $weights['timestamp_accuracy'];
            $totalWeight += $weights['timestamp_accuracy'];
        }

        // Content originality score (inverse of duplicate likelihood)
        if (isset($verification['search_results']['duplicate_likelihood'])) {
            $score = 1 - $verification['search_results']['duplicate_likelihood'];
            $weightedScore += $score * $weights['content_originality'];
            $totalWeight += $weights['content_originality'];
        }

        // Source reliability score
        if (isset($verification['credibility_analysis'])) {
            $score = $verification['credibility_analysis']['confidence'];
            $weightedScore += $score * $weights['source_reliability'];
            $totalWeight += $weights['source_reliability'];
        }

        // Provenance verification score
        if (isset($verification['provenance'])) {
            $score = $verification['provenance']['confidence'];
            $weightedScore += $score * $weights['cross_verification'];
            $totalWeight += $weights['cross_verification'];
        }

        $verification['confidence'] = $totalWeight > 0 ? $weightedScore / $totalWeight : 0;

        return $verification;
    }

    /**
     * Generate human-readable findings
     */
    protected function generateFindings(array $verification): array
    {
        $findings = [];
        $confidence = $verification['confidence'];
        $confidenceLevels = $this->config['confidence_levels'];

        // Overall assessment
        if ($confidence >= $confidenceLevels['high']) {
            $findings['overall'] = 'High confidence verification - Content appears authentic and properly attributed.';
        } elseif ($confidence >= $confidenceLevels['medium']) {
            $findings['overall'] = 'Medium confidence verification - Some evidence supports authenticity but manual review recommended.';
        } elseif ($confidence >= $confidenceLevels['low']) {
            $findings['overall'] = 'Low confidence verification - Limited evidence available, thorough manual review required.';
        } else {
            $findings['overall'] = 'Insufficient evidence for verification - Cannot determine authenticity with available data.';
        }

        // Specific findings based on evidence
        if (isset($verification['original_source'])) {
            $source = $verification['original_source'];
            $findings['original_source'] = "Original source identified: {$source['source_name']} published on ".
                date('Y-m-d H:i:s', $source['published_at']);
        }

        if (isset($verification['search_results'])) {
            $duplicateChance = $verification['search_results']['duplicate_likelihood'] * 100;
            if ($duplicateChance > 80) {
                $findings['duplication'] = "High probability ({$duplicateChance}%) that this content is duplicated from other sources.";
            } elseif ($duplicateChance > 50) {
                $findings['duplication'] = "Moderate probability ({$duplicateChance}%) of content duplication detected.";
            }
        }

        if (isset($verification['credibility_analysis'])) {
            $score = $verification['credibility_analysis']['overall_score'];
            $findings['credibility'] = "Average source credibility score: {$score}% across {$verification['credibility_analysis']['source_count']} sources.";
        }

        $verification['findings'] = $findings;

        return $verification;
    }

    /**
     * Generate timestamp verification summary
     */
    protected function generateTimestampSummary(array $results): string
    {
        $verified = count(array_filter($results, fn ($r) => $r['verified']));
        $total = count($results);

        if ($total === 0) {
            return 'No timestamps could be verified.';
        }

        $percentage = round(($verified / $total) * 100, 1);

        return "Verified {$verified} out of {$total} timestamps ({$percentage}%) using Internet Archive data.";
    }

    /**
     * Generate credibility analysis summary
     */
    protected function generateCredibilitySummary(array $sources, float $overallScore): string
    {
        $sourceCount = count($sources);
        $scorePercentage = round($overallScore * 100, 1);

        if ($sourceCount === 0) {
            return 'No sources available for credibility analysis.';
        }

        $topSource = reset($sources);
        $topScore = round($topSource['credibility_score'], 1);
        
        return "Analyzed {$sourceCount} sources with average credibility of {$scorePercentage}%. " .
               "Highest scoring source: {$topSource['source_name']} ({$topScore}%).";
    }

    /**
     * Get verification statistics
     */
    public function getVerificationStatistics(): array
    {
        return [
            'total_verifications' => VerificationResult::count(),
            'high_confidence' => VerificationResult::where('confidence_score', '>=', $this->config['confidence_levels']['high'])->count(),
            'medium_confidence' => VerificationResult::whereBetween('confidence_score', [$this->config['confidence_levels']['low'], $this->config['confidence_levels']['high']])->count(),
            'low_confidence' => VerificationResult::where('confidence_score', '<', $this->config['confidence_levels']['low'])->count(),
            'average_processing_time' => VerificationResult::avg('processing_time_ms') ?? 0,
            'recent_verifications' => VerificationResult::where('created_at', '>=', now()->subDays(7))->count(),
        ];
    }
}
