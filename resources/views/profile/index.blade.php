@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-900 py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Profile Header -->
        <div class="bg-gray-800 rounded-lg border border-gray-700 p-8 mb-8">
            <div class="flex items-center space-x-6">
                <!-- Avatar -->
                <div class="w-24 h-24 bg-gradient-to-r from-blue-500 to-purple-500 rounded-full flex items-center justify-center">
                    <span class="text-white text-3xl font-bold">{{ substr($user->name, 0, 1) }}</span>
                </div>

                <!-- User Info -->
                <div class="flex-1">
                    <h1 class="text-3xl font-bold text-white mb-2">{{ $user->name }}</h1>
                    <p class="text-gray-400 mb-4">{{ $user->email }}</p>

                    <div class="flex items-center space-x-6 text-sm">
                        <div class="flex items-center space-x-2">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a2 2 0 012-2h4a2 2 0 012 2v4m-6 0h12l-1 10a2 2 0 01-2 2H9a2 2 0 01-2-2L6 7h2z"></path>
                            </svg>
                            <span class="text-gray-400">Member since {{ $stats['member_since']->format('F Y') }}</span>
                        </div>

                        @if($company)
                            <div class="flex items-center space-x-2">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h1a1 1 0 011 1v5m-4 0h4"></path>
                                </svg>
                                <span class="text-gray-400">{{ $company->name }}</span>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Edit Button -->
                <div>
                    <a href="{{ route('company.profile') }}"
                       class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition-colors">
                        Edit Profile
                    </a>
                </div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Games Created -->
            <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-blue-600 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h1m4 0h1m-6 4h.01M16 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-bold text-white">{{ $stats['games_created'] }}</p>
                        <p class="text-gray-400 text-sm">Games Created</p>
                    </div>
                </div>
            </div>

            <!-- Messages Sent -->
            <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-purple-600 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-bold text-white">{{ $stats['messages_sent'] }}</p>
                        <p class="text-gray-400 text-sm">Messages Sent</p>
                    </div>
                </div>
            </div>

            <!-- Credits Used -->
            <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-green-600 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-bold text-white">{{ number_format($stats['credits_used']) }}</p>
                        <p class="text-gray-400 text-sm">Credits Used</p>
                    </div>
                </div>
            </div>

            <!-- Account Status -->
            <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-yellow-600 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-bold text-white">Active</p>
                        <p class="text-gray-400 text-sm">Account Status</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Recent Games -->
            <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
                <h3 class="text-xl font-semibold text-white mb-6">Recent Games</h3>

                <div class="space-y-4">
                    @if($stats['games_created'] > 0)
                        <!-- Placeholder for recent games - you can populate this with actual data -->
                        <div class="flex items-center space-x-4 p-3 bg-gray-700 rounded-lg">
                            <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                            <div class="flex-1">
                                <p class="text-white font-medium">Recent PlayCanvas Game</p>
                                <p class="text-gray-400 text-sm">2 hours ago</p>
                            </div>
                        </div>
                    @else
                        <div class="text-center py-8">
                            <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h1m4 0h1m-6 4h.01M16 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <p class="text-gray-400">No games created yet</p>
                            <a href="{{ route('chat') }}" class="text-blue-400 hover:text-blue-300 text-sm">Create your first game →</a>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Account Settings -->
            <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
                <h3 class="text-xl font-semibold text-white mb-6">Quick Settings</h3>

                <div class="space-y-4">
                    <a href="{{ route('settings') }}"
                       class="flex items-center justify-between p-3 bg-gray-700 rounded-lg hover:bg-gray-600 transition-colors">
                        <div class="flex items-center space-x-3">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                            <span class="text-white">AI Settings</span>
                        </div>
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </a>

                    <a href="{{ route('company.billing') }}"
                       class="flex items-center justify-between p-3 bg-gray-700 rounded-lg hover:bg-gray-600 transition-colors">
                        <div class="flex items-center space-x-3">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                            </svg>
                            <span class="text-white">Billing & Credits</span>
                        </div>
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </a>

                    <a href="{{ route('company.profile') }}"
                       class="flex items-center justify-between p-3 bg-gray-700 rounded-lg hover:bg-gray-600 transition-colors">
                        <div class="flex items-center space-x-3">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                            <span class="text-white">Edit Profile</span>
                        </div>
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
