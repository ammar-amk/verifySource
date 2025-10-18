@extends('layouts.app')

@section('content')
<div class="bg-gradient-to-br from-blue-50 to-indigo-100">
    <!-- Hero Section -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <div class="text-center">
            <h1 class="text-4xl sm:text-5xl font-bold text-gray-900 mb-6">
                Verify Content <span class="text-blue-600">Authenticity</span>
            </h1>
            <p class="text-xl text-gray-600 mb-8 max-w-3xl mx-auto">
                Trace the original source of online content, verify its authenticity, and combat misinformation 
                with our open-source content verification platform.
            </p>
            
            <!-- Key Features -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
                <div class="bg-white p-6 rounded-lg shadow-sm">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mx-auto mb-4">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Source Verification</h3>
                    <p class="text-gray-600 text-sm">Identify the earliest known publication and trace content origins</p>
                </div>
                
                <div class="bg-white p-6 rounded-lg shadow-sm">
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mx-auto mb-4">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Instant Analysis</h3>
                    <p class="text-gray-600 text-sm">Get real-time verification results with confidence scores</p>
                </div>
                
                <div class="bg-white p-6 rounded-lg shadow-sm">
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mx-auto mb-4">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Privacy First</h3>
                    <p class="text-gray-600 text-sm">No tracking, no ads, completely open-source and transparent</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Verification Form Section -->
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
    <div class="bg-white rounded-xl shadow-lg p-8">
        <div class="text-center mb-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Verify Content Now</h2>
            <p class="text-gray-600">Paste any text, URL, or upload a file to verify its authenticity and trace its origins</p>
        </div>

        <!-- Verification Form -->
        @livewire('verification-form')
    </div>
</div>

<!-- How It Works Section -->
<div class="bg-gray-50 py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <h2 class="text-3xl font-bold text-gray-900 mb-4">How VerifySource Works</h2>
            <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                Our comprehensive verification process combines multiple techniques to provide accurate content analysis
            </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
            <!-- Process Steps -->
            <div class="space-y-8">
                <div class="flex items-start space-x-4">
                    <div class="flex-shrink-0 w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center text-sm font-semibold">
                        1
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Content Analysis</h3>
                        <p class="text-gray-600">We analyze your submitted content using advanced text processing and generate a unique content fingerprint.</p>
                    </div>
                </div>

                <div class="flex items-start space-x-4">
                    <div class="flex-shrink-0 w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center text-sm font-semibold">
                        2
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Database Search</h3>
                        <p class="text-gray-600">Search our indexed database of millions of articles using both full-text and semantic similarity matching.</p>
                    </div>
                </div>

                <div class="flex items-start space-x-4">
                    <div class="flex-shrink-0 w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center text-sm font-semibold">
                        3
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Provenance Analysis</h3>
                        <p class="text-gray-600">Analyze publication patterns, identify original sources, and detect content propagation across the web.</p>
                    </div>
                </div>

                <div class="flex items-start space-x-4">
                    <div class="flex-shrink-0 w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center text-sm font-semibold">
                        4
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">External Verification</h3>
                        <p class="text-gray-600">Cross-reference with external sources like the Internet Archive to verify timestamps and authenticity.</p>
                    </div>
                </div>

                <div class="flex items-start space-x-4">
                    <div class="flex-shrink-0 w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center text-sm font-semibold">
                        5
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Credibility Assessment</h3>
                        <p class="text-gray-600">Evaluate source trustworthiness, domain authority, and content quality to provide confidence scores.</p>
                    </div>
                </div>
            </div>

            <!-- Illustration -->
            <div class="bg-white rounded-xl p-8 shadow-sm">
                <div class="space-y-6">
                    <div class="text-center">
                        <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900">Content Input</h3>
                        <p class="text-gray-600 text-sm">Text, URL, or File</p>
                    </div>

                    <div class="flex justify-center">
                        <div class="h-8 w-px bg-gray-300"></div>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div class="text-center">
                            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mx-auto mb-2">
                                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                            </div>
                            <p class="text-xs text-gray-600">Search</p>
                        </div>
                        
                        <div class="text-center">
                            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mx-auto mb-2">
                                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                </svg>
                            </div>
                            <p class="text-xs text-gray-600">Analyze</p>
                        </div>
                        
                        <div class="text-center">
                            <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center mx-auto mb-2">
                                <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <p class="text-xs text-gray-600">Verify</p>
                        </div>
                    </div>

                    <div class="flex justify-center">
                        <div class="h-8 w-px bg-gray-300"></div>
                    </div>

                    <div class="text-center">
                        <div class="w-16 h-16 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900">Verification Report</h3>
                        <p class="text-gray-600 text-sm">Detailed Results & Evidence</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Section -->
<div class="py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <h2 class="text-3xl font-bold text-gray-900 mb-4">Platform Statistics</h2>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
            <!-- Sources Indexed -->
            <div class="text-center">
                <div class="text-3xl font-bold text-blue-600 mb-2">
                    {{ number_format(\App\Models\Source::count()) }}
                </div>
                <div class="text-gray-600">Sources Indexed</div>
            </div>

            <!-- Articles Processed -->
            <div class="text-center">
                <div class="text-3xl font-bold text-green-600 mb-2">
                    {{ number_format(\App\Models\Article::count()) }}
                </div>
                <div class="text-gray-600">Articles Processed</div>
            </div>

            <!-- Verifications -->
            <div class="text-center">
                <div class="text-3xl font-bold text-purple-600 mb-2">
                    {{ number_format(\App\Models\VerificationRequest::count()) }}
                </div>
                <div class="text-gray-600">Verifications Completed</div>
            </div>

            <!-- Content Hashes -->
            <div class="text-center">
                <div class="text-3xl font-bold text-orange-600 mb-2">
                    {{ number_format(\App\Models\ContentHash::count()) }}
                </div>
                <div class="text-gray-600">Unique Content Pieces</div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activity -->
<div class="bg-gray-50 py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <h2 class="text-3xl font-bold text-gray-900 mb-4">Recent Activity</h2>
            <p class="text-gray-600">Latest articles and sources added to our verification database</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Recent Articles -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Recently Added Articles</h3>
                <div class="space-y-4">
                    @php
                        $recentArticles = \App\Models\Article::with('source')
                            ->latest()
                            ->limit(5)
                            ->get();
                    @endphp
                    
                    @forelse($recentArticles as $article)
                        <div class="border-b border-gray-200 pb-3 last:border-b-0">
                            <h4 class="font-medium text-gray-900 text-sm mb-1">
                                <a href="{{ route('articles.show', $article) }}" class="hover:text-blue-600">
                                    {{ Str::limit($article->title, 60) }}
                                </a>
                            </h4>
                            <p class="text-gray-600 text-xs">
                                from {{ $article->source->name ?? 'Unknown Source' }} • 
                                {{ $article->published_at?->diffForHumans() ?? $article->created_at->diffForHumans() }}
                            </p>
                        </div>
                    @empty
                        <p class="text-gray-500 text-sm">No articles yet. Content will appear here as it's indexed.</p>
                    @endforelse
                </div>
                
                @if($recentArticles->count() > 0)
                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <a href="{{ route('articles.index') }}" class="text-blue-600 hover:text-blue-700 text-sm font-medium">
                            View all articles →
                        </a>
                    </div>
                @endif
            </div>

            <!-- Recent Sources -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Recently Added Sources</h3>
                <div class="space-y-4">
                    @php
                        $recentSources = \App\Models\Source::withCount('articles')
                            ->latest()
                            ->limit(5)
                            ->get();
                    @endphp
                    
                    @forelse($recentSources as $source)
                        <div class="border-b border-gray-200 pb-3 last:border-b-0">
                            <h4 class="font-medium text-gray-900 text-sm mb-1">
                                <a href="{{ route('sources.show', $source) }}" class="hover:text-blue-600">
                                    {{ $source->name }}
                                </a>
                            </h4>
                            <p class="text-gray-600 text-xs">
                                {{ $source->articles_count }} articles • 
                                Added {{ $source->created_at->diffForHumans() }}
                            </p>
                        </div>
                    @empty
                        <p class="text-gray-500 text-sm">No sources yet. Sources will appear here as they're added.</p>
                    @endforelse
                </div>
                
                @if($recentSources->count() > 0)
                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <a href="{{ route('sources.index') }}" class="text-blue-600 hover:text-blue-700 text-sm font-medium">
                            View all sources →
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection