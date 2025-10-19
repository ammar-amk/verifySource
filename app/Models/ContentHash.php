<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentHash extends Model
{
    use HasFactory;

    protected $fillable = [
        'hash',
        'article_id',
        'hash_type',
        'similarity_score',
        'similar_hashes',
    ];

    protected $casts = [
        'similarity_score' => 'decimal:4',
        'similar_hashes' => 'array',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function scopeByHash($query, $hash)
    {
        return $query->where('hash', $hash);
    }

    public function scopeByHashType($query, $type)
    {
        return $query->where('hash_type', $type);
    }

    public function scopeSimilar($query, $threshold = 0.8)
    {
        return $query->where('similarity_score', '>=', $threshold);
    }
}
