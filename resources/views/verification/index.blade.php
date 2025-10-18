<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>VerifySource - Content Verification</title>
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
                            VerifySource
                        </h1>
                    </div>
                    <nav class="flex space-x-4">
                        <a href="/" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                            Home
                        </a>
                        <a href="/verification" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-200">
                            Verify Content
                        </a>
                    </nav>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="max-w-4xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-4">
                    Verify Content Source
                </h2>
                <p class="text-lg text-gray-600 dark:text-gray-400">
                    Paste any text or URL to find the earliest known source and verify authenticity
                </p>
            </div>

            <!-- Verification Form -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-8">
                <form id="verification-form" class="space-y-6">
                    @csrf
                    
                    <!-- Input Type Toggle -->
                    <div class="flex space-x-4 mb-6">
                        <button type="button" id="text-tab" class="px-4 py-2 rounded-md font-medium text-sm transition-colors bg-blue-600 text-white">
                            Text Input
                        </button>
                        <button type="button" id="url-tab" class="px-4 py-2 rounded-md font-medium text-sm transition-colors bg-gray-200 text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600">
                            URL Input
                        </button>
                    </div>

                    <!-- Text Input -->
                    <div id="text-input" class="space-y-4">
                        <label for="input_text" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Paste your text here
                        </label>
                        <textarea 
                            id="input_text" 
                            name="input_text" 
                            rows="8" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                            placeholder="Paste the text you want to verify..."
                        ></textarea>
                    </div>

                    <!-- URL Input -->
                    <div id="url-input" class="space-y-4 hidden">
                        <label for="input_url" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Enter URL
                        </label>
                        <input 
                            type="url" 
                            id="input_url" 
                            name="input_url" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                            placeholder="https://example.com/article"
                        >
                    </div>

                    <!-- Submit Button -->
                    <div class="flex justify-center">
                        <button 
                            type="submit" 
                            class="px-8 py-3 bg-blue-600 text-white font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <span id="submit-text">Verify Content</span>
                            <span id="loading-text" class="hidden">Verifying...</span>
                        </button>
                    </div>
                </form>

                <!-- Results Section -->
                <div id="results" class="mt-8 hidden">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                        Verification Results
                    </h3>
                    <div id="results-content" class="space-y-4">
                        <!-- Results will be populated here -->
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Tab switching functionality
        document.getElementById('text-tab').addEventListener('click', function() {
            document.getElementById('text-input').classList.remove('hidden');
            document.getElementById('url-input').classList.add('hidden');
            document.getElementById('text-tab').className = 'px-4 py-2 rounded-md font-medium text-sm transition-colors bg-blue-600 text-white';
            document.getElementById('url-tab').className = 'px-4 py-2 rounded-md font-medium text-sm transition-colors bg-gray-200 text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600';
        });

        document.getElementById('url-tab').addEventListener('click', function() {
            document.getElementById('text-input').classList.add('hidden');
            document.getElementById('url-input').classList.remove('hidden');
            document.getElementById('url-tab').className = 'px-4 py-2 rounded-md font-medium text-sm transition-colors bg-blue-600 text-white';
            document.getElementById('text-tab').className = 'px-4 py-2 rounded-md font-medium text-sm transition-colors bg-gray-200 text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600';
        });

        // Form submission
        document.getElementById('verification-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitButton = this.querySelector('button[type="submit"]');
            const submitText = document.getElementById('submit-text');
            const loadingText = document.getElementById('loading-text');
            
            // Show loading state
            submitButton.disabled = true;
            submitText.classList.add('hidden');
            loadingText.classList.remove('hidden');
            
            try {
                const response = await fetch('/verification/verify', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || formData.get('_token')
                    }
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Show success message
                    document.getElementById('results').classList.remove('hidden');
                    document.getElementById('results-content').innerHTML = `
                        <div class="bg-green-50 border border-green-200 rounded-md p-4">
                            <p class="text-green-800">Verification request submitted successfully! Request ID: ${data.request_id}</p>
                        </div>
                    `;
                } else {
                    // Show error message
                    document.getElementById('results').classList.remove('hidden');
                    document.getElementById('results-content').innerHTML = `
                        <div class="bg-red-50 border border-red-200 rounded-md p-4">
                            <p class="text-red-800">Error: ${data.message || 'Verification failed'}</p>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Error:', error);
                document.getElementById('results').classList.remove('hidden');
                document.getElementById('results-content').innerHTML = `
                    <div class="bg-red-50 border border-red-200 rounded-md p-4">
                        <p class="text-red-800">An error occurred while processing your request.</p>
                    </div>
                `;
            } finally {
                // Reset button state
                submitButton.disabled = false;
                submitText.classList.remove('hidden');
                loadingText.classList.add('hidden');
            }
        });
    </script>
</body>
</html>
