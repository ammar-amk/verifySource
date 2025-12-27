<?php

namespace App\Http\Controllers;

use App\Models\Source;
use App\Services\SourceManagementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SourceController extends Controller
{
    protected SourceManagementService $sourceManager;

    public function __construct(SourceManagementService $sourceManager)
    {
        $this->sourceManager = $sourceManager;
    }

    public function index(Request $request)
    {
        // Check if it's an API request
        if ($request->wantsJson() || $request->is('api/*')) {
            $sources = Source::withCount(['articles', 'crawlJobs'])
                ->orderBy('credibility_score', 'desc')
                ->paginate(20);

            return response()->json([
                'sources' => $sources,
            ]);
        }

        // Web interface
        $query = Source::withCount(['articles', 'crawlJobs']);

        // Search functionality
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('url', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        // Category filter
        if ($category = $request->get('category')) {
            $query->where('category', $category);
        }

        // Credibility filter
        if ($minCredibility = $request->get('min_credibility')) {
            $query->where('credibility_score', '>=', $minCredibility);
        }

        // Activity filter (sources with recent articles)
        if ($request->get('active') === '1') {
            $query->whereHas('articles', function ($q) {
                $q->where('published_at', '>=', now()->subDays(30));
            });
        }

        // Sorting
        $sortBy = $request->get('sort', 'credibility_score');
        $sortDirection = $request->get('direction', 'desc');

        if (in_array($sortBy, ['name', 'credibility_score', 'articles_count', 'created_at'])) {
            $query->orderBy($sortBy, $sortDirection);
        } else {
            $query->orderBy('credibility_score', 'desc');
        }

        $sources = $query->paginate(20)->withQueryString();

        return view('sources.index', [
            'title' => 'Browse Sources',
            'sources' => $sources,
            'filters' => $request->only(['search', 'category', 'min_credibility', 'active', 'sort', 'direction']),
        ]);
    }

    public function show(Source $source, Request $request)
    {
        // Check if it's an API request
        if ($request->wantsJson() || $request->is('api/*')) {
            $source->loadCount(['articles', 'crawlJobs']);
            $stats = $this->sourceManager->calculateSourceStats($source);

            return response()->json([
                'source' => $source,
                'stats' => $stats,
            ]);
        }

        // Web interface
        $source->loadCount(['articles', 'crawlJobs']);

        // Get recent articles from this source
        $recentArticles = $source->articles()
            ->latest('published_at')
            ->limit(10)
            ->get(['id', 'title', 'excerpt', 'author', 'published_at', 'quality_score', 'url']);

        // Get source statistics
        $stats = $this->sourceManager->calculateSourceStats($source);

        return view('sources.show', [
            'title' => $source->name,
            'source' => $source,
            'recentArticles' => $recentArticles,
            'stats' => $stats,
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'url' => 'required|url|max:2048',
            'description' => 'nullable|string|max:1000',
            'credibility_score' => 'nullable|numeric|min:0|max:1',
            'category' => 'nullable|string|max:100',
            'language' => 'nullable|string|size:2',
            'country' => 'nullable|string|size:2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $errors = $this->sourceManager->validateSourceData($request->all());
        if (! empty($errors)) {
            return response()->json([
                'success' => false,
                'errors' => $errors,
            ], 422);
        }

        $source = $this->sourceManager->createSource($request->all());

        return response()->json([
            'success' => true,
            'source' => $source,
        ], 201);
    }

    public function update(Request $request, Source $source)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'url' => 'sometimes|url|max:2048',
            'description' => 'nullable|string|max:1000',
            'credibility_score' => 'nullable|numeric|min:0|max:1',
            'category' => 'nullable|string|max:100',
            'language' => 'nullable|string|size:2',
            'country' => 'nullable|string|size:2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $source = $this->sourceManager->updateSource($source, $request->all());

        return response()->json([
            'success' => true,
            'source' => $source,
        ]);
    }

    public function destroy(Source $source)
    {
        $source->delete();

        return response()->json([
            'success' => true,
            'message' => 'Source deleted successfully',
        ]);
    }

    public function activate(Source $source)
    {
        $this->sourceManager->activateSource($source);

        return response()->json([
            'success' => true,
            'message' => 'Source activated successfully',
        ]);
    }

    public function deactivate(Source $source)
    {
        $this->sourceManager->deactivateSource($source);

        return response()->json([
            'success' => true,
            'message' => 'Source deactivated successfully',
        ]);
    }

    public function verify(Source $source)
    {
        $this->sourceManager->verifySource($source);

        return response()->json([
            'success' => true,
            'message' => 'Source verified successfully',
        ]);
    }

    public function updateCredibility(Request $request, Source $source)
    {
        $validator = Validator::make($request->all(), [
            'credibility_score' => 'required|numeric|min:0|max:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $this->sourceManager->updateCredibilityScore($source, $request->credibility_score);

        return response()->json([
            'success' => true,
            'message' => 'Credibility score updated successfully',
        ]);
    }

    public function getActive()
    {
        $sources = $this->sourceManager->getActiveSources();

        return response()->json([
            'success' => true,
            'sources' => $sources,
        ]);
    }

    public function getByCategory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $sources = $this->sourceManager->getSourcesByCategory($request->category);

        return response()->json([
            'success' => true,
            'sources' => $sources,
        ]);
    }

    public function getHighCredibility(Request $request)
    {
        $threshold = $request->get('threshold', 0.8);
        $sources = $this->sourceManager->getHighCredibilitySources($threshold);

        return response()->json([
            'success' => true,
            'sources' => $sources,
            'threshold' => $threshold,
        ]);
    }

    public function getPerformanceMetrics()
    {
        $metrics = $this->sourceManager->getSourcePerformanceMetrics();

        return response()->json([
            'success' => true,
            'metrics' => $metrics,
        ]);
    }

    public function getRecommendations()
    {
        $recommendations = $this->sourceManager->getSourceRecommendations();

        return response()->json([
            'success' => true,
            'recommendations' => $recommendations,
        ]);
    }

    public function search(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:2',
            'category' => 'nullable|string|max:100',
            'active_only' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $query = Source::query();

        if ($request->query) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'LIKE', '%'.$request->query.'%')
                    ->orWhere('domain', 'LIKE', '%'.$request->query.'%')
                    ->orWhere('description', 'LIKE', '%'.$request->query.'%');
            });
        }

        if ($request->category) {
            $query->where('category', $request->category);
        }

        if ($request->active_only) {
            $query->where('is_active', true);
        }

        $sources = $query->orderBy('credibility_score', 'desc')
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'sources' => $sources,
            'count' => $sources->count(),
        ]);
    }
}
