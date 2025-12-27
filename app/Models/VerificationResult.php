<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerificationResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'verification_request_id',
        'article_id',
        'similarity_score',
        'credibility_score',
        'earliest_publication',
        'match_type',
        'match_details',
        'is_earliest_source',
    ];

    protected $casts = [
        'similarity_score' => 'decimal:4',
        'credibility_score' => 'decimal:2',
        'earliest_publication' => 'datetime',
        'match_details' => 'array',
        'is_earliest_source' => 'boolean',
    ];

    public function verificationRequest(): BelongsTo
    {
        return $this->belongsTo(VerificationRequest::class);
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function scopeByMatchType($query, $type)
    {
        return $query->where('match_type', $type);
    }

    public function scopeExactMatches($query)
    {
        return $query->where('match_type', 'exact');
    }

    public function scopeSimilarMatches($query)
    {
        return $query->where('match_type', 'similar');
    }

    public function scopeEarliestSources($query)
    {
        return $query->where('is_earliest_source', true);
    }

    public function scopeHighSimilarity($query, $threshold = 0.8)
    {
        return $query->where('similarity_score', '>=', $threshold);
    }

    public function scopeHighCredibility($query, $threshold = 0.7)
    {
        return $query->where('credibility_score', '>=', $threshold);
    }

    public function getOverallScoreAttribute()
    {
        return ($this->similarity_score + $this->credibility_score) / 2;
    }
}
