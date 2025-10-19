<div>
    @if(!$verificationComplete)
        <!-- Input Type Selection -->
        <div class="flex justify-center mb-6">
            <div class="flex bg-gray-100 rounded-lg p-1">
                <button 
                    wire:click="$set('inputType', 'text')"
                    class="px-4 py-2 rounded-md text-sm font-medium transition-colors {{ $inputType === 'text' ? 'bg-white text-blue-600 shadow-sm' : 'text-gray-600 hover:text-gray-900' }}"
                >
                    Text Content
                </button>
                <button 
                    wire:click="$set('inputType', 'url')"
                    class="px-4 py-2 rounded-md text-sm font-medium transition-colors {{ $inputType === 'url' ? 'bg-white text-blue-600 shadow-sm' : 'text-gray-600 hover:text-gray-900' }}"
                >
                    URL/Link
                </button>
                <button 
                    wire:click="$set('inputType', 'file')"
                    class="px-4 py-2 rounded-md text-sm font-medium transition-colors {{ $inputType === 'file' ? 'bg-white text-blue-600 shadow-sm' : 'text-gray-600 hover:text-gray-900' }}"
                >
                    File Upload
                </button>
            </div>
        </div>

        <!-- Form Content -->
        <form wire:submit.prevent="verify" class="space-y-6">
            <!-- Text Input -->
            @if($inputType === 'text')
                <div>
                    <label for="content" class="block text-sm font-medium text-gray-700 mb-2">
                        Content to Verify
                    </label>
                    <textarea 
                        wire:model="content" 
                        id="content"
                        rows="8" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 resize-vertical"
                        placeholder="Paste the text content you want to verify here..."
                    ></textarea>
                    @error('content') 
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    
                    @if($content)
                        <p class="mt-1 text-sm text-gray-500">
                            Character count: {{ strlen($content) }} / 50,000
                        </p>
                    @endif
                </div>
            @endif

            <!-- URL Input -->
            @if($inputType === 'url')
                <div>
                    <label for="url" class="block text-sm font-medium text-gray-700 mb-2">
                        URL to Verify
                    </label>
                    <input 
                        wire:model="url" 
                        type="url" 
                        id="url"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                        placeholder="https://example.com/article-to-verify"
                    >
                    @error('url') 
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    
                    <p class="mt-1 text-sm text-gray-500">
                        We'll extract and verify the content from this URL
                    </p>
                </div>
            @endif

            <!-- File Upload -->
            @if($inputType === 'file')
                <div>
                    <label for="file" class="block text-sm font-medium text-gray-700 mb-2">
                        File to Verify
                    </label>
                    <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md hover:border-gray-400 transition-colors">
                        <div class="space-y-1 text-center">
                            <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <div class="flex text-sm text-gray-600">
                                <label for="file" class="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500">
                                    <span>Upload a file</span>
                                    <input wire:model="file" id="file" type="file" class="sr-only" accept=".txt,.pdf,.doc,.docx">
                                </label>
                                <p class="pl-1">or drag and drop</p>
                            </div>
                            <p class="text-xs text-gray-500">
                                TXT, PDF, DOC, DOCX up to 10MB
                            </p>
                        </div>
                    </div>
                    
                    @if($file)
                        <div class="mt-2 flex items-center space-x-2 text-sm text-gray-600">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <span>{{ $file->getClientOriginalName() }}</span>
                            <span class="text-gray-400">({{ number_format($file->getSize() / 1024, 1) }} KB)</span>
                        </div>
                    @endif
                    
                    @error('file') 
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            @endif

            <!-- Submit Button -->
            <div class="flex justify-center">
                <button 
                    type="submit" 
                    class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    wire:loading.attr="disabled"
                    wire:target="verify"
                >
                    <span wire:loading.remove wire:target="verify">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Verify Content
                    </span>
                    <span wire:loading wire:target="verify" class="flex items-center">
                        <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Verifying...
                    </span>
                </button>
            </div>

            <!-- Loading State -->
            <div wire:loading wire:target="verify" class="text-center">
                <div class="inline-flex items-center text-blue-600">
                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span class="text-sm">Running comprehensive verification analysis...</span>
                </div>
            </div>
        </form>

        <!-- Error Message -->
        @if($errorMessage)
            <div class="mt-6 bg-red-50 border-l-4 border-red-400 p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800">Verification Error</h3>
                        <p class="mt-1 text-sm text-red-700">{{ $errorMessage }}</p>
                    </div>
                </div>
            </div>
        @endif
    @else
        <!-- Verification Results -->
        <div class="space-y-6">
            <!-- Success Header -->
            <div class="text-center">
                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">Verification Complete</h3>
                @if(isset($verificationResult['cached']) && $verificationResult['cached'])
                    <p class="text-gray-600 text-sm mb-2">
                        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-blue-100 text-blue-800">
                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                            Cached Result
                        </span>
                    </p>
                @endif
            </div>

            <!-- Overall Confidence Score -->
            <div class="bg-{{ $this->confidenceColor }}-50 border border-{{ $this->confidenceColor }}-200 rounded-lg p-6">
                <div class="text-center">
                    <div class="text-3xl font-bold text-{{ $this->confidenceColor }}-600 mb-2">
                        {{ round(($verificationResult['overall_confidence'] ?? 0) * 100) }}%
                    </div>
                    <div class="text-lg font-medium text-{{ $this->confidenceColor }}-800 mb-1">
                        {{ $this->confidenceLevel }} Confidence
                    </div>
                    <div class="text-sm text-{{ $this->confidenceColor }}-700">
                        Status: {{ ucfirst($verificationResult['status'] ?? 'Unknown') }}
                    </div>
                </div>
            </div>

            <!-- Key Findings -->
            @if(isset($verificationResult['findings']) && count($verificationResult['findings']) > 0)
                <div class="bg-white border border-gray-200 rounded-lg p-6">
                    <h4 class="text-lg font-semibold text-gray-900 mb-4">Key Findings</h4>
                    <div class="space-y-3">
                        @foreach(array_slice($verificationResult['findings'], 0, 5) as $finding)
                            <div class="flex items-start space-x-3">
                                <div class="flex-shrink-0 w-2 h-2 bg-blue-400 rounded-full mt-2"></div>
                                <div class="flex-1">
                                    <p class="text-gray-900">{{ $finding['description'] ?? $finding['type'] ?? 'Unknown finding' }}</p>
                                    @if(isset($finding['confidence']))
                                        <p class="text-sm text-gray-500">Confidence: {{ round($finding['confidence'] * 100) }}%</p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Evidence Summary -->
            @if(isset($verificationResult['evidence_summary']) && !empty($verificationResult['evidence_summary']))
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-6">
                    <h4 class="text-lg font-semibold text-gray-900 mb-3">Evidence Summary</h4>
                    <p class="text-gray-700">{{ $verificationResult['evidence_summary'] }}</p>
                </div>
            @endif

            <!-- Recommendations -->
            @if(isset($verificationResult['recommendations']) && count($verificationResult['recommendations']) > 0)
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6">
                    <h4 class="text-lg font-semibold text-gray-900 mb-4">Recommendations</h4>
                    <ul class="space-y-2">
                        @foreach($verificationResult['recommendations'] as $recommendation)
                            <li class="flex items-start space-x-2">
                                <svg class="w-4 h-4 text-yellow-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                                </svg>
                                <span class="text-yellow-800">{{ $recommendation }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Action Buttons -->
            <div class="flex justify-center space-x-4">
                <button 
                    wire:click="newVerification"
                    class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                >
                    Verify New Content
                </button>
                
                @if(isset($verificationResult['search_analysis']['total_matches']) && $verificationResult['search_analysis']['total_matches'] > 0)
                    <a href="#" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        View Detailed Results
                    </a>
                @endif
            </div>

            <!-- Processing Time -->
            @if(isset($verificationResult['processing_time']))
                <div class="text-center text-sm text-gray-500">
                    Verification completed in {{ $verificationResult['processing_time'] }}s
                </div>
            @endif
        </div>
    @endif
</div>
