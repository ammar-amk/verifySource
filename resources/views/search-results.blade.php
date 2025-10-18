@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Search Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Search Results</h1>
        @if($query)
            <p class="text-gray-600">
                Results for "<span class="font-medium">{{ $query }}</span>" 
                @if($totalResults > 0)
                    ({{ number_format($totalResults) }} results found)
                @endif
            </p>
        @else
            <p class="text-gray-600">Browse all content in our database</p>
        @endif
    </div>

    <!-- Search Form -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-8">
        <form method="GET" action="{{ route('search') }}" class="flex flex-col lg:flex-row gap-4">
            <div class="flex-1">
                <label for="q" class="sr-only">Search query</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                    <input 
                        type="text" 
                        name="q" 
                        id="q"
                        value="{{ $query }}"
                        placeholder="Search articles, sources, and content..."
                        class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                    >
                </div>
            </div>

            <!-- Content Type Filter -->
            <div class="lg:w-48">
                <select 
                    name="type" 
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                >
                    <option value="">All Content</option>
                    <option value="articles" {{ $contentType === 'articles' ? 'selected' : '' }}>Articles Only</option>
                    <option value="sources" {{ $contentType === 'sources' ? 'selected' : '' }}>Sources Only</option>
                </select>
            </div>

            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 font-medium">
                Search
            </button>
        </form>
    </div>

    <!-- Results Tabs -->
    <div class="border-b border-gray-200 mb-6">
        <nav class="-mb-px flex space-x-8" aria-label="Tabs">
            <a 
                href="{{ route('search', ['q' => $query, 'type' => '']) }}"
                class="py-2 px-1 border-b-2 font-medium text-sm {{ !$contentType ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
            >
                All Results
                @if($totalResults > 0)
                    <span class="bg-gray-100 text-gray-900 ml-2 py-0.5 px-2.5 rounded-full text-xs">
                        {{ number_format($totalResults) }}
                    </span>
                @endif
            </a>
            
            <a 
                href="{{ route('search', ['q' => $query, 'type' => 'articles']) }}"
                class="py-2 px-1 border-b-2 font-medium text-sm {{ $contentType === 'articles' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
            >
                Articles
                @if(isset($articleCount))
                    <span class="bg-gray-100 text-gray-900 ml-2 py-0.5 px-2.5 rounded-full text-xs">
                        {{ number_format($articleCount) }}
                    </span>
                @endif
            </a>
            
            <a 
                href="{{ route('search', ['q' => $query, 'type' => 'sources']) }}"
                class="py-2 px-1 border-b-2 font-medium text-sm {{ $contentType === 'sources' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
            >
                Sources
                @if(isset($sourceCount))
                    <span class="bg-gray-100 text-gray-900 ml-2 py-0.5 px-2.5 rounded-full text-xs">
                        {{ number_format($sourceCount) }}
                    </span>
                @endif
            </a>
        </nav>
    </div>

    <!-- Results Content -->
    @if($totalResults > 0)
        <div class="space-y-6">
            <!-- Articles Results -->
            @if($contentType !== 'sources' && $articles->count() > 0)
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    @if(!$contentType)
                        <div class="border-b border-gray-200 px-6 py-4">
                            <h2 class="text-lg font-medium text-gray-900">Articles</h2>
                        </div>
                    @endif

                    <div class="divide-y divide-gray-200">
                        @foreach($articles as $article)
                            <article class="p-6 hover:bg-gray-50 transition-colors">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <h3 class="text-lg font-medium text-gray-900 mb-2">
                                            <a href="{{ route('articles.show', $article) }}" class="hover:text-blue-600">
                                                {{ $article->title }}
                                            </a>
                                        </h3>

                                        <!-- Article Metadata -->
                                        <div class="flex items-center space-x-4 text-sm text-gray-500 mb-3">
                                            <span>
                                                <a href="{{ route('sources.show', $article->source) }}" class="hover:text-blue-600">
                                                    {{ $article->source->name }}
                                                </a>
                                            </span>
                                            
                                            @if($article->published_at)
                                                <span>{{ $article->published_at->format('M j, Y') }}</span>
                                            @endif
                                            
                                            @if($article->quality_score)
                                                @php
                                                    $qualityColor = $article->quality_score >= 70 ? 'green' : ($article->quality_score >= 40 ? 'yellow' : 'red');
                                                @endphp
                                                <span class="flex items-center text-{{ $qualityColor }}-600">
                                                    <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                    </svg>
                                                    {{ $article->quality_score }}% Quality
                                                </span>
                                            @endif
                                        </div>

                                        <!-- Content Preview -->
                                        @if($article->content)
                                            <p class="text-gray-700 text-sm leading-relaxed mb-3">
                                                {{ Str::limit(strip_tags($article->content), 250) }}
                                            </p>
                                        @endif

                                        <!-- Article Actions -->
                                        <div class="flex items-center space-x-4 text-sm">
                                            <a href="{{ route('articles.show', $article) }}" class="text-blue-600 hover:text-blue-800">
                                                View Details →
                                            </a>
                                            
                                            @if($article->url)
                                                <a href="{{ $article->url }}" target="_blank" class="text-gray-500 hover:text-gray-700">
                                                    Original Source →
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>

                    @if($contentType === 'articles' && $articles->hasPages())
                        <div class="border-t border-gray-200 px-6 py-4">
                            {{ $articles->appends(request()->query())->links() }}
                        </div>
                    @endif
                </div>
            @endif

            <!-- Sources Results -->
            @if($contentType !== 'articles' && $sources->count() > 0)
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    @if(!$contentType)
                        <div class="border-b border-gray-200 px-6 py-4">
                            <h2 class="text-lg font-medium text-gray-900">Sources</h2>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 p-6">
                        @foreach($sources as $source)
                            <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                                <div class="flex items-start justify-between mb-3">
                                    <h3 class="font-medium text-gray-900">
                                        <a href="{{ route('sources.show', $source) }}" class="hover:text-blue-600">
                                            {{ $source->name }}
                                        </a>
                                    </h3>
                                    
                                    @if($source->credibility_score)
                                        @php
                                            $credibilityColor = $source->credibility_score >= 80 ? 'green' : ($source->credibility_score >= 60 ? 'yellow' : 'red');
                                        @endphp
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $credibilityColor }}-100 text-{{ $credibilityColor }}-800">
                                            {{ $source->credibility_score }}%
                                        </span>
                                    @endif
                                </div>

                                @if($source->description)
                                    <p class="text-gray-600 text-sm mb-3 line-clamp-2">
                                        {{ Str::limit($source->description, 120) }}
                                    </p>
                                @endif

                                <div class="flex items-center justify-between text-sm text-gray-500">
                                    <span class="capitalize">{{ $source->type }}</span>
                                    <span>{{ $source->articles_count ?? 0 }} articles</span>
                                </div>

                                <div class="mt-3 pt-3 border-t border-gray-100">
                                    <a href="{{ route('sources.show', $source) }}" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                        View Source →
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if($contentType === 'sources' && $sources->hasPages())
                        <div class="border-t border-gray-200 px-6 py-4">
                            {{ $sources->appends(request()->query())->links() }}
                        </div>
                    @endif
                </div>
            @endif
        </div>
    @else
        <!-- No Results -->
        <div class="text-center py-12">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">No results found</h3>
            <p class="mt-1 text-sm text-gray-500">
                @if($query)
                    No content matches your search query "{{ $query }}". Try different keywords or browse all content.
                @else
                    Start searching to find articles and sources in our database.
                @endif
            </p>
            
            @if($query)
                <div class="mt-6">
                    <a href="{{ route('search') }}" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                        Browse All Content
                    </a>
                </div>
            @endif
        </div>
    @endif

    <!-- Search Suggestions -->
    @if($query && $totalResults === 0)
        <div class="bg-blue-50 border border-blue-200 rounded-md p-4 mt-8">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-blue-800">
                        Search Tips
                    </h3>
                    <div class="mt-2 text-sm text-blue-700">
                        <ul class="list-disc pl-5 space-y-1">
                            <li>Try different keywords or synonyms</li>
                            <li>Check your spelling</li>
                            <li>Use broader search terms</li>
                            <li>Browse specific categories using the filters</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection