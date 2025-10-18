@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-4">Browse Articles</h1>
        <p class="text-gray-600">Explore our indexed articles and their verification status</p>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-8">
        <form method="GET" action="{{ route('articles.index') }}" class="space-y-4 md:space-y-0 md:grid md:grid-cols-6 md:gap-4">
            <!-- Search -->
            <div class="md:col-span-2">
                <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search Articles</label>
                <input 
                    type="text" 
                    name="search" 
                    id="search"
                    value="{{ $filters['search'] ?? '' }}"
                    placeholder="Title, content, or source..."
                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm"
                >
            </div>

            <!-- Source Filter -->
            <div>
                <label for="source" class="block text-sm font-medium text-gray-700 mb-1">Source</label>
                <select 
                    name="source" 
                    id="source"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm"
                >
                    <option value="">All Sources</option>
                    @foreach($sources as $source)
                        <option value="{{ $source->id }}" {{ ($filters['source'] ?? '') == $source->id ? 'selected' : '' }}>
                            {{ $source->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Quality Filter -->
            <div>
                <label for="min_quality" class="block text-sm font-medium text-gray-700 mb-1">Min. Quality</label>
                <select 
                    name="min_quality" 
                    id="min_quality"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm"
                >
                    <option value="">Any Quality</option>
                    <option value="80" {{ ($filters['min_quality'] ?? '') === '80' ? 'selected' : '' }}>High (80%+)</option>
                    <option value="60" {{ ($filters['min_quality'] ?? '') === '60' ? 'selected' : '' }}>Good (60%+)</option>
                    <option value="40" {{ ($filters['min_quality'] ?? '') === '40' ? 'selected' : '' }}>Fair (40%+)</option>
                </select>
            </div>

            <!-- Date From -->
            <div>
                <label for="date_from" class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
                <input 
                    type="date" 
                    name="date_from" 
                    id="date_from"
                    value="{{ $filters['date_from'] ?? '' }}"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm"
                >
            </div>

            <!-- Submit -->
            <div class="flex items-end">
                <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 text-sm font-medium">
                    Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Results -->
    <div class="space-y-6">
        <!-- Results Summary -->
        <div class="flex items-center justify-between">
            <p class="text-gray-600">
                Showing {{ $articles->count() }} of {{ $articles->total() }} articles
            </p>
            
            <!-- Sort Options -->
            <div class="flex items-center space-x-4">
                <form method="GET" action="{{ route('articles.index') }}" class="flex items-center space-x-2">
                    <!-- Preserve existing filters -->
                    @foreach($filters as $key => $value)
                        @if($key !== 'sort' && $key !== 'direction')
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endif
                    @endforeach
                    
                    <label class="text-sm text-gray-600">Sort:</label>
                    <select 
                        name="sort" 
                        onchange="this.form.submit()"
                        class="text-sm border-gray-300 rounded focus:ring-blue-500 focus:border-blue-500"
                    >
                        <option value="published_at" {{ ($filters['sort'] ?? 'published_at') === 'published_at' ? 'selected' : '' }}>
                            Published Date
                        </option>
                        <option value="quality_score" {{ ($filters['sort'] ?? '') === 'quality_score' ? 'selected' : '' }}>
                            Quality Score
                        </option>
                        <option value="created_at" {{ ($filters['sort'] ?? '') === 'created_at' ? 'selected' : '' }}>
                            Added Date
                        </option>
                    </select>
                </form>
            </div>
        </div>

        <!-- Articles List -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 divide-y divide-gray-200">
            @forelse($articles as $article)
                <article class="p-6 hover:bg-gray-50 transition-colors">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <!-- Article Header -->
                            <div class="mb-2">
                                <h3 class="text-lg font-semibold text-gray-900 mb-1">
                                    <a href="{{ route('articles.show', $article) }}" class="hover:text-blue-600">
                                        {{ $article->title }}
                                    </a>
                                </h3>
                                
                                <!-- Metadata -->
                                <div class="flex items-center space-x-4 text-sm text-gray-600">
                                    <span>
                                        <a href="{{ route('sources.show', $article->source) }}" class="hover:text-blue-600">
                                            {{ $article->source->name }}
                                        </a>
                                    </span>
                                    
                                    @if($article->published_at)
                                        <span>{{ $article->published_at->format('M j, Y') }}</span>
                                    @endif
                                    
                                    @if($article->author)
                                        <span>by {{ $article->author }}</span>
                                    @endif
                                </div>
                            </div>

                            <!-- Content Preview -->
                            @if($article->content)
                                <p class="text-gray-700 text-sm leading-relaxed mb-3">
                                    {{ Str::limit(strip_tags($article->content), 200) }}
                                </p>
                            @endif

                            <!-- Article Stats -->
                            <div class="flex items-center space-x-6 text-sm text-gray-500">
                                @if($article->quality_score)
                                    @php
                                        $qualityColor = $article->quality_score >= 70 ? 'green' : ($article->quality_score >= 40 ? 'yellow' : 'red');
                                    @endphp
                                    <span class="flex items-center">
                                        <svg class="w-4 h-4 mr-1 text-{{ $qualityColor }}-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                        Quality: {{ $article->quality_score }}%
                                    </span>
                                @endif
                                
                                @if($article->url)
                                    <a href="{{ $article->url }}" target="_blank" class="hover:text-blue-600">
                                        View Original →
                                    </a>
                                @endif
                                
                                <span>Added {{ $article->created_at->diffForHumans() }}</span>
                            </div>
                        </div>

                        <!-- Article Actions -->
                        <div class="ml-6 flex flex-col items-end space-y-2">
                            @if($article->url)
                                <a 
                                    href="{{ $article->url }}" 
                                    target="_blank"
                                    class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                >
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                    </svg>
                                    Original
                                </a>
                            @endif
                            
                            <a 
                                href="{{ route('articles.show', $article) }}"
                                class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                            >
                                View Details
                            </a>
                        </div>
                    </div>
                </article>
            @empty
                <div class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No articles found</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        No articles match your current filters. Try adjusting your search criteria.
                    </p>
                </div>
            @endforelse
        </div>

        <!-- Pagination -->
        @if($articles->hasPages())
            <div class="mt-8">
                {{ $articles->links() }}
            </div>
        @endif
    </div>
</div>
@endsection