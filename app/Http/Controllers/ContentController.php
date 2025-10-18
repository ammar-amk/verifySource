<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Source;
use App\Services\ContentProcessingService;
use App\Services\ContentVerificationService;
use App\Services\SourceManagementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ContentController extends Controller
{
    protected ContentProcessingService $contentProcessor;
    protected ContentVerificationService $verificationService;
    protected SourceManagementService $sourceManager;

    public function __construct(
        ContentProcessingService $contentProcessor,
        ContentVerificationService $verificationService,
        SourceManagementService $sourceManager
    ) {
        $this->contentProcessor = $contentProcessor;
        $this->verificationService = $verificationService;
        $this->sourceManager = $sourceManager;
    }

    public function index()
    {
        $articles = Article::with('source')
            ->latest('published_at')
            ->paginate(20);

        $stats = $this->contentProcessor->getContentStats();

        return response()->json([
            'articles' => $articles,
            'stats' => $stats
        ]);
    }

    public function show(Article $article)
    {
        $article->load(['source', 'contentHash', 'verificationResults']);

        return response()->json([
            'article' => $article
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'source_id' => 'required|exists:sources,id',
            'url' => 'required|url|max:2048',
            'title' => 'required|string|max:500',
            'content' => 'required|string|min:100',
            'author' => 'nullable|string|max:255',
            'published_at' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $article = Article::create([
            'source_id' => $request->source_id,
            'url' => $request->url,
            'title' => $request->title,
            'content' => $request->content,
            'author' => $request->author,
            'published_at' => $request->published_at,
            'crawled_at' => now(),
            'content_hash' => hash('sha256', $request->content),
        ]);

        $this->contentProcessor->processArticle($article);

        return response()->json([
            'success' => true,
            'article' => $article->load('source')
        ], 201);
    }

    public function update(Request $request, Article $article)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:500',
            'content' => 'sometimes|string|min:100',
            'author' => 'nullable|string|max:255',
            'published_at' => 'sometimes|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $article->update($request->only(['title', 'content', 'author', 'published_at']));

        if ($request->has('content')) {
            $this->contentProcessor->processArticle($article);
        }

        return response()->json([
            'success' => true,
            'article' => $article->load('source')
        ]);
    }

    public function destroy(Article $article)
    {
        $article->delete();

        return response()->json([
            'success' => true,
            'message' => 'Article deleted successfully'
        ]);
    }

    public function process(Article $article)
    {
        $success = $this->contentProcessor->processArticle($article);

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Article processed successfully' : 'Failed to process article'
        ]);
    }

    public function findDuplicates(Article $article)
    {
        $duplicates = $this->contentProcessor->findSimilarContent($article->content_hash);

        return response()->json([
            'success' => true,
            'duplicates' => $duplicates
        ]);
    }

    public function markAsDuplicate(Article $article)
    {
        $this->contentProcessor->markAsDuplicate($article);

        return response()->json([
            'success' => true,
            'message' => 'Article marked as duplicate'
        ]);
    }

    public function getStats()
    {
        $contentStats = $this->contentProcessor->getContentStats();
        $verificationStats = $this->verificationService->getVerificationStats();

        return response()->json([
            'content_stats' => $contentStats,
            'verification_stats' => $verificationStats
        ]);
    }

    public function search(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:3',
            'source_id' => 'nullable|exists:sources,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $query = Article::with('source')
            ->where('is_processed', true)
            ->where('is_duplicate', false);

        if ($request->query) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'LIKE', '%' . $request->query . '%')
                  ->orWhere('content', 'LIKE', '%' . $request->query . '%')
                  ->orWhere('excerpt', 'LIKE', '%' . $request->query . '%');
            });
        }

        if ($request->source_id) {
            $query->where('source_id', $request->source_id);
        }

        if ($request->date_from) {
            $query->where('published_at', '>=', $request->date_from);
        }

        if ($request->date_to) {
            $query->where('published_at', '<=', $request->date_to);
        }

        $articles = $query->latest('published_at')
            ->limit($request->limit ?? 20)
            ->get();

        return response()->json([
            'success' => true,
            'articles' => $articles,
            'count' => $articles->count()
        ]);
    }

    public function getBySource(Source $source)
    {
        $articles = $source->articles()
            ->latest('published_at')
            ->paginate(20);

        $sourceStats = $this->sourceManager->calculateSourceStats($source);

        return response()->json([
            'source' => $source,
            'articles' => $articles,
            'stats' => $sourceStats
        ]);
    }

    public function getRecent()
    {
        $articles = Article::with('source')
            ->where('is_processed', true)
            ->where('is_duplicate', false)
            ->latest('published_at')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'articles' => $articles
        ]);
    }
}
