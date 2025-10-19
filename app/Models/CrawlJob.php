<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrawlJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_id',
        'url',
        'status',
        'priority',
        'retry_count',
        'max_retries',
        'error_message',
        'metadata',
        'scheduled_at',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeRunning($query)
    {
        return $query->where('status', 'running');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeHighPriority($query)
    {
        return $query->where('priority', '>', 0);
    }

    public function scopeRetryable($query)
    {
        return $query->where('retry_count', '<', $this->max_retries);
    }

    public function canRetry(): bool
    {
        return $this->retry_count < $this->max_retries;
    }
}
