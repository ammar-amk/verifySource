<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SourceCredibilityScore extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_id',
        'overall_score',
        'domain_trust_score',
        'content_quality_score',
        'bias_score',
        'external_validation_score',
        'historical_accuracy_score',
        'score_breakdown',
        'scoring_factors',
        'credibility_level',
        'score_explanation',
        'confidence_level',
        'calculated_at',
    ];

    protected $casts = [
        'overall_score' => 'decimal:2',
        'domain_trust_score' => 'decimal:2',
        'content_quality_score' => 'decimal:2',
        'bias_score' => 'decimal:2',
        'external_validation_score' => 'decimal:2',
        'historical_accuracy_score' => 'decimal:2',
        'score_breakdown' => 'array',
        'scoring_factors' => 'array',
        'confidence_level' => 'integer',
        'calculated_at' => 'datetime',
    ];

    /**
     * Get the source that owns the credibility score
     */
    public function source()
    {
        return $this->belongsTo(Source::class);
    }

    /**
     * Get credibility score audits
     */
    public function audits()
    {
        return $this->morphMany(CredibilityScoreAudit::class, 'scoreable');
    }

    /**
     * Scope for credibility level
     */
    public function scopeCredibilityLevel($query, $level)
    {
        return $query->where('credibility_level', $level);
    }

    /**
     * Scope for minimum score
     */
    public function scopeMinimumScore($query, $score)
    {
        return $query->where('overall_score', '>=', $score);
    }

    /**
     * Scope for recent scores
     */
    public function scopeRecent($query, $hours = 24)
    {
        return $query->where('calculated_at', '>=', now()->subHours($hours));
    }

    /**
     * Check if score is expired and needs recalculation
     */
    public function isExpired(): bool
    {
        $ttl = config('credibility.caching.domain_scores_ttl', 86400);
        return $this->calculated_at->addSeconds($ttl)->isPast();
    }

    /**
     * Get human-readable credibility level
     */
    public function getCredibilityLabelAttribute(): string
    {
        return match($this->credibility_level) {
            'highly_credible' => 'Highly Credible',
            'credible' => 'Credible',
            'moderately_credible' => 'Moderately Credible',
            'low_credibility' => 'Low Credibility',
            'not_credible' => 'Not Credible',
            default => 'Unknown'
        };
    }

    /**
     * Get score color for UI display
     */
    public function getScoreColorAttribute(): string
    {
        $score = $this->overall_score;
        
        if ($score >= 85) return 'green';
        if ($score >= 70) return 'blue';
        if ($score >= 55) return 'yellow';
        if ($score >= 40) return 'orange';
        
        return 'red';
    }
}
