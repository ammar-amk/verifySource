<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Article extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_id',
        'url',
        'title',
        'content',
        'excerpt',
        'authors',
        'published_at',
        'crawled_at',
        'content_hash',
        'language',
        'word_count',
        'quality_score',
        'metadata',
        'is_processed',
        'is_duplicate',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'crawled_at' => 'datetime',
        'metadata' => 'array',
        'is_processed' => 'boolean',
        'is_duplicate' => 'boolean',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function contentHash(): HasOne
    {
        return $this->hasOne(ContentHash::class);
    }

    public function verificationResults(): HasMany
    {
        return $this->hasMany(VerificationResult::class);
    }

    public function scopeProcessed($query)
    {
        return $query->where('is_processed', true);
    }

    public function scopeNotDuplicate($query)
    {
        return $query->where('is_duplicate', false);
    }

    public function scopeByLanguage($query, $language)
    {
        return $query->where('language', $language);
    }

    public function scopePublishedAfter($query, $date)
    {
        return $query->where('published_at', '>=', $date);
    }

    public function scopePublishedBefore($query, $date)
    {
        return $query->where('published_at', '<=', $date);
    }

    public function getCredibilityScoreAttribute()
    {
        return $this->source->credibility_score ?? 0.5;
    }
}
