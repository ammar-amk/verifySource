<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Source;
use App\Models\VerificationRequest;
use App\Models\VerificationResult;
use App\Services\ContentVerificationService;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    /**
     * Display a listing of articles.
     */
    public function index(Request $request)
    {
        $query = Article::with('source');

        // Search functionality
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                    ->orWhere('content', 'LIKE', "%{$search}%")
                    ->orWhereHas('source', function ($sourceQuery) use ($search) {
                        $sourceQuery->where('name', 'LIKE', "%{$search}%");
                    });
            });
        }

        // Source filter
        if ($sourceId = $request->get('source')) {
            $query->where('source_id', $sourceId);
        }

        // Quality filter
        if ($minQuality = $request->get('min_quality')) {
            $query->where('quality_score', '>=', $minQuality);
        }

        // Date filter
        if ($dateFrom = $request->get('date_from')) {
            $query->whereDate('published_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->get('date_to')) {
            $query->whereDate('published_at', '<=', $dateTo);
        }

        // Sorting
        $sortBy = $request->get('sort', 'published_at');
        $sortDirection = $request->get('direction', 'desc');

        if (in_array($sortBy, ['published_at', 'quality_score', 'created_at'])) {
            $query->orderBy($sortBy, $sortDirection);
        } else {
            $query->latest('published_at');
        }

        $articles = $query->paginate(20)->withQueryString();

        // Get sources for filter dropdown (cached)
        $sources = cache()->remember('sources_dropdown', 600, function () {
            return Source::orderBy('name')->get(['id', 'name']);
        });

        return view('articles.index', [
            'title' => 'Browse Articles',
            'articles' => $articles,
            'sources' => $sources,
            'filters' => $request->only(['search', 'source', 'min_quality', 'date_from', 'date_to', 'sort', 'direction']),
        ]);
    }

    /**
     * Display the specified article.
     */
    public function show(Article $article)
    {
        $article->load(['source', 'contentHash']);

        // Get verification results for this article
        $verificationResults = VerificationResult::whereHas('verificationRequest', function ($query) use ($article) {
            $query->where('article_id', $article->id);
        })->latest()->get();

        // Get related articles from the same source
        $relatedArticles = Article::where('source_id', $article->source_id)
            ->where('id', '!=', $article->id)
            ->latest('published_at')
            ->limit(4)
            ->get(['id', 'title', 'published_at', 'source_id']);

        return view('articles.show', [
            'title' => $article->title,
            'article' => $article,
            'verificationResults' => $verificationResults,
            'relatedArticles' => $relatedArticles,
        ]);
    }

    /**
     * Re-verify an article
     */
    public function reverify(Request $request, Article $article, ContentVerificationService $verificationService)
    {
        try {
            // Create a new verification request
            $verificationRequest = VerificationRequest::create([
                'content_type' => 'article',
                'article_id' => $article->id,
                'status' => 'pending',
                'content_hash' => hash('sha256', $article->content ?? $article->title),
                'metadata' => [
                    'source' => 'article_reverify',
                    'article_id' => $article->id,
                    'article_title' => $article->title,
                    'source_name' => $article->source->name,
                ],
            ]);

            // Start verification process
            $result = $verificationService->verifyContent([
                'content' => $article->content ?? '',
                'title' => $article->title,
                'url' => $article->url,
                'source_id' => $article->source_id,
            ], $verificationRequest->id);

            if ($result['success']) {
                // Update article quality score if provided
                if (isset($result['data']['overall_score'])) {
                    $article->update([
                        'quality_score' => $result['data']['overall_score'],
                    ]);
                }

                return redirect()
                    ->route('articles.show', $article)
                    ->with('success', 'Article verification has been completed. The results have been updated.');
            } else {
                return redirect()
                    ->route('articles.show', $article)
                    ->with('error', 'Verification failed: '.($result['message'] ?? 'Unknown error occurred.'));
            }
        } catch (\Exception $e) {
            \Log::error('Article reverification failed', [
                'article_id' => $article->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()
                ->route('articles.show', $article)
                ->with('error', 'An error occurred during verification. Please try again later.');
        }
    }
}
