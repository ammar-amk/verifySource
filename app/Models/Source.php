<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Source extends Model
{
    use HasFactory;

    protected $fillable = [
        'domain',
        'name',
        'description',
        'url',
        'credibility_score',
        'credibility_level',
        'trust_score',
        'last_credibility_check',
        'category',
        'language',
        'country',
        'is_verified',
        'is_active',
        'metadata',
        'last_crawled_at',
    ];

    protected $casts = [
        'credibility_score' => 'decimal:2',
        'trust_score' => 'decimal:2',
        'is_verified' => 'boolean',
        'is_active' => 'boolean',
        'metadata' => 'array',
        'last_crawled_at' => 'datetime',
        'last_credibility_check' => 'datetime',
    ];

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function crawlJobs(): HasMany
    {
        return $this->hasMany(CrawlJob::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function scopeHighCredibility($query, $threshold = 0.7)
    {
        return $query->where('credibility_score', '>=', $threshold);
    }
}
