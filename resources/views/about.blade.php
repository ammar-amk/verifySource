@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Hero Section -->
    <div class="text-center mb-12">
        <h1 class="text-4xl font-bold text-gray-900 mb-4">About VerifySource</h1>
        <p class="text-xl text-gray-600 max-w-3xl mx-auto">
            A comprehensive platform for content verification, source credibility assessment, and information quality analysis.
        </p>
    </div>

    <!-- Mission Section -->
    <div class="bg-blue-50 rounded-lg p-8 mb-12">
        <h2 class="text-2xl font-bold text-gray-900 mb-4">Our Mission</h2>
        <p class="text-gray-700 leading-relaxed mb-4">
            In an era of information overload and misinformation, VerifySource empowers users to make informed decisions 
            about content credibility. We provide advanced verification tools and source analysis to help distinguish 
            reliable information from unreliable content.
        </p>
        <p class="text-gray-700 leading-relaxed">
            Our platform combines automated analysis with comprehensive source tracking to deliver actionable insights 
            about content quality and source reliability.
        </p>
    </div>

    <!-- Features Grid -->
    <div class="mb-12">
        <h2 class="text-2xl font-bold text-gray-900 mb-8 text-center">Platform Features</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <!-- Content Verification -->
            <div class="bg-white border border-gray-200 rounded-lg p-6">
                <div class="flex items-center mb-4">
                    <svg class="w-8 h-8 text-blue-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <h3 class="text-lg font-semibold text-gray-900">Content Verification</h3>
                </div>
                <p class="text-gray-600 text-sm">
                    Submit text, URLs, or files for comprehensive verification analysis including factual accuracy, 
                    bias detection, and quality scoring.
                </p>
            </div>

            <!-- Source Analysis -->
            <div class="bg-white border border-gray-200 rounded-lg p-6">
                <div class="flex items-center mb-4">
                    <svg class="w-8 h-8 text-green-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 011 1l4 4v9a2 2 0 01-2 2z"/>
                    </svg>
                    <h3 class="text-lg font-semibold text-gray-900">Source Credibility</h3>
                </div>
                <p class="text-gray-600 text-sm">
                    Evaluate source reliability through credibility scoring, historical accuracy tracking, 
                    and comprehensive source profiling.
                </p>
            </div>

            <!-- Real-time Analysis -->
            <div class="bg-white border border-gray-200 rounded-lg p-6">
                <div class="flex items-center mb-4">
                    <svg class="w-8 h-8 text-purple-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    <h3 class="text-lg font-semibold text-gray-900">Real-time Processing</h3>
                </div>
                <p class="text-gray-600 text-sm">
                    Get instant verification results with real-time processing capabilities and 
                    live updates on verification status.
                </p>
            </div>

            <!-- Content Discovery -->
            <div class="bg-white border border-gray-200 rounded-lg p-6">
                <div class="flex items-center mb-4">
                    <svg class="w-8 h-8 text-orange-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <h3 class="text-lg font-semibold text-gray-900">Content Discovery</h3>
                </div>
                <p class="text-gray-600 text-sm">
                    Browse and search through our indexed content library with advanced filtering 
                    and quality-based sorting options.
                </p>
            </div>

            <!-- API Access -->
            <div class="bg-white border border-gray-200 rounded-lg p-6">
                <div class="flex items-center mb-4">
                    <svg class="w-8 h-8 text-red-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <h3 class="text-lg font-semibold text-gray-900">API Integration</h3>
                </div>
                <p class="text-gray-600 text-sm">
                    Integrate verification capabilities into your applications with our comprehensive 
                    RESTful API and developer tools.
                </p>
            </div>

            <!-- Analytics -->
            <div class="bg-white border border-gray-200 rounded-lg p-6">
                <div class="flex items-center mb-4">
                    <svg class="w-8 h-8 text-indigo-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                    <h3 class="text-lg font-semibold text-gray-900">Analytics Dashboard</h3>
                </div>
                <p class="text-gray-600 text-sm">
                    Access detailed analytics and insights about content patterns, source performance, 
                    and verification trends over time.
                </p>
            </div>
        </div>
    </div>

    <!-- How It Works Section -->
    <div class="bg-gray-50 rounded-lg p-8 mb-12">
        <h2 class="text-2xl font-bold text-gray-900 mb-8 text-center">How VerifySource Works</h2>
        
        <div class="space-y-8">
            <div class="flex items-start">
                <div class="flex-shrink-0 w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold mr-4">
                    1
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Content Submission</h3>
                    <p class="text-gray-600">
                        Submit content for verification through multiple channels: direct text input, URL analysis, 
                        or file upload. Our system accepts various content formats and sources.
                    </p>
                </div>
            </div>

            <div class="flex items-start">
                <div class="flex-shrink-0 w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold mr-4">
                    2
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Automated Analysis</h3>
                    <p class="text-gray-600">
                        Our verification engine processes the content using advanced algorithms to analyze 
                        factual accuracy, detect bias, assess source credibility, and evaluate overall quality.
                    </p>
                </div>
            </div>

            <div class="flex items-start">
                <div class="flex-shrink-0 w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold mr-4">
                    3
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Source Evaluation</h3>
                    <p class="text-gray-600">
                        The system evaluates the source credibility by analyzing historical accuracy, 
                        editorial standards, transparency, and other reliability factors.
                    </p>
                </div>
            </div>

            <div class="flex items-start">
                <div class="flex-shrink-0 w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold mr-4">
                    4
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Results & Insights</h3>
                    <p class="text-gray-600">
                        Receive comprehensive verification results including confidence scores, detailed analysis, 
                        source credibility assessment, and actionable recommendations.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- API Information -->
    <div class="mb-12">
        <h2 class="text-2xl font-bold text-gray-900 mb-6">API Documentation</h2>
        
        <div class="bg-white border border-gray-200 rounded-lg p-6">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Available Endpoints</h3>
                    
                    <div class="space-y-3 text-sm">
                        <div class="flex items-center">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 mr-3">
                                GET
                            </span>
                            <code class="text-gray-800">/api/sources</code>
                            <span class="text-gray-500 ml-2">- List sources</span>
                        </div>
                        
                        <div class="flex items-center">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 mr-3">
                                GET
                            </span>
                            <code class="text-gray-800">/api/articles</code>
                            <span class="text-gray-500 ml-2">- List articles</span>
                        </div>
                        
                        <div class="flex items-center">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 mr-3">
                                POST
                            </span>
                            <code class="text-gray-800">/api/verify</code>
                            <span class="text-gray-500 ml-2">- Submit verification</span>
                        </div>
                        
                        <div class="flex items-center">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 mr-3">
                                GET
                            </span>
                            <code class="text-gray-800">/api/verification/{id}</code>
                            <span class="text-gray-500 ml-2">- Check verification status</span>
                        </div>
                        
                        <div class="flex items-center">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 mr-3">
                                GET
                            </span>
                            <code class="text-gray-800">/api/search</code>
                            <span class="text-gray-500 ml-2">- Search content</span>
                        </div>
                    </div>
                </div>
                
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Example Request</h3>
                    
                    <div class="bg-gray-50 rounded p-4">
                        <pre class="text-sm text-gray-800 overflow-x-auto"><code>curl -X POST {{ request()->getSchemeAndHttpHost() }}/api/verify \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "content_type": "text",
    "content": "Your content to verify here"
  }'</code></pre>
                    </div>
                    
                    <div class="mt-4">
                        <h4 class="font-medium text-gray-900 mb-2">Response Format</h4>
                        <div class="bg-gray-50 rounded p-4">
                            <pre class="text-sm text-gray-800 overflow-x-auto"><code>{
  "success": true,
  "request_id": "uuid-here",
  "status": "processing",
  "message": "Verification request submitted"
}</code></pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Section -->
    <div class="bg-white border border-gray-200 rounded-lg p-8 mb-12">
        <h2 class="text-2xl font-bold text-gray-900 mb-6 text-center">Platform Statistics</h2>
        
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-8">
            <div class="text-center">
                <div class="text-3xl font-bold text-blue-600 mb-2">{{ number_format($stats['total_sources']) }}</div>
                <div class="text-gray-600 text-sm">Indexed Sources</div>
            </div>
            
            <div class="text-center">
                <div class="text-3xl font-bold text-green-600 mb-2">{{ number_format($stats['total_articles']) }}</div>
                <div class="text-gray-600 text-sm">Verified Articles</div>
            </div>
            
            <div class="text-center">
                <div class="text-3xl font-bold text-purple-600 mb-2">{{ number_format($stats['verification_requests']) }}</div>
                <div class="text-gray-600 text-sm">Verifications</div>
            </div>
            
            <div class="text-center">
                <div class="text-3xl font-bold text-orange-600 mb-2">{{ $stats['avg_quality_score'] }}%</div>
                <div class="text-gray-600 text-sm">Avg Quality Score</div>
            </div>
        </div>
    </div>

    <!-- Contact/Support Section -->
    <div class="text-center bg-blue-50 rounded-lg p-8">
        <h2 class="text-2xl font-bold text-gray-900 mb-4">Need Help?</h2>
        <p class="text-gray-600 mb-6 max-w-2xl mx-auto">
            Whether you're integrating our API, need technical support, or have questions about our verification process, 
            we're here to help you make the most of VerifySource.
        </p>
        
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="mailto:support@verifysource.example" class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
                Contact Support
            </a>
            
            <a href="{{ route('home') }}" class="inline-flex items-center px-6 py-3 border border-gray-300 text-base font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Try Verification
            </a>
        </div>
    </div>
</div>
@endsection