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
</head>
<body class="font-sans antialiased h-full bg-gray-900">
    <!-- Simple Landing Page -->
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
</body>
</html>
