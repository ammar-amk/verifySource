@extends('layouts.app')

@push('styles')
<style>
    .hero-gradient {
        background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 50%, #06b6d4 100%);
    }
    
    .metric-card {
        background: white;
        border-radius: 1rem;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border: 1px solid #e5e7eb;
    }
    
    .metric-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        border-color: #3b82f6;
    }
    
    .article-item {
        background: white;
        border: 1px solid #e5e7eb;
        border-radius: 0.75rem;
        padding: 1.5rem;
        transition: all 0.2s ease;
    }
    
    .article-item:hover {
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        border-color: #3b82f6;
    }
    
    .quality-bar {
        height: 8px;
        border-radius: 9999px;
        background: #e5e7eb;
        overflow: hidden;
        position: relative;
    }
    
    .quality-fill {
        height: 100%;
        transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1);
        border-radius: 9999px;
    }
    
    .pulse-dot {
        animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }
    
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }
    
    .score-ring {
        transform: rotate(-90deg);
        transition: stroke-dashoffset 1s ease-in-out;
    }
    
    .badge {
        display: inline-flex;
        align-items: center;
        padding: 0.375rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
        line-height: 1;
    }
</style>
@endpush

@section('content')
<div class="min-h-screen bg-gray-50">
    <!-- Hero Header -->
    <div class="hero-gradient text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div class="flex items-center text-sm mb-6 space-x-2 opacity-90">
                <a href="{{ route('home') }}" class="hover:underline">Home</a>
                <span>/</span>
                <a href="{{ route('sources.index') }}" class="hover:underline">Sources</a>
                <span>/</span>
                <span>{{ $source->name }}</span>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
                <div class="lg:col-span-2">
                    <div class="flex items-start gap-4">
                        <div class="flex-shrink-0 w-16 h-16 bg-white/20 backdrop-blur-sm rounded-2xl flex items-center justify-center">
                            <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M2 5a2 2 0 012-2h7a2 2 0 012 2v4a2 2 0 01-2 2H9l-3 3v-3H4a2 2 0 01-2-2V5z"></path>
                                <path d="M15 7v2a4 4 0 01-4 4H9.828l-1.766 1.767c.28.149.599.233.938.233h2l3 3v-3h2a2 2 0 002-2V9a2 2 0 00-2-2h-1z"></path>
                            </svg>
                        </div>
                        <div class="flex-1">
                            <h1 class="text-4xl font-black mb-3">{{ $source->name }}</h1>
                            <a href="{{ $source->url }}" target="_blank" class="inline-flex items-center gap-2 text-blue-100 hover:text-white transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path>
                                </svg>
                                <span class="text-sm font-medium">{{ $source->domain }}</span>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                </svg>
                            </a>
                            @if($source->description)
                                <p class="mt-4 text-blue-50 leading-relaxed">{{ $source->description }}</p>
                            @endif
                            
                            <div class="flex items-center gap-3 mt-4">
                                @if($source->is_active)
                                    <span class="badge bg-green-100 text-green-800">
                                        <span class="w-2 h-2 bg-green-500 rounded-full mr-2 pulse-dot"></span>
                                        Active
                                    </span>
                                @else
                                    <span class="badge bg-gray-100 text-gray-800">
                                        <span class="w-2 h-2 bg-gray-500 rounded-full mr-2"></span>
                                        Inactive
                                    </span>
                                @endif
                                
                                <span class="badge bg-white/20 text-white">
                                    <svg class="w-3 h-3 mr-1.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"></path>
                                    </svg>
                                    {{ $source->category ?? 'General' }}
                                </span>
                                
                                <span class="badge bg-white/20 text-white">
                                    <svg class="w-3 h-3 mr-1.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"></path>
                                    </svg>
                                    {{ $source->country ?? 'Unknown' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Credibility Score Circle -->
                <div class="flex justify-center lg:justify-end">
                    <div class="relative">
                        <svg class="w-40 h-40">
                            <circle cx="80" cy="80" r="70" fill="none" stroke="rgba(255,255,255,0.2)" stroke-width="12"></circle>
                            <circle class="score-ring" cx="80" cy="80" r="70" fill="none" 
                                    stroke="{{ $source->credibility_score >= 70 ? '#10b981' : ($source->credibility_score >= 40 ? '#f59e0b' : '#ef4444') }}" 
                                    stroke-width="12" 
                                    stroke-dasharray="{{ 2 * 3.14159 * 70 }}" 
                                    stroke-dashoffset="{{ 2 * 3.14159 * 70 * (1 - $source->credibility_score / 100) }}" 
                                    stroke-linecap="round"></circle>
                        </svg>
                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                            <div class="text-5xl font-black">{{ number_format($source->credibility_score, 0) }}</div>
                            <div class="text-xs font-semibold opacity-90 uppercase tracking-wide mt-1">
                                {{ $source->credibility_score >= 70 ? 'High Trust' : ($source->credibility_score >= 40 ? 'Moderate' : 'Low Trust') }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <!-- Key Metrics -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
            <div class="metric-card p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-bold text-gray-600 uppercase tracking-wider">Total Articles</h3>
                    <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                </div>
                <div class="text-4xl font-black text-gray-900">{{ number_format($source->articles_count ?? 0) }}</div>
                <p class="text-sm text-gray-500 mt-2">Published content pieces</p>
            </div>

            <div class="metric-card p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-bold text-gray-600 uppercase tracking-wider">Avg Quality</h3>
                    <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
                @if($recentArticles && $recentArticles->count() > 0)
                    <div class="text-4xl font-black text-gray-900">{{ number_format($recentArticles->avg('quality_score'), 0) }}%</div>
                    <div class="quality-bar mt-3">
                        <div class="quality-fill bg-gradient-to-r from-green-400 to-green-600" style="width: {{ $recentArticles->avg('quality_score') }}%"></div>
                    </div>
                @else
                    <div class="text-4xl font-black text-gray-400">N/A</div>
                    <p class="text-sm text-gray-500 mt-2">No data yet</p>
                @endif
            </div>

            <div class="metric-card p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-bold text-gray-600 uppercase tracking-wider">Coverage</h3>
                    <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"></path>
                        </svg>
                    </div>
                </div>
                <div class="flex items-baseline gap-2">
                    <div class="text-4xl font-black text-gray-900">{{ $source->country ?? '??' }}</div>
                    <div class="text-lg text-gray-500">/ {{ $source->category ?? 'General' }}</div>
                </div>
                <p class="text-sm text-gray-500 mt-2">Geographic & topical focus</p>
            </div>
        </div>

        <!-- Quality Analytics -->
        @if($recentArticles && $recentArticles->count() > 0)
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-12">
            <!-- Quality Distribution -->
            <div class="metric-card p-6">
                <h3 class="text-lg font-black text-gray-900 mb-6">Quality Distribution</h3>
                @php
                    $highQuality = $recentArticles->where('quality_score', '>=', 70)->count();
                    $medQuality = $recentArticles->whereBetween('quality_score', [40, 69])->count();
                    $lowQuality = $recentArticles->where('quality_score', '<', 40)->count();
                    $total = $recentArticles->count();
                @endphp
                
                <div class="space-y-5">
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                                <span class="text-sm font-semibold text-gray-700">High Quality (70-100%)</span>
                            </div>
                            <span class="text-lg font-black text-gray-900">{{ $highQuality }}</span>
                        </div>
                        <div class="quality-bar">
                            <div class="quality-fill bg-gradient-to-r from-green-400 to-green-600" style="width: {{ $total > 0 ? ($highQuality / $total * 100) : 0 }}%"></div>
                        </div>
                        <div class="text-xs text-gray-500 mt-1">{{ $total > 0 ? number_format($highQuality / $total * 100, 1) : 0 }}% of articles</div>
                    </div>
                    
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 bg-yellow-500 rounded-full"></div>
                                <span class="text-sm font-semibold text-gray-700">Medium Quality (40-69%)</span>
                            </div>
                            <span class="text-lg font-black text-gray-900">{{ $medQuality }}</span>
                        </div>
                        <div class="quality-bar">
                            <div class="quality-fill bg-gradient-to-r from-yellow-400 to-yellow-600" style="width: {{ $total > 0 ? ($medQuality / $total * 100) : 0 }}%"></div>
                        </div>
                        <div class="text-xs text-gray-500 mt-1">{{ $total > 0 ? number_format($medQuality / $total * 100, 1) : 0 }}% of articles</div>
                    </div>
                    
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 bg-red-500 rounded-full"></div>
                                <span class="text-sm font-semibold text-gray-700">Low Quality (0-39%)</span>
                            </div>
                            <span class="text-lg font-black text-gray-900">{{ $lowQuality }}</span>
                        </div>
                        <div class="quality-bar">
                            <div class="quality-fill bg-gradient-to-r from-red-400 to-red-600" style="width: {{ $total > 0 ? ($lowQuality / $total * 100) : 0 }}%"></div>
                        </div>
                        <div class="text-xs text-gray-500 mt-1">{{ $total > 0 ? number_format($lowQuality / $total * 100, 1) : 0 }}% of articles</div>
                    </div>
                </div>
            </div>

            <!-- Article Insights -->
            <div class="metric-card p-6">
                <h3 class="text-lg font-black text-gray-900 mb-6">Content Insights</h3>
                
                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-4">
                        <div class="text-3xl font-black text-blue-900 mb-1">{{ $recentArticles->whereNotNull('author')->count() }}</div>
                        <div class="text-xs font-semibold text-blue-700">With Authors</div>
                        <div class="text-xs text-blue-600 mt-1">
                            {{ $total > 0 ? number_format($recentArticles->whereNotNull('author')->count() / $total * 100, 0) : 0 }}% attribution
                        </div>
                    </div>
                    
                    <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl p-4">
                        <div class="text-3xl font-black text-purple-900 mb-1">{{ $recentArticles->whereNotNull('published_at')->count() }}</div>
                        <div class="text-xs font-semibold text-purple-700">Dated Articles</div>
                        <div class="text-xs text-purple-600 mt-1">
                            {{ $total > 0 ? number_format($recentArticles->whereNotNull('published_at')->count() / $total * 100, 0) : 0 }}% timestamped
                        </div>
                    </div>
                    
                    <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-xl p-4">
                        <div class="text-3xl font-black text-green-900 mb-1">{{ $recentArticles->whereNotNull('excerpt')->count() }}</div>
                        <div class="text-xs font-semibold text-green-700">With Excerpts</div>
                        <div class="text-xs text-green-600 mt-1">
                            {{ $total > 0 ? number_format($recentArticles->whereNotNull('excerpt')->count() / $total * 100, 0) : 0 }}% described
                        </div>
                    </div>
                    
                    <div class="bg-gradient-to-br from-orange-50 to-orange-100 rounded-xl p-4">
                        @php
                            $recentCount = $recentArticles->where('published_at', '>=', now()->subWeek())->count();
                        @endphp
                        <div class="text-3xl font-black text-orange-900 mb-1">{{ $recentCount }}</div>
                        <div class="text-xs font-semibold text-orange-700">This Week</div>
                        <div class="text-xs text-orange-600 mt-1">Latest activity</div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Recent Articles Section -->
        <div class="mb-6">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-2xl font-black text-gray-900">Recent Articles</h2>
                @if($recentArticles && $recentArticles->count() >= 10)
                    <a href="{{ route('articles.index', ['source' => $source->id]) }}" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg font-semibold text-sm hover:bg-blue-700 transition-colors">
                        View All Articles
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                        </svg>
                    </a>
                @endif
            </div>
        </div>
        
        @if($recentArticles && $recentArticles->count() > 0)
            <div class="space-y-4">
                @foreach($recentArticles as $article)
                    <div class="article-item group">
                        <div class="flex items-start gap-4">
                            <div class="flex-shrink-0 w-12 h-12 bg-gradient-to-br from-blue-500 to-purple-600 rounded-xl flex items-center justify-center text-white font-black text-lg">
                                {{ substr($article->title, 0, 1) }}
                            </div>
                            
                            <div class="flex-1 min-w-0">
                                <a href="{{ route('articles.show', $article->id) }}" class="text-xl font-bold text-gray-900 hover:text-blue-600 transition-colors block mb-2 group-hover:underline">
                                    {{ $article->title }}
                                </a>
                                
                                @if($article->excerpt)
                                    <p class="text-gray-600 text-sm leading-relaxed mb-3 line-clamp-2">{{ $article->excerpt }}</p>
                                @endif
                                
                                <div class="flex items-center gap-4 flex-wrap">
                                    @if($article->author)
                                        <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-600">
                                            <svg class="w-4 h-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                                            </svg>
                                            {{ $article->author }}
                                        </span>
                                    @endif
                                    
                                    @if($article->published_at)
                                        <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-500">
                                            <svg class="w-4 h-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"></path>
                                            </svg>
                                            {{ $article->published_at->format('M d, Y') }}
                                        </span>
                                    @endif
                                    
                                    @if($article->quality_score)
                                        <span class="badge {{ $article->quality_score >= 70 ? 'bg-green-100 text-green-800' : ($article->quality_score >= 40 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                            </svg>
                                            Quality: {{ $article->quality_score }}%
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="metric-card p-16 text-center">
                <div class="inline-flex items-center justify-center w-20 h-20 bg-gradient-to-br from-blue-100 to-purple-100 rounded-full mb-6">
                    <svg class="w-10 h-10 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
                <h3 class="text-2xl font-bold text-gray-900 mb-3">No Articles Yet</h3>
                <p class="text-gray-600 mb-2">No articles have been crawled from this source yet.</p>
                <p class="text-sm text-gray-500">Our crawlers are working to discover new content. Check back soon!</p>
            </div>
        @endif
    </div>
</div>
@endsection
