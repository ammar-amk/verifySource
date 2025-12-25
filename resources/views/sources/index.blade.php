@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-4">Browse Sources</h1>
        <p class="text-gray-600">Explore our indexed news sources and their credibility information</p>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-8">
        <form method="GET" action="{{ route('sources.index') }}" class="space-y-4 md:space-y-0 md:grid md:grid-cols-6 md:gap-4">
            <!-- Search -->
            <div class="md:col-span-2">
                <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search Sources</label>
                <input 
                    type="text" 
                    name="search" 
                    id="search"
                    value="{{ $filters['search'] ?? '' }}"
                    placeholder="Source name, URL, or description..."
                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm"
                >
            </div>

            <!-- Category Filter -->
            <div>
                <label for="category" class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                <select 
                    name="category" 
                    id="category"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm"
                >
                    <option value="">All Categories</option>
                    <option value="news" {{ ($filters['category'] ?? '') === 'news' ? 'selected' : '' }}>News</option>
                    <option value="blog" {{ ($filters['category'] ?? '') === 'blog' ? 'selected' : '' }}>Blog</option>
                    <option value="academic" {{ ($filters['category'] ?? '') === 'academic' ? 'selected' : '' }}>Academic</option>
                    <option value="government" {{ ($filters['category'] ?? '') === 'government' ? 'selected' : '' }}>Government</option>
                    <option value="other" {{ ($filters['category'] ?? '') === 'other' ? 'selected' : '' }}>Other</option>
                </select>
            </div>

            <!-- Credibility Filter -->
            <div>
                <label for="min_credibility" class="block text-sm font-medium text-gray-700 mb-1">Min. Credibility</label>
                <select 
                    name="min_credibility" 
                    id="min_credibility"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm"
                >
                    <option value="">Any</option>
                    <option value="0.8" {{ ($filters['min_credibility'] ?? '') === '0.8' ? 'selected' : '' }}>Very High (80%+)</option>
                    <option value="0.6" {{ ($filters['min_credibility'] ?? '') === '0.6' ? 'selected' : '' }}>High (60%+)</option>
                    <option value="0.4" {{ ($filters['min_credibility'] ?? '') === '0.4' ? 'selected' : '' }}>Medium (40%+)</option>
                </select>
            </div>

            <!-- Sort -->
            <div>
                <label for="sort" class="block text-sm font-medium text-gray-700 mb-1">Sort By</label>
                <select 
                    name="sort" 
                    id="sort"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm"
                >
                    <option value="credibility_score" {{ ($filters['sort'] ?? '') === 'credibility_score' ? 'selected' : '' }}>Credibility</option>
                    <option value="articles_count" {{ ($filters['sort'] ?? '') === 'articles_count' ? 'selected' : '' }}>Article Count</option>
                    <option value="name" {{ ($filters['sort'] ?? '') === 'name' ? 'selected' : '' }}>Name</option>
                    <option value="created_at" {{ ($filters['sort'] ?? '') === 'created_at' ? 'selected' : '' }}>Added Date</option>
                </select>
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
                Showing {{ $sources->count() }} of {{ $sources->total() }} sources
            </p>
            
            <!-- Active Filter Toggle -->
            <div class="flex items-center space-x-4">
                <label class="flex items-center">
                    <input 
                        type="checkbox" 
                        name="active" 
                        value="1"
                        {{ ($filters['active'] ?? '') === '1' ? 'checked' : '' }}
                        onchange="this.form.submit()"
                        class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                    >
                    <span class="ml-2 text-sm text-gray-600">Recently Active Only</span>
                </label>
            </div>
        </div>

        <!-- Sources Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @forelse($sources as $source)
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-gray-900 mb-1">
                                <a href="{{ route('sources.show', $source) }}" class="hover:text-blue-600">
                                    {{ $source->name }}
                                </a>
                            </h3>
                            <p class="text-sm text-gray-600">{{ parse_url($source->url, PHP_URL_HOST) }}</p>
                        </div>
                        
                        <!-- Credibility Score -->
                        @if($source->credibility_score)
                            @php
                                $scorePercent = round($source->credibility_score);
                                $scoreColor = $scorePercent >= 70 ? 'green' : ($scorePercent >= 40 ? 'yellow' : 'red');
                            @endphp
                            <div class="text-center">
                                <div class="text-lg font-bold text-{{ $scoreColor }}-600">{{ $scorePercent }}%</div>
                                <div class="text-xs text-gray-500">Credibility</div>
                            </div>
                        @endif
                    </div>

                    <!-- Description -->
                    @if($source->description)
                        <p class="text-gray-700 text-sm mb-4">{{ Str::limit($source->description, 100) }}</p>
                    @endif

                    <!-- Stats -->
                    <div class="grid grid-cols-3 gap-4 text-center text-sm">
                        <div>
                            <div class="font-semibold text-gray-900">{{ number_format($source->articles_count) }}</div>
                            <div class="text-gray-500">Articles</div>
                        </div>
                        <div>
                            <div class="font-semibold text-gray-900">{{ number_format($source->crawl_jobs_count) }}</div>
                            <div class="text-gray-500">Crawl Jobs</div>
                        </div>
                        <div>
                            <div class="font-semibold text-gray-900 capitalize">{{ $source->category ?? 'Unknown' }}</div>
                            <div class="text-gray-500">Type</div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <a 
                            href="{{ route('sources.show', $source) }}" 
                            class="text-blue-600 hover:text-blue-700 text-sm font-medium"
                        >
                            View Details →
                        </a>
                    </div>
                </div>
            @empty
                <div class="col-span-full text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9.5a2 2 0 00-2-2h-2m-4-3v9m0 0h9m-9 0l9-9"/>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No sources found</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        No sources match your current filters. Try adjusting your search criteria.
                    </p>
                </div>
            @endforelse
        </div>

        <!-- Pagination -->
        @if($sources->hasPages())
            <div class="mt-8">
                {{ $sources->links() }}
            </div>
        @endif
    </div>
</div>
@endsection