<?php

namespace App\Services;

use App\Models\VerificationResult;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VerificationResultService
{
    /**
     * Store verification result
     */
    public function storeResult(
        int $verificationRequestId,
        string $contentHash,
        array $verificationData,
        array $searchMatches = [],
        array $provenanceAnalysis = [],
        array $credibilityAssessment = []
    ): VerificationResult {
        try {
            return DB::transaction(function () use (
                $verificationRequestId,
                $contentHash,
                $verificationData,
                $searchMatches,
                $provenanceAnalysis,
                $credibilityAssessment
            ) {
                $result = VerificationResult::create([
                    'verification_request_id' => $verificationRequestId,
                    'content_hash' => $contentHash,
                    'overall_confidence' => $verificationData['overall_confidence'] ?? 0.0,
                    'verification_status' => $verificationData['status'] ?? 'pending',
                    'findings' => $verificationData['findings'] ?? [],
                    'evidence' => $this->compileEvidence($verificationData, $searchMatches, $provenanceAnalysis, $credibilityAssessment),
                    'recommendations' => $verificationData['recommendations'] ?? [],
                    'verified_at' => now(),
                ]);

                Log::info('Verification result stored', [
                    'result_id' => $result->id,
                    'request_id' => $verificationRequestId,
                    'confidence' => $result->overall_confidence,
                    'status' => $result->verification_status,
                ]);

                return $result;
            });
        } catch (Exception $e) {
            Log::error('Failed to store verification result', [
                'request_id' => $verificationRequestId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Update existing verification result
     */
    public function updateResult(int $resultId, array $updates): VerificationResult
    {
        try {
            $result = VerificationResult::findOrFail($resultId);

            // Merge new data with existing
            if (isset($updates['findings'])) {
                $existingFindings = $result->findings ?? [];
                $updates['findings'] = array_merge($existingFindings, $updates['findings']);
            }

            if (isset($updates['evidence'])) {
                $existingEvidence = $result->evidence ?? [];
                $updates['evidence'] = array_merge($existingEvidence, $updates['evidence']);
            }

            if (isset($updates['recommendations'])) {
                $existingRecommendations = $result->recommendations ?? [];
                $updates['recommendations'] = array_merge($existingRecommendations, $updates['recommendations']);
            }

            $result->update($updates);

            Log::info('Verification result updated', [
                'result_id' => $resultId,
                'updated_fields' => array_keys($updates),
            ]);

            return $result->fresh();
        } catch (Exception $e) {
            Log::error('Failed to update verification result', [
                'result_id' => $resultId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get verification results with analysis
     */
    public function getResultWithAnalysis(int $resultId): array
    {
        try {
            $result = VerificationResult::with(['verificationRequest'])
                ->findOrFail($resultId);

            $analysis = [
                'result' => $result->toArray(),
                'summary' => $this->generateResultSummary($result),
                'detailed_analysis' => $this->generateDetailedAnalysis($result),
                'risk_assessment' => $this->generateRiskAssessment($result),
                'action_items' => $this->generateActionItems($result),
            ];

            return $analysis;
        } catch (Exception $e) {
            Log::error('Failed to get verification result with analysis', [
                'result_id' => $resultId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get verification statistics
     */
    public function getVerificationStatistics(array $filters = []): array
    {
        try {
            $query = VerificationResult::query();

            // Apply filters
            if (isset($filters['date_from'])) {
                $query->where('verified_at', '>=', $filters['date_from']);
            }

            if (isset($filters['date_to'])) {
                $query->where('verified_at', '<=', $filters['date_to']);
            }

            if (isset($filters['status'])) {
                $query->where('verification_status', $filters['status']);
            }

            if (isset($filters['confidence_min'])) {
                $query->where('overall_confidence', '>=', $filters['confidence_min']);
            }

            $results = $query->get();

            $statistics = [
                'total_verifications' => $results->count(),
                'status_breakdown' => $this->calculateStatusBreakdown($results),
                'confidence_distribution' => $this->calculateConfidenceDistribution($results),
                'temporal_trends' => $this->calculateTemporalTrends($results),
                'top_findings' => $this->getTopFindings($results),
                'average_confidence' => $results->avg('overall_confidence'),
                'completion_rate' => $this->calculateCompletionRate($results),
            ];

            return $statistics;
        } catch (Exception $e) {
            Log::error('Failed to get verification statistics', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Search verification results
     */
    public function searchResults(array $criteria): Collection
    {
        try {
            $query = VerificationResult::with(['verificationRequest']);

            // Text search in findings
            if (isset($criteria['text'])) {
                $query->whereJsonContains('findings', ['description' => $criteria['text']])
                    ->orWhereJsonContains('evidence', ['description' => $criteria['text']]);
            }

            // Search by confidence range
            if (isset($criteria['confidence_min']) || isset($criteria['confidence_max'])) {
                if (isset($criteria['confidence_min'])) {
                    $query->where('overall_confidence', '>=', $criteria['confidence_min']);
                }
                if (isset($criteria['confidence_max'])) {
                    $query->where('overall_confidence', '<=', $criteria['confidence_max']);
                }
            }

            // Search by verification status
            if (isset($criteria['status'])) {
                if (is_array($criteria['status'])) {
                    $query->whereIn('verification_status', $criteria['status']);
                } else {
                    $query->where('verification_status', $criteria['status']);
                }
            }

            // Date range
            if (isset($criteria['date_from']) || isset($criteria['date_to'])) {
                if (isset($criteria['date_from'])) {
                    $query->where('verified_at', '>=', $criteria['date_from']);
                }
                if (isset($criteria['date_to'])) {
                    $query->where('verified_at', '<=', $criteria['date_to']);
                }
            }

            // Order by relevance or date
            $orderBy = $criteria['order_by'] ?? 'verified_at';
            $orderDirection = $criteria['order_direction'] ?? 'desc';
            $query->orderBy($orderBy, $orderDirection);

            // Limit results
            $limit = $criteria['limit'] ?? 100;
            $query->limit($limit);

            return $query->get();
        } catch (Exception $e) {
            Log::error('Failed to search verification results', [
                'criteria' => $criteria,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get similar verification results based on content
     */
    public function getSimilarResults(string $contentHash, int $limit = 10): Collection
    {
        try {
            // Find results with similar evidence patterns
            $results = VerificationResult::where('content_hash', '!=', $contentHash)
                ->orderBy('verified_at', 'desc')
                ->limit($limit * 3) // Get more to filter
                ->get();

            // Score similarity and return top matches
            $scoredResults = $results->map(function ($result) use ($contentHash) {
                $similarity = $this->calculateResultSimilarity($contentHash, $result);
                $result->similarity_score = $similarity;

                return $result;
            })->filter(function ($result) {
                return $result->similarity_score > 0.3; // Only include reasonably similar results
            })->sortByDesc('similarity_score')
                ->take($limit);

            return $scoredResults;
        } catch (Exception $e) {
            Log::error('Failed to get similar verification results', [
                'content_hash' => $contentHash,
                'error' => $e->getMessage(),
            ]);

            return collect();
        }
    }

    /**
     * Compile evidence from all verification components
     */
    protected function compileEvidence(
        array $verificationData,
        array $searchMatches,
        array $provenanceAnalysis,
        array $credibilityAssessment
    ): array {
        $evidence = [];

        // Add search evidence
        if (! empty($searchMatches)) {
            $evidence['search_matches'] = [
                'type' => 'search_results',
                'description' => 'Content matching analysis',
                'data' => [
                    'total_matches' => count($searchMatches),
                    'top_matches' => array_slice($searchMatches, 0, 5),
                    'match_confidence' => $this->calculateMatchConfidence($searchMatches),
                ],
                'timestamp' => now()->toISOString(),
            ];
        }

        // Add provenance evidence
        if (! empty($provenanceAnalysis)) {
            $evidence['provenance'] = [
                'type' => 'content_provenance',
                'description' => 'Content origin and propagation analysis',
                'data' => $provenanceAnalysis,
                'timestamp' => now()->toISOString(),
            ];
        }

        // Add credibility evidence
        if (! empty($credibilityAssessment)) {
            $evidence['credibility'] = [
                'type' => 'credibility_assessment',
                'description' => 'Source and content credibility analysis',
                'data' => $credibilityAssessment,
                'timestamp' => now()->toISOString(),
            ];
        }

        // Add wayback machine evidence if available
        if (isset($verificationData['wayback_analysis'])) {
            $evidence['wayback_machine'] = [
                'type' => 'temporal_verification',
                'description' => 'Internet Archive timestamp verification',
                'data' => $verificationData['wayback_analysis'],
                'timestamp' => now()->toISOString(),
            ];
        }

        return $evidence;
    }

    /**
     * Calculate match confidence from search results
     */
    protected function calculateMatchConfidence(array $matches): float
    {
        if (empty($matches)) {
            return 0.0;
        }

        $scores = array_column($matches, 'hybrid_score');
        if (empty($scores)) {
            $scores = array_column($matches, 'score');
        }

        if (empty($scores)) {
            return 0.5;
        }

        return array_sum($scores) / count($scores);
    }

    /**
     * Generate result summary
     */
    protected function generateResultSummary(VerificationResult $result): array
    {
        $summary = [
            'overall_status' => $result->verification_status,
            'confidence_level' => $this->getConfidenceLabel($result->overall_confidence),
            'key_findings' => $this->extractKeyFindings($result->findings ?? []),
            'primary_concerns' => $this->extractPrimaryConcerns($result->findings ?? []),
            'recommendation_summary' => $this->summarizeRecommendations($result->recommendations ?? []),
        ];

        return $summary;
    }

    /**
     * Generate detailed analysis
     */
    protected function generateDetailedAnalysis(VerificationResult $result): array
    {
        $evidence = $result->evidence ?? [];

        $analysis = [
            'evidence_strength' => $this->assessEvidenceStrength($evidence),
            'consistency_check' => $this->checkEvidenceConsistency($evidence),
            'coverage_analysis' => $this->analyzeCoverage($evidence),
            'reliability_factors' => $this->identifyReliabilityFactors($evidence),
        ];

        return $analysis;
    }

    /**
     * Generate risk assessment
     */
    protected function generateRiskAssessment(VerificationResult $result): array
    {
        $riskLevel = 'low';
        $riskFactors = [];

        if ($result->overall_confidence < 0.3) {
            $riskLevel = 'high';
            $riskFactors[] = 'Very low verification confidence';
        } elseif ($result->overall_confidence < 0.6) {
            $riskLevel = 'medium';
            $riskFactors[] = 'Moderate verification confidence';
        }

        // Check for suspicious patterns
        $findings = $result->findings ?? [];
        foreach ($findings as $finding) {
            if (isset($finding['type']) && str_contains($finding['type'], 'suspicious')) {
                $riskLevel = max($riskLevel, 'high');
                $riskFactors[] = $finding['description'] ?? 'Suspicious pattern detected';
            }
        }

        return [
            'risk_level' => $riskLevel,
            'risk_factors' => $riskFactors,
            'mitigation_suggestions' => $this->generateMitigationSuggestions($riskLevel, $riskFactors),
        ];
    }

    /**
     * Generate action items
     */
    protected function generateActionItems(VerificationResult $result): array
    {
        $actionItems = [];

        if ($result->overall_confidence < 0.5) {
            $actionItems[] = [
                'priority' => 'high',
                'action' => 'Conduct additional verification',
                'description' => 'Low confidence score requires further investigation',
            ];
        }

        if ($result->verification_status === 'suspicious') {
            $actionItems[] = [
                'priority' => 'urgent',
                'action' => 'Flag for manual review',
                'description' => 'Suspicious patterns detected requiring human assessment',
            ];
        }

        return $actionItems;
    }

    /**
     * Calculate status breakdown
     */
    protected function calculateStatusBreakdown(Collection $results): array
    {
        return $results->groupBy('verification_status')
            ->map->count()
            ->toArray();
    }

    /**
     * Calculate confidence distribution
     */
    protected function calculateConfidenceDistribution(Collection $results): array
    {
        $distribution = [
            'very_low' => 0,   // 0.0 - 0.2
            'low' => 0,        // 0.2 - 0.4
            'medium' => 0,     // 0.4 - 0.6
            'high' => 0,       // 0.6 - 0.8
            'very_high' => 0,  // 0.8 - 1.0
        ];

        foreach ($results as $result) {
            $confidence = $result->overall_confidence;

            if ($confidence < 0.2) {
                $distribution['very_low']++;
            } elseif ($confidence < 0.4) {
                $distribution['low']++;
            } elseif ($confidence < 0.6) {
                $distribution['medium']++;
            } elseif ($confidence < 0.8) {
                $distribution['high']++;
            } else {
                $distribution['very_high']++;
            }
        }

        return $distribution;
    }

    /**
     * Calculate temporal trends
     */
    protected function calculateTemporalTrends(Collection $results): array
    {
        $trends = $results->groupBy(function ($result) {
            return $result->verified_at->format('Y-m-d');
        })->map(function ($dayResults) {
            return [
                'count' => $dayResults->count(),
                'avg_confidence' => $dayResults->avg('overall_confidence'),
            ];
        })->toArray();

        return $trends;
    }

    /**
     * Get top findings across results
     */
    protected function getTopFindings(Collection $results): array
    {
        $findingCounts = [];

        foreach ($results as $result) {
            $findings = $result->findings ?? [];
            foreach ($findings as $finding) {
                $type = $finding['type'] ?? 'unknown';
                if (! isset($findingCounts[$type])) {
                    $findingCounts[$type] = 0;
                }
                $findingCounts[$type]++;
            }
        }

        arsort($findingCounts);

        return array_slice($findingCounts, 0, 10);
    }

    /**
     * Calculate completion rate
     */
    protected function calculateCompletionRate(Collection $results): float
    {
        $completed = $results->whereIn('verification_status', ['verified', 'suspicious', 'unverifiable'])->count();
        $total = $results->count();

        return $total > 0 ? ($completed / $total) : 0.0;
    }

    /**
     * Calculate similarity between results
     */
    protected function calculateResultSimilarity(string $contentHash, VerificationResult $result): float
    {
        // This is a simplified similarity calculation
        // In a real implementation, you might use more sophisticated NLP techniques

        $similarity = 0.0;

        // Compare evidence types
        $evidence = $result->evidence ?? [];
        $evidenceTypes = array_keys($evidence);

        // Weight based on evidence overlap and finding patterns
        // This would need to be expanded based on actual requirements

        return $similarity;
    }

    /**
     * Get confidence label
     */
    protected function getConfidenceLabel(float $confidence): string
    {
        if ($confidence >= 0.8) {
            return 'Very High';
        }
        if ($confidence >= 0.6) {
            return 'High';
        }
        if ($confidence >= 0.4) {
            return 'Medium';
        }
        if ($confidence >= 0.2) {
            return 'Low';
        }

        return 'Very Low';
    }

    /**
     * Extract key findings
     */
    protected function extractKeyFindings(array $findings): array
    {
        return array_slice(array_map(function ($finding) {
            return $finding['description'] ?? $finding['type'] ?? 'Unknown finding';
        }, $findings), 0, 3);
    }

    /**
     * Extract primary concerns
     */
    protected function extractPrimaryConcerns(array $findings): array
    {
        $concerns = array_filter($findings, function ($finding) {
            return ($finding['severity'] ?? 'low') !== 'low';
        });

        return array_slice(array_map(function ($concern) {
            return $concern['description'] ?? $concern['type'] ?? 'Unknown concern';
        }, $concerns), 0, 3);
    }

    /**
     * Summarize recommendations
     */
    protected function summarizeRecommendations(array $recommendations): string
    {
        if (empty($recommendations)) {
            return 'No specific recommendations';
        }

        return implode('. ', array_slice($recommendations, 0, 2));
    }

    /**
     * Assess evidence strength
     */
    protected function assessEvidenceStrength(array $evidence): array
    {
        $strength = [
            'overall' => 'medium',
            'factors' => [],
        ];

        if (isset($evidence['search_matches'])) {
            $strength['factors'][] = 'Search matches available';
        }

        if (isset($evidence['provenance'])) {
            $strength['factors'][] = 'Provenance analysis conducted';
        }

        if (isset($evidence['credibility'])) {
            $strength['factors'][] = 'Credibility assessment performed';
        }

        return $strength;
    }

    /**
     * Check evidence consistency
     */
    protected function checkEvidenceConsistency(array $evidence): array
    {
        return [
            'consistent' => true,
            'conflicts' => [],
            'notes' => 'Evidence consistency analysis would be implemented here',
        ];
    }

    /**
     * Analyze coverage
     */
    protected function analyzeCoverage(array $evidence): array
    {
        $coverage = [
            'completeness' => count($evidence) > 2 ? 'good' : 'partial',
            'gaps' => [],
        ];

        if (! isset($evidence['search_matches'])) {
            $coverage['gaps'][] = 'No search analysis';
        }

        if (! isset($evidence['provenance'])) {
            $coverage['gaps'][] = 'No provenance analysis';
        }

        return $coverage;
    }

    /**
     * Identify reliability factors
     */
    protected function identifyReliabilityFactors(array $evidence): array
    {
        return [
            'positive_factors' => ['Multiple evidence types', 'Recent analysis'],
            'negative_factors' => [],
        ];
    }

    /**
     * Generate mitigation suggestions
     */
    protected function generateMitigationSuggestions(string $riskLevel, array $riskFactors): array
    {
        $suggestions = [];

        if ($riskLevel === 'high') {
            $suggestions[] = 'Require manual verification before publication';
            $suggestions[] = 'Seek additional sources for confirmation';
        } elseif ($riskLevel === 'medium') {
            $suggestions[] = 'Add disclaimer about verification confidence';
            $suggestions[] = 'Monitor for additional evidence';
        }

        return $suggestions;
    }
}
