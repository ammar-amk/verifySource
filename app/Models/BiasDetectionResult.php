<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BiasDetectionResult extends Model
{
    protected $fillable = [
        'content_type', 'content_id', 'political_bias_score', 'emotional_bias_score',
        'factual_reporting_score', 'political_leaning', 'bias_classification',
        'detected_patterns', 'language_analysis', 'confidence_metrics',
        'bias_explanation', 'detected_at',
    ];

    protected $casts = [
        'political_bias_score' => 'decimal:2',
        'emotional_bias_score' => 'decimal:2',
        'factual_reporting_score' => 'decimal:2',
        'detected_patterns' => 'array',
        'language_analysis' => 'array',
        'confidence_metrics' => 'array',
        'detected_at' => 'datetime',
    ];

    public function content()
    {
        return $this->morphTo();
    }
}
