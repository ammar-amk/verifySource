<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>VerifySource - Content Management Dashboard</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 dark:bg-gray-900">
    <div class="min-h-screen">
        <!-- Header -->
        <header class="bg-white dark:bg-gray-800 shadow">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-6">
                    <div class="flex items-center">
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                            VerifySource Dashboard
                        </h1>
                    </div>
                    <nav class="flex space-x-4">
                        <a href="/" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                            Home
                        </a>
                        <a href="/verification" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                            Verify Content
                        </a>
                        <a href="/content/dashboard" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-200">
                            Dashboard
                        </a>
                    </nav>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
            <!-- Stats Overview -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Articles</p>
                            <p class="text-2xl font-semibold text-gray-900 dark:text-white" id="total-articles">-</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Processed</p>
                            <p class="text-2xl font-semibold text-gray-900 dark:text-white" id="processed-articles">-</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-yellow-500 rounded-md flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Duplicates</p>
                            <p class="text-2xl font-semibold text-gray-900 dark:text-white" id="duplicate-articles">-</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-purple-500 rounded-md flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Active Sources</p>
                            <p class="text-2xl font-semibold text-gray-900 dark:text-white" id="active-sources">-</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content Management Tabs -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                <div class="border-b border-gray-200 dark:border-gray-700">
                    <nav class="-mb-px flex space-x-8 px-6">
                        <button id="articles-tab" class="py-4 px-1 border-b-2 border-blue-500 font-medium text-sm text-blue-600 dark:text-blue-400">
                            Articles
                        </button>
                        <button id="sources-tab" class="py-4 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300">
                            Sources
                        </button>
                        <button id="verification-tab" class="py-4 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300">
                            Verification Stats
                        </button>
                    </nav>
                </div>

                <!-- Articles Tab Content -->
                <div id="articles-content" class="p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Recent Articles</h2>
                        <div class="flex space-x-4">
                            <input type="text" id="search-articles" placeholder="Search articles..." class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            <button id="refresh-articles" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                Refresh
                            </button>
                        </div>
                    </div>
                    <div id="articles-list" class="space-y-4">
                        <!-- Articles will be loaded here -->
                    </div>
                </div>

                <!-- Sources Tab Content -->
                <div id="sources-content" class="p-6 hidden">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Source Management</h2>
                        <div class="flex space-x-4">
                            <input type="text" id="search-sources" placeholder="Search sources..." class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            <button id="refresh-sources" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                Refresh
                            </button>
                        </div>
                    </div>
                    <div id="sources-list" class="space-y-4">
                        <!-- Sources will be loaded here -->
                    </div>
                </div>

                <!-- Verification Tab Content -->
                <div id="verification-content" class="p-6 hidden">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Verification Statistics</h2>
                    <div id="verification-stats" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Verification stats will be loaded here -->
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Tab switching functionality
        function switchTab(tabName) {
            // Hide all content
            document.querySelectorAll('[id$="-content"]').forEach(content => {
                content.classList.add('hidden');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('[id$="-tab"]').forEach(tab => {
                tab.className = 'py-4 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300';
            });
            
            // Show selected content and activate tab
            document.getElementById(tabName + '-content').classList.remove('hidden');
            document.getElementById(tabName + '-tab').className = 'py-4 px-1 border-b-2 border-blue-500 font-medium text-sm text-blue-600 dark:text-blue-400';
        }

        // Tab event listeners
        document.getElementById('articles-tab').addEventListener('click', () => switchTab('articles'));
        document.getElementById('sources-tab').addEventListener('click', () => switchTab('sources'));
        document.getElementById('verification-tab').addEventListener('click', () => switchTab('verification'));

        // Load dashboard data
        async function loadDashboardData() {
            try {
                const response = await fetch('/api/v1/content/stats');
                const data = await response.json();
                
                if (data.content_stats) {
                    document.getElementById('total-articles').textContent = data.content_stats.total_articles;
                    document.getElementById('processed-articles').textContent = data.content_stats.processed_articles;
                    document.getElementById('duplicate-articles').textContent = data.content_stats.duplicate_articles;
                    document.getElementById('active-sources').textContent = data.content_stats.active_sources;
                }
            } catch (error) {
                console.error('Error loading dashboard data:', error);
            }
        }

        // Load articles
        async function loadArticles() {
            try {
                const response = await fetch('/api/v1/content/recent');
                const data = await response.json();
                
                if (data.success && data.articles) {
                    const articlesList = document.getElementById('articles-list');
                    articlesList.innerHTML = '';
                    
                    data.articles.forEach(article => {
                        const articleElement = document.createElement('div');
                        articleElement.className = 'border border-gray-200 dark:border-gray-700 rounded-lg p-4';
                        articleElement.innerHTML = `
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">${article.title}</h3>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">${article.source.name} • ${new Date(article.published_at).toLocaleDateString()}</p>
                                    <p class="text-sm text-gray-600 dark:text-gray-300 mt-2">${article.excerpt || 'No excerpt available'}</p>
                                </div>
                                <div class="ml-4 flex space-x-2">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${article.is_processed ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'}">
                                        ${article.is_processed ? 'Processed' : 'Pending'}
                                    </span>
                                    ${article.is_duplicate ? '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Duplicate</span>' : ''}
                                </div>
                            </div>
                        `;
                        articlesList.appendChild(articleElement);
                    });
                }
            } catch (error) {
                console.error('Error loading articles:', error);
            }
        }

        // Load sources
        async function loadSources() {
            try {
                const response = await fetch('/api/v1/sources/active');
                const data = await response.json();
                
                if (data.success && data.sources) {
                    const sourcesList = document.getElementById('sources-list');
                    sourcesList.innerHTML = '';
                    
                    data.sources.forEach(source => {
                        const sourceElement = document.createElement('div');
                        sourceElement.className = 'border border-gray-200 dark:border-gray-700 rounded-lg p-4';
                        sourceElement.innerHTML = `
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">${source.name}</h3>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">${source.domain}</p>
                                    <p class="text-sm text-gray-600 dark:text-gray-300 mt-2">${source.description || 'No description available'}</p>
                                </div>
                                <div class="ml-4 flex flex-col items-end space-y-2">
                                    <div class="flex items-center space-x-2">
                                        <span class="text-sm font-medium text-gray-900 dark:text-white">Credibility:</span>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${source.credibility_score >= 0.8 ? 'bg-green-100 text-green-800' : source.credibility_score >= 0.6 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'}">
                                            ${(source.credibility_score * 100).toFixed(0)}%
                                        </span>
                                    </div>
                                    <div class="flex space-x-2">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${source.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}">
                                            ${source.is_active ? 'Active' : 'Inactive'}
                                        </span>
                                        ${source.is_verified ? '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">Verified</span>' : ''}
                                    </div>
                                </div>
                            </div>
                        `;
                        sourcesList.appendChild(sourceElement);
                    });
                }
            } catch (error) {
                console.error('Error loading sources:', error);
            }
        }

        // Load verification stats
        async function loadVerificationStats() {
            try {
                const response = await fetch('/api/v1/content/stats');
                const data = await response.json();
                
                if (data.verification_stats) {
                    const statsContainer = document.getElementById('verification-stats');
                    statsContainer.innerHTML = `
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Verification Requests</h3>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600 dark:text-gray-300">Total Requests:</span>
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">${data.verification_stats.total_requests}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600 dark:text-gray-300">Completed:</span>
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">${data.verification_stats.completed_requests}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600 dark:text-gray-300">Pending:</span>
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">${data.verification_stats.pending_requests}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600 dark:text-gray-300">Failed:</span>
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">${data.verification_stats.failed_requests}</span>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Verification Results</h3>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600 dark:text-gray-300">Total Results:</span>
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">${data.verification_stats.total_results}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600 dark:text-gray-300">Exact Matches:</span>
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">${data.verification_stats.exact_matches}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600 dark:text-gray-300">Similar Matches:</span>
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">${data.verification_stats.similar_matches}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600 dark:text-gray-300">Avg Confidence:</span>
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">${(data.verification_stats.average_confidence * 100).toFixed(1)}%</span>
                                </div>
                            </div>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Error loading verification stats:', error);
            }
        }

        // Event listeners
        document.getElementById('refresh-articles').addEventListener('click', loadArticles);
        document.getElementById('refresh-sources').addEventListener('click', loadSources);

        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', function() {
            loadDashboardData();
            loadArticles();
        });

        // Load data when switching tabs
        document.getElementById('sources-tab').addEventListener('click', loadSources);
        document.getElementById('verification-tab').addEventListener('click', loadVerificationStats);
    </script>
</body>
</html>
