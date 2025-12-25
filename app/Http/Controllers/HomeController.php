<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\ContentHash;
use App\Models\Source;
use App\Models\VerificationRequest;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index()
    {
        $stats = $this->getStatistics();

        return view('home', [
            'title' => 'Content Verification Platform',
            'stats' => $stats,
        ]);
    }

    public function about()
    {
        $stats = $this->getStatistics();

        return view('about', [
            'title' => 'About VerifySource',
            'stats' => $stats,
        ]);
    }

    /**
     * Get platform statistics with caching
     */
    private function getStatistics()
    {
        return cache()->remember('platform_statistics', 300, function () { // Cache for 5 minutes
            return [
                'total_sources' => Source::count(),
                'total_articles' => Article::count(),
                'verification_requests' => VerificationRequest::count(),
                'content_hashes' => ContentHash::count(),
                'avg_quality_score' => round(Article::whereNotNull('quality_score')->avg('quality_score') ?: 0, 1),
                'high_quality_articles' => Article::where('quality_score', '>=', 70)->count(),
                'recent_articles' => Article::with('source')->latest()->limit(5)->get(),
                'top_sources' => Source::withCount('articles')
                    ->orderBy('articles_count', 'desc')
                    ->limit(5)
                    ->get(),
            ];
        });
    }

    public function search(Request $request)
    {
        $query = $request->get('q', '');
        $contentType = $request->get('type', '');

        // Initialize variables
        $articles = collect();
        $sources = collect();
        $totalResults = 0;
        $articleCount = 0;
        $sourceCount = 0;

        if (! empty($query)) {
            // Search articles
            if ($contentType !== 'sources') {
                $articlesQuery = Article::with('source')
                    ->where(function ($q) use ($query) {
                        $q->where('title', 'LIKE', "%{$query}%")
                            ->orWhere('content', 'LIKE', "%{$query}%");
                    });

                if ($contentType === 'articles') {
                    $articles = $articlesQuery->paginate(20);
                } else {
                    $articles = $articlesQuery->latest()->limit(10)->get();
                }

                $articleCount = $articlesQuery->count();
            }

            // Search sources
            if ($contentType !== 'articles') {
                $sourcesQuery = Source::where(function ($q) use ($query) {
                    $q->where('name', 'LIKE', "%{$query}%")
                        ->orWhere('description', 'LIKE', "%{$query}%")
                        ->orWhere('url', 'LIKE', "%{$query}%");
                })
                    ->withCount('articles');

                if ($contentType === 'sources') {
                    $sources = $sourcesQuery->paginate(20);
                } else {
                    $sources = $sourcesQuery->latest()->limit(5)->get();
                }

                $sourceCount = $sourcesQuery->count();
            }

            $totalResults = $articleCount + $sourceCount;
        } else {
            // No query - show all content with pagination
            if ($contentType === 'articles' || ! $contentType) {
                $articles = Article::with('source')->latest()->paginate(20);
                $articleCount = Article::count();
            }

            if ($contentType === 'sources' || ! $contentType) {
                $sources = Source::withCount('articles')->latest()->paginate(20);
                $sourceCount = Source::count();
            }

            $totalResults = $articleCount + $sourceCount;
        }

        return view('search-results', [
            'title' => $query ? "Search Results for: {$query}" : 'Browse All Content',
            'query' => $query,
            'contentType' => $contentType,
            'articles' => $articles,
            'sources' => $sources,
            'totalResults' => $totalResults,
            'articleCount' => $articleCount,
            'sourceCount' => $sourceCount,
        ]);
    }
}
