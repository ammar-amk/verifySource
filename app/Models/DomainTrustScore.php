<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DomainTrustScore extends Model
{
    use HasFactory;

    protected $fillable = [
        'domain',
        'trust_score',
        'trust_factors',
        'risk_factors',
        'domain_age_score',
        'ssl_score',
        'whois_score',
        'security_score',
        'is_trusted_source',
        'is_government',
        'is_academic',
        'is_news_organization',
        'classification',
        'last_analyzed_at',
    ];

    protected $casts = [
        'trust_factors' => 'array',
        'risk_factors' => 'array',
        'trust_score' => 'decimal:2',
        'domain_age_score' => 'decimal:2',
        'ssl_score' => 'decimal:2',
        'whois_score' => 'decimal:2',
        'security_score' => 'decimal:2',
        'is_trusted_source' => 'boolean',
        'is_government' => 'boolean',
        'is_academic' => 'boolean',
        'is_news_organization' => 'boolean',
        'last_analyzed_at' => 'datetime',
    ];

    /**
     * Get sources using this domain
     */
    public function sources()
    {
        return $this->hasMany(Source::class, 'domain', 'domain');
    }

    /**
     * Scope for trusted sources
     */
    public function scopeTrusted($query)
    {
        return $query->where('is_trusted_source', true);
    }

    /**
     * Scope for government sources
     */
    public function scopeGovernment($query)
    {
        return $query->where('is_government', true);
    }

    /**
     * Scope for academic sources
     */
    public function scopeAcademic($query)
    {
        return $query->where('is_academic', true);
    }

    /**
     * Scope for high trust score
     */
    public function scopeHighTrust($query, $threshold = 70)
    {
        return $query->where('trust_score', '>=', $threshold);
    }

    /**
     * Get credibility level based on trust score
     */
    public function getCredibilityLevelAttribute(): string
    {
        $score = $this->trust_score;
        
        if ($score >= 85) return 'highly_credible';
        if ($score >= 70) return 'credible';
        if ($score >= 55) return 'moderately_credible';
        if ($score >= 40) return 'low_credibility';
        
        return 'not_credible';
    }

    /**
     * Check if domain needs re-analysis
     */
    public function needsReanalysis(): bool
    {
        $maxAge = config('credibility.domain_trust.cache_ttl', 86400);
        return $this->last_analyzed_at->addSeconds($maxAge)->isPast();
    }
}
