@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Breadcrumb -->
    <nav class="flex mb-8" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-3">
            <li class="inline-flex items-center">
                <a href="{{ route('home') }}" class="text-gray-700 hover:text-blue-600">
                    Home
                </a>
            </li>
            <li>
                <div class="flex items-center">
                    <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 111.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                    </svg>
                    <a href="{{ route('articles.index') }}" class="ml-1 text-gray-700 hover:text-blue-600 md:ml-2">
                        Articles
                    </a>
                </div>
            </li>
            <li aria-current="page">
                <div class="flex items-center">
                    <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 111.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                    </svg>
                    <span class="ml-1 text-gray-500 md:ml-2">{{ Str::limit($article->title, 50) }}</span>
                </div>
            </li>
        </ol>
    </nav>

    <!-- Article Header -->
    <header class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-4 leading-tight">
            {{ $article->title }}
        </h1>

        <!-- Article Metadata -->
        <div class="flex flex-wrap items-center gap-4 text-gray-600 mb-6">
            <div class="flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 011 1l4 4v9a2 2 0 01-2 2z"/>
                </svg>
                <a href="{{ route('sources.show', $article->source) }}" class="hover:text-blue-600 font-medium">
                    {{ $article->source->name }}
                </a>
            </div>

            @if($article->author)
                <div class="flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    <span>{{ $article->author }}</span>
                </div>
            @endif

            @if($article->published_at)
                <div class="flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <span>{{ $article->published_at->format('F j, Y g:i A') }}</span>
                </div>
            @endif
        </div>

        <!-- Quality Score and Actions -->
        <div class="flex items-center justify-between bg-gray-50 rounded-lg p-4">
            <div class="flex items-center space-x-6">
                @if($article->quality_score)
                    @php
                        $qualityColor = $article->quality_score >= 70 ? 'green' : ($article->quality_score >= 40 ? 'yellow' : 'red');
                        $qualityLabel = $article->quality_score >= 80 ? 'Excellent' : 
                                      ($article->quality_score >= 60 ? 'Good' : 
                                      ($article->quality_score >= 40 ? 'Fair' : 'Poor'));
                    @endphp
                    <div class="flex items-center">
                        <div class="flex items-center mr-3">
                            <svg class="w-5 h-5 mr-1 text-{{ $qualityColor }}-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <span class="text-{{ $qualityColor }}-700 font-medium">{{ $qualityLabel }}</span>
                        </div>
                        <div class="bg-gray-200 rounded-full h-2 w-24 mr-2">
                            <div class="bg-{{ $qualityColor }}-500 h-2 rounded-full" style="width: {{ $article->quality_score }}%"></div>
                        </div>
                        <span class="text-sm text-gray-600">{{ $article->quality_score }}%</span>
                    </div>
                @endif

                <div class="text-sm text-gray-500">
                    Added {{ $article->created_at?->diffForHumans() ?? 'Unknown' }}
                </div>
            </div>

            <div class="flex space-x-3">
                @if($article->url)
                    <a 
                        href="{{ $article->url }}" 
                        target="_blank"
                        class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                    >
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                        </svg>
                        View Original
                    </a>
                @endif

                <button 
                    onclick="showVerificationModal()"
                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                >
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Re-verify
                </button>
            </div>
        </div>
    </header>

    <!-- Article Content -->
    <main>
        @if($article->content)
            <div class="prose prose-lg max-w-none mb-8">
                {!! nl2br(e($article->content)) !!}
            </div>
        @else
            <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4 mb-8">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-yellow-800">Content Not Available</h3>
                        <p class="mt-1 text-sm text-yellow-700">
                            The article content is not available in our system. You can view the original article using the link above.
                        </p>
                    </div>
                </div>
            </div>
        @endif

        <!-- Verification Results -->
        @if($verificationResults->count() > 0)
            <div class="bg-white border border-gray-200 rounded-lg p-6 mb-8">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Verification History</h2>
                
                <div class="space-y-4">
                    @foreach($verificationResults as $result)
                        <div class="border-l-4 border-l-{{ $result->overall_score >= 70 ? 'green' : ($result->overall_score >= 40 ? 'yellow' : 'red') }}-400 pl-4">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center space-x-3">
                                    <span class="text-sm font-medium text-gray-900">
                                        Verification {{ $result->created_at->format('M j, Y g:i A') }}
                                    </span>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $result->overall_score >= 70 ? 'green' : ($result->overall_score >= 40 ? 'yellow' : 'red') }}-100 text-{{ $result->overall_score >= 70 ? 'green' : ($result->overall_score >= 40 ? 'yellow' : 'red') }}-800">
                                        {{ $result->overall_score }}% Confidence
                                    </span>
                                </div>
                            </div>
                            
                            @if($result->details)
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                                    @foreach($result->details as $key => $value)
                                        <div>
                                            <span class="font-medium text-gray-700">{{ ucfirst(str_replace('_', ' ', $key)) }}:</span>
                                            <span class="text-gray-600 ml-1">{{ is_array($value) ? implode(', ', $value) : $value }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Related Articles -->
        @if($relatedArticles->count() > 0)
            <div class="bg-white border border-gray-200 rounded-lg p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Related Articles</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    @foreach($relatedArticles as $related)
                        <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                            <h3 class="font-medium text-gray-900 mb-2">
                                <a href="{{ route('articles.show', $related) }}" class="hover:text-blue-600">
                                    {{ Str::limit($related->title, 80) }}
                                </a>
                            </h3>
                            
                            <div class="flex items-center justify-between text-sm text-gray-500">
                                <span>{{ $related->source->name }}</span>
                                @if($related->published_at)
                                    <span>{{ $related->published_at->format('M j, Y') }}</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </main>
</div>

<!-- Verification Modal -->
<div id="verificationModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
        
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                        <svg class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                        <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                            Re-verify Article
                        </h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-500">
                                This will run a new verification check on this article using our latest verification algorithms.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <form action="{{ route('verification.verify-article', $article) }}" method="POST" class="w-full sm:w-auto">
                    @csrf
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Start Verification
                    </button>
                </form>
                <button type="button" onclick="hideVerificationModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function showVerificationModal() {
    document.getElementById('verificationModal').classList.remove('hidden');
}

function hideVerificationModal() {
    document.getElementById('verificationModal').classList.add('hidden');
}

// Close modal when clicking outside
document.getElementById('verificationModal').addEventListener('click', function(e) {
    if (e.target === this) {
        hideVerificationModal();
    }
});
</script>
@endsection