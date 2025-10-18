<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Content Verification' }} - VerifySource</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    
    <!-- Meta Tags -->
    <meta name="description" content="Verify the authenticity and trace the original source of online content with VerifySource - an open-source content verification platform.">
    <meta name="keywords" content="content verification, fact checking, source tracing, misinformation, provenance">
    
    <!-- Open Graph -->
    <meta property="og:title" content="{{ $title ?? 'Content Verification' }} - VerifySource">
    <meta property="og:description" content="Verify content authenticity and trace original sources">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    
    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="font-sans antialiased bg-gray-50 text-gray-900">
    <!-- Navigation -->
    <nav class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <!-- Logo -->
                <div class="flex items-center">
                    <a href="{{ route('home') }}" class="flex items-center space-x-2">
                        <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="text-xl font-bold text-gray-900">VerifySource</span>
                    </a>
                </div>

                <!-- Desktop Navigation -->
                <div class="hidden md:flex items-center space-x-6">
                    <a href="{{ route('home') }}" class="text-gray-600 hover:text-gray-900 px-3 py-2 text-sm font-medium {{ request()->routeIs('home') ? 'text-blue-600 border-b-2 border-blue-600' : '' }}">
                        Home
                    </a>
                    <a href="{{ route('sources.index') }}" class="text-gray-600 hover:text-gray-900 px-3 py-2 text-sm font-medium {{ request()->routeIs('sources.*') ? 'text-blue-600 border-b-2 border-blue-600' : '' }}">
                        Sources
                    </a>
                    <a href="{{ route('articles.index') }}" class="text-gray-600 hover:text-gray-900 px-3 py-2 text-sm font-medium {{ request()->routeIs('articles.*') ? 'text-blue-600 border-b-2 border-blue-600' : '' }}">
                        Articles
                    </a>
                    <a href="{{ route('about') }}" class="text-gray-600 hover:text-gray-900 px-3 py-2 text-sm font-medium {{ request()->routeIs('about') ? 'text-blue-600 border-b-2 border-blue-600' : '' }}">
                        About
                    </a>
                    
                    <!-- Search -->
                    <div class="relative">
                        <form action="{{ route('search') }}" method="GET" class="flex">
                            <input 
                                type="text" 
                                name="q" 
                                value="{{ request('q') }}"
                                placeholder="Search content..." 
                                class="w-64 px-3 py-1.5 text-sm border border-gray-300 rounded-l-md focus:ring-blue-500 focus:border-blue-500"
                            >
                            <button type="submit" class="px-3 py-1.5 bg-blue-600 text-white text-sm border border-blue-600 rounded-r-md hover:bg-blue-700 focus:ring-blue-500">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Mobile menu button -->
                <div class="md:hidden">
                    <button 
                        type="button" 
                        class="mobile-menu-button text-gray-600 hover:text-gray-900 p-2"
                        aria-label="Open main menu"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Mobile Navigation -->
            <div class="mobile-menu hidden md:hidden pb-4">
                <div class="space-y-2">
                    <a href="{{ route('home') }}" class="block px-3 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 {{ request()->routeIs('home') ? 'text-blue-600' : '' }}">
                        Home
                    </a>
                    <a href="{{ route('sources.index') }}" class="block px-3 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 {{ request()->routeIs('sources.*') ? 'text-blue-600' : '' }}">
                        Sources
                    </a>
                    <a href="{{ route('articles.index') }}" class="block px-3 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 {{ request()->routeIs('articles.*') ? 'text-blue-600' : '' }}">
                        Articles
                    </a>
                    <a href="{{ route('about') }}" class="block px-3 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 {{ request()->routeIs('about') ? 'text-blue-600' : '' }}">
                        About
                    </a>
                    
                    <!-- Mobile Search -->
                    <form action="{{ route('search') }}" method="GET" class="px-3 pt-2">
                        <div class="flex">
                            <input 
                                type="text" 
                                name="q" 
                                value="{{ request('q') }}"
                                placeholder="Search content..." 
                                class="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-l-md focus:ring-blue-500 focus:border-blue-500"
                            >
                            <button type="submit" class="px-3 py-2 bg-blue-600 text-white text-sm border border-blue-600 rounded-r-md hover:bg-blue-700">
                                Search
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <!-- Flash Messages -->
    @if (session('success'))
        <div class="bg-green-50 border-l-4 border-green-400 p-4 mb-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-green-700">{{ session('success') }}</p>
                </div>
            </div>
        </div>
    @endif

    @if (session('error'))
        <div class="bg-red-50 border-l-4 border-red-400 p-4 mb-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-red-700">{{ session('error') }}</p>
                </div>
            </div>
        </div>
    @endif

    @if (session('warning'))
        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-yellow-700">{{ session('warning') }}</p>
                </div>
            </div>
        </div>
    @endif

    <!-- Main Content -->
    <main class="min-h-screen">
        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200 mt-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- About -->
                <div>
                    <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wider mb-4">About VerifySource</h3>
                    <p class="text-gray-600 text-sm">
                        An open-source platform for tracing content origins and verifying information authenticity. 
                        Help combat misinformation through transparent content verification.
                    </p>
                </div>
                
                <!-- Links -->
                <div>
                    <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wider mb-4">Quick Links</h3>
                    <ul class="space-y-2">
                        <li><a href="{{ route('home') }}" class="text-gray-600 hover:text-gray-900 text-sm">Verify Content</a></li>
                        <li><a href="{{ route('sources.index') }}" class="text-gray-600 hover:text-gray-900 text-sm">Browse Sources</a></li>
                        <li><a href="{{ route('articles.index') }}" class="text-gray-600 hover:text-gray-900 text-sm">Browse Articles</a></li>
                        <li><a href="{{ route('about') }}" class="text-gray-600 hover:text-gray-900 text-sm">About & API</a></li>
                    </ul>
                </div>
                
                <!-- Open Source -->
                <div>
                    <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wider mb-4">Open Source</h3>
                    <ul class="space-y-2">
                        <li><a href="#" class="text-gray-600 hover:text-gray-900 text-sm">GitHub Repository</a></li>
                        <li><a href="#" class="text-gray-600 hover:text-gray-900 text-sm">Documentation</a></li>
                        <li><a href="#" class="text-gray-600 hover:text-gray-900 text-sm">Contribute</a></li>
                        <li><a href="#" class="text-gray-600 hover:text-gray-900 text-sm">MIT License</a></li>
                    </ul>
                </div>
            </div>
            
            <div class="border-t border-gray-200 mt-8 pt-8">
                <div class="flex flex-col md:flex-row justify-between items-center">
                    <p class="text-gray-600 text-sm">
                        © {{ date('Y') }} VerifySource. Open-source content verification platform.
                    </p>
                    <div class="flex items-center space-x-4 mt-4 md:mt-0">
                        <span class="text-gray-500 text-xs">Built with</span>
                        <div class="flex items-center space-x-2 text-xs text-gray-600">
                            <span>Laravel</span>
                            <span>•</span>
                            <span>Livewire</span>
                            <span>•</span>
                            <span>Tailwind CSS</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <!-- Mobile Menu Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuButton = document.querySelector('.mobile-menu-button');
            const mobileMenu = document.querySelector('.mobile-menu');
            
            if (mobileMenuButton && mobileMenu) {
                mobileMenuButton.addEventListener('click', function() {
                    mobileMenu.classList.toggle('hidden');
                });
            }
        });
    </script>

    @livewireScripts
    @stack('scripts')
</body>
</html>