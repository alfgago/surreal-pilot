<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'SurrealPilot') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Scripts -->
    @if(file_exists(public_path('build/manifest.json')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif

    <!-- Additional Head Content -->
    @stack('head')
</head>
<body class="font-sans antialiased h-full bg-gray-900">
    <div id="app" class="h-full">
        @auth
            <!-- Desktop Navigation -->
            <nav class="hidden md:flex bg-gray-800 border-b border-gray-700 px-6 py-4 items-center justify-between">
                <div class="flex items-center space-x-6">
                    <!-- Logo -->
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-gradient-to-r from-purple-500 to-pink-500 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                        </div>
                        <span class="ml-2 text-xl font-bold text-white">SurrealPilot</span>
                    </div>

                    <!-- Main Navigation -->
                    <div class="flex space-x-1">
                        <a href="/chat" class="nav-link {{ request()->is('chat') ? 'active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                            </svg>
                            Chat
                        </a>
                        <a href="/games" class="nav-link {{ request()->is('games*') ? 'active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h1m4 0h1m-6 4h.01M16 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            My Games
                        </a>
                        <a href="/profile" class="nav-link {{ request()->is('profile*') ? 'active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                            Profile
                        </a>
                        <a href="/settings" class="nav-link {{ request()->is('settings*') ? 'active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                            Settings
                        </a>
                    </div>
                </div>

                <!-- User Menu -->
                <div class="flex items-center space-x-4">
                    <!-- Credits Display -->
                    <div class="flex items-center space-x-2 text-sm">
                        <div class="w-2 h-2 bg-green-500 rounded-full"></div>
                        <span class="text-gray-300">Credits:</span>
                        <span id="header-credits" class="text-green-400 font-medium">Loading...</span>
                    </div>

                    <!-- User Dropdown -->
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" class="flex items-center space-x-2 text-gray-300 hover:text-white transition-colors">
                            <div class="w-8 h-8 bg-gradient-to-r from-blue-500 to-purple-500 rounded-full flex items-center justify-center">
                                <span class="text-white text-sm font-medium">{{ substr(auth()->user()->name, 0, 1) }}</span>
                            </div>
                            <span class="hidden sm:block">{{ auth()->user()->name }}</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>

                        <div x-show="open" @click.away="open = false" x-transition
                             class="absolute right-0 mt-2 w-48 bg-gray-800 rounded-md shadow-lg py-1 z-50">
                            <a href="/company/billing" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-700">
                                Billing & Credits
                            </a>
                            <a href="/company/settings" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-700">
                                Company Settings
                            </a>
                            <div class="border-t border-gray-700 my-1"></div>
                            <form method="POST" action="/logout">
                                @csrf
                                <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-gray-300 hover:bg-gray-700">
                                    Sign Out
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Mobile Bottom Navigation -->
            <div class="md:hidden fixed bottom-0 left-0 right-0 bg-gray-800 border-t border-gray-700 z-50">
                <div class="flex justify-around py-2">
                    <a href="/chat" class="mobile-nav-item {{ request()->is('chat') ? 'active' : '' }}">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                        </svg>
                        <span class="text-xs mt-1">Chat</span>
                    </a>
                    <a href="/games" class="mobile-nav-item {{ request()->is('games*') ? 'active' : '' }}">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h1m4 0h1m-6 4h.01M16 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span class="text-xs mt-1">Games</span>
                    </a>
                    <a href="/profile" class="mobile-nav-item {{ request()->is('profile*') ? 'active' : '' }}">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                        <span class="text-xs mt-1">Profile</span>
                    </a>
                    <a href="/settings" class="mobile-nav-item {{ request()->is('settings*') ? 'active' : '' }}">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        <span class="text-xs mt-1">Settings</span>
                    </a>
                </div>
            </div>

            <!-- Main Content -->
            <main class="flex-1 {{ request()->is('chat') ? 'h-screen' : 'min-h-screen pb-16 md:pb-0' }}">
                @yield('content')
            </main>
        @else
            <!-- Guest Landing -->
            <div class="min-h-screen bg-gradient-to-br from-gray-900 via-purple-900 to-gray-900 flex items-center justify-center">
                <div class="max-w-md w-full space-y-8 p-8">
                    <div class="text-center">
                        <div class="mx-auto w-16 h-16 bg-gradient-to-r from-purple-500 to-pink-500 rounded-2xl flex items-center justify-center mb-6">
                            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                        </div>
                        <h2 class="text-3xl font-bold text-white mb-2">Welcome to SurrealPilot</h2>
                        <p class="text-gray-300 mb-8">AI-powered game development with Claude Sonnet 4</p>
                    </div>

                    <div class="space-y-4">
                        <a href="/register"
                           class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 transition-all duration-200">
                            Create Account
                        </a>
                        <a href="/login"
                           class="w-full flex justify-center py-3 px-4 border border-gray-600 rounded-lg shadow-sm text-sm font-medium text-gray-300 bg-gray-800 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-all duration-200">
                            Sign In
                        </a>
                    </div>

                    <div class="text-center text-sm text-gray-400">
                        <p>Create games with PlayCanvas & Unreal Engine</p>
                    </div>
                </div>
            </div>
        @endauth
    </div>

    <!-- Global Scripts -->
    <script>
        window.csrfToken = '{{ csrf_token() }}';
        window.currentUser = @json(auth()->user());
        @auth
            window.currentCompanyId = {{ auth()->user()->currentCompany?->id ?? 'null' }};
        @endauth
    </script>

    <!-- Alpine.js -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- Custom Scripts -->
    @stack('scripts')

    <style>
        .nav-link {
            @apply flex items-center space-x-2 px-3 py-2 rounded-md text-sm font-medium text-gray-300 hover:text-white hover:bg-gray-700 transition-colors;
        }

        .nav-link.active {
            @apply text-white bg-gray-700;
        }

        .mobile-nav-item {
            @apply flex flex-col items-center justify-center py-2 px-4 text-gray-400 hover:text-white transition-colors;
        }

        .mobile-nav-item.active {
            @apply text-blue-400;
        }

        .mobile-nav-item.active svg {
            @apply text-blue-400;
        }
    </style>
</body>
</html>
