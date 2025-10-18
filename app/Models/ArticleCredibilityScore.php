<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArticleCredibilityScore extends Model
{
    protected $fillable = [
        'article_id', 'overall_score', 'content_quality_score', 'readability_score',
        'fact_density_score', 'citation_score', 'bias_score', 'sentiment_neutrality',
        'quality_indicators', 'quality_detractors', 'bias_analysis', 'credibility_level',
        'analysis_summary', 'analyzed_at'
    ];

    protected $casts = [
        'overall_score' => 'decimal:2',
        'content_quality_score' => 'decimal:2',
        'readability_score' => 'decimal:2',
        'fact_density_score' => 'decimal:2',
        'citation_score' => 'decimal:2',
        'bias_score' => 'decimal:2',
        'sentiment_neutrality' => 'decimal:2',
        'quality_indicators' => 'array',
        'quality_detractors' => 'array',
        'bias_analysis' => 'array',
        'analyzed_at' => 'datetime',
    ];

    public function article()
    {
        return $this->belongsTo(Article::class);
    }
}
