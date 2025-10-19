<?php

namespace App\Services;

use App\Models\Source;
use Illuminate\Support\Facades\Log;

class SourceManagementService
{
    public function createSource(array $data): Source
    {
        $data['domain'] = $this->extractDomain($data['url']);

        $source = Source::create($data);

        Log::info('Source created', [
            'source_id' => $source->id,
            'domain' => $source->domain,
        ]);

        return $source;
    }

    public function updateSource(Source $source, array $data): Source
    {
        if (isset($data['url'])) {
            $data['domain'] = $this->extractDomain($data['url']);
        }

        $source->update($data);

        Log::info('Source updated', [
            'source_id' => $source->id,
            'domain' => $source->domain,
        ]);

        return $source;
    }

    public function activateSource(Source $source): void
    {
        $source->update(['is_active' => true]);

        Log::info('Source activated', [
            'source_id' => $source->id,
            'domain' => $source->domain,
        ]);
    }

    public function deactivateSource(Source $source): void
    {
        $source->update(['is_active' => false]);

        Log::info('Source deactivated', [
            'source_id' => $source->id,
            'domain' => $source->domain,
        ]);
    }

    public function verifySource(Source $source): void
    {
        $source->update(['is_verified' => true]);

        Log::info('Source verified', [
            'source_id' => $source->id,
            'domain' => $source->domain,
        ]);
    }

    public function updateCredibilityScore(Source $source, float $score): void
    {
        $score = max(0.0, min(1.0, $score));

        $source->update(['credibility_score' => $score]);

        Log::info('Source credibility score updated', [
            'source_id' => $source->id,
            'domain' => $source->domain,
            'new_score' => $score,
        ]);
    }

    public function extractDomain(string $url): string
    {
        $parsed = parse_url($url);

        if (! isset($parsed['host'])) {
            throw new \InvalidArgumentException("Invalid URL: {$url}");
        }

        $domain = $parsed['host'];

        if (strpos($domain, 'www.') === 0) {
            $domain = substr($domain, 4);
        }

        return $domain;
    }

    public function getSourceByDomain(string $domain): ?Source
    {
        return Source::where('domain', $domain)->first();
    }

    public function getActiveSources(): \Illuminate\Database\Eloquent\Collection
    {
        return Source::where('is_active', true)
            ->orderBy('credibility_score', 'desc')
            ->get();
    }

    public function getSourcesByCategory(string $category): \Illuminate\Database\Eloquent\Collection
    {
        return Source::where('category', $category)
            ->where('is_active', true)
            ->orderBy('credibility_score', 'desc')
            ->get();
    }

    public function getHighCredibilitySources(float $threshold = 0.8): \Illuminate\Database\Eloquent\Collection
    {
        return Source::where('credibility_score', '>=', $threshold)
            ->where('is_active', true)
            ->orderBy('credibility_score', 'desc')
            ->get();
    }

    public function calculateSourceStats(Source $source): array
    {
        $articles = $source->articles();

        return [
            'total_articles' => $articles->count(),
            'processed_articles' => $articles->where('is_processed', true)->count(),
            'duplicate_articles' => $articles->where('is_duplicate', true)->count(),
            'latest_article' => $articles->latest('published_at')->first(),
            'oldest_article' => $articles->oldest('published_at')->first(),
            'average_articles_per_day' => $this->calculateAverageArticlesPerDay($source),
            'last_crawled' => $source->last_crawled_at,
        ];
    }

    protected function calculateAverageArticlesPerDay(Source $source): float
    {
        $articles = $source->articles()->whereNotNull('published_at');

        if ($articles->count() === 0) {
            return 0.0;
        }

        $oldestArticle = $articles->oldest('published_at')->first();
        $newestArticle = $articles->latest('published_at')->first();

        if (! $oldestArticle || ! $newestArticle) {
            return 0.0;
        }

        $daysDiff = $oldestArticle->published_at->diffInDays($newestArticle->published_at);

        if ($daysDiff === 0) {
            return $articles->count();
        }

        return $articles->count() / $daysDiff;
    }

    public function updateLastCrawled(Source $source): void
    {
        $source->update(['last_crawled_at' => now()]);
    }

    public function getSourceRecommendations(): array
    {
        $categories = Source::select('category')
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->toArray();

        $recommendations = [];

        foreach ($categories as $category) {
            $sources = $this->getSourcesByCategory($category);

            if ($sources->count() < 5) {
                $recommendations[] = [
                    'category' => $category,
                    'current_count' => $sources->count(),
                    'recommended_count' => 10,
                    'priority' => 'high',
                ];
            }
        }

        return $recommendations;
    }

    public function validateSourceData(array $data): array
    {
        $errors = [];

        if (empty($data['name'])) {
            $errors[] = 'Source name is required';
        }

        if (empty($data['url'])) {
            $errors[] = 'Source URL is required';
        } elseif (! filter_var($data['url'], FILTER_VALIDATE_URL)) {
            $errors[] = 'Invalid URL format';
        }

        if (isset($data['credibility_score'])) {
            $score = floatval($data['credibility_score']);
            if ($score < 0 || $score > 1) {
                $errors[] = 'Credibility score must be between 0 and 1';
            }
        }

        if (isset($data['language']) && ! in_array($data['language'], ['en', 'es', 'fr', 'de', 'it', 'pt', 'ru', 'zh', 'ja', 'ko'])) {
            $errors[] = 'Unsupported language code';
        }

        return $errors;
    }

    public function getSourcePerformanceMetrics(): array
    {
        $sources = Source::withCount(['articles', 'crawlJobs'])
            ->with(['articles' => function ($query) {
                $query->select('source_id', 'published_at', 'is_processed', 'is_duplicate');
            }])
            ->get();

        $metrics = [];

        foreach ($sources as $source) {
            $articles = $source->articles;
            $processedCount = $articles->where('is_processed', true)->count();
            $duplicateCount = $articles->where('is_duplicate', true)->count();

            $metrics[] = [
                'source_id' => $source->id,
                'domain' => $source->domain,
                'name' => $source->name,
                'credibility_score' => $source->credibility_score,
                'total_articles' => $source->articles_count,
                'processed_articles' => $processedCount,
                'duplicate_articles' => $duplicateCount,
                'processing_rate' => $source->articles_count > 0 ? ($processedCount / $source->articles_count) * 100 : 0,
                'duplicate_rate' => $source->articles_count > 0 ? ($duplicateCount / $source->articles_count) * 100 : 0,
                'total_crawl_jobs' => $source->crawl_jobs_count,
                'is_active' => $source->is_active,
                'is_verified' => $source->is_verified,
            ];
        }

        return $metrics;
    }
}
