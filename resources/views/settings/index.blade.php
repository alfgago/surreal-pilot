@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-900 py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-white">Settings</h1>
            <p class="text-gray-400 mt-1">Manage your AI preferences and app settings</p>
        </div>

        @if(session('success'))
            <div class="bg-green-900 border border-green-700 text-green-100 px-4 py-3 rounded-lg mb-8">
                {{ session('success') }}
            </div>
        @endif

        <form method="POST" action="{{ route('settings.update') }}" class="space-y-8">
            @csrf
            @method('PATCH')

            <!-- AI Provider Settings -->
            <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
                <h2 class="text-xl font-semibold text-white mb-6">AI Provider Settings</h2>

                <!-- Default Provider -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-300 mb-3">Default AI Provider</label>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach($providers as $key => $provider)
                            <div class="relative">
                                <input type="radio" name="default_provider" value="{{ $key }}"
                                       id="provider_{{ $key }}"
                                       class="sr-only peer"
                                       {{ $settings['default_provider'] === $key ? 'checked' : '' }}>
                                <label for="provider_{{ $key }}"
                                       class="flex items-center p-4 bg-gray-700 rounded-lg border-2 border-gray-600 cursor-pointer hover:bg-gray-600 peer-checked:border-blue-500 peer-checked:bg-blue-900 transition-all">
                                    <div class="flex-1">
                                        <div class="font-medium text-white">{{ $provider['name'] }}</div>
                                        <div class="text-sm text-gray-400">{{ $provider['description'] }}</div>
                                        @if($provider['requires_key'])
                                            <div class="text-xs text-yellow-400 mt-1">Requires API key</div>
                                        @endif
                                    </div>
                                    <div class="ml-4">
                                        <div class="w-4 h-4 border-2 border-gray-400 rounded-full peer-checked:border-blue-500 peer-checked:bg-blue-500"></div>
                                    </div>
                                </label>
                            </div>
                        @endforeach
                    </div>
                    @error('default_provider')
                        <p class="text-red-400 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- API Keys Management -->
                <div class="border-t border-gray-700 pt-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-medium text-white">API Keys</h3>
                            <p class="text-gray-400 text-sm">Configure your AI provider API keys</p>
                        </div>
                        <a href="{{ route('company.provider-settings') }}"
                           class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors">
                            Manage API Keys
                        </a>
                    </div>
                </div>
            </div>

            <!-- Chat Behavior -->
            <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
                <h2 class="text-xl font-semibold text-white mb-6">Chat Behavior</h2>

                <!-- Temperature/Creativity -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-300 mb-3">
                        Creativity Level
                        <span class="text-xs text-gray-400">(Temperature: <span id="temp-value">{{ $settings['temperature'] }}</span>)</span>
                    </label>
                    <input type="range" name="temperature"
                           min="0" max="1" step="0.1"
                           value="{{ $settings['temperature'] }}"
                           id="temperature-slider"
                           class="w-full h-2 bg-gray-600 rounded-lg appearance-none cursor-pointer">
                    <div class="flex justify-between text-xs text-gray-400 mt-1">
                        <span>Focused (0.0)</span>
                        <span>Balanced (0.5)</span>
                        <span>Creative (1.0)</span>
                    </div>
                    @error('temperature')
                        <p class="text-red-400 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Chat Options -->
                <div class="space-y-4">
                    <label class="flex items-center space-x-3">
                        <input type="checkbox" name="stream_responses" value="1"
                               {{ $settings['stream_responses'] ? 'checked' : '' }}
                               class="rounded bg-gray-700 border-gray-600 text-blue-600 focus:ring-blue-500">
                        <div>
                            <span class="text-sm font-medium text-gray-300">Stream responses</span>
                            <p class="text-xs text-gray-400">Show AI responses in real-time as they're generated</p>
                        </div>
                    </label>

                    <label class="flex items-center space-x-3">
                        <input type="checkbox" name="save_history" value="1"
                               {{ $settings['save_history'] ? 'checked' : '' }}
                               class="rounded bg-gray-700 border-gray-600 text-blue-600 focus:ring-blue-500">
                        <div>
                            <span class="text-sm font-medium text-gray-300">Save chat history</span>
                            <p class="text-xs text-gray-400">Keep a record of your conversations for future reference</p>
                        </div>
                    </label>

                    <label class="flex items-center space-x-3">
                        <input type="checkbox" name="notifications" value="1"
                               {{ $settings['notifications'] ? 'checked' : '' }}
                               class="rounded bg-gray-700 border-gray-600 text-blue-600 focus:ring-blue-500">
                        <div>
                            <span class="text-sm font-medium text-gray-300">Enable notifications</span>
                            <p class="text-xs text-gray-400">Get notified about important updates and messages</p>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Account & Privacy -->
            <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
                <h2 class="text-xl font-semibold text-white mb-6">Account & Privacy</h2>

                <div class="space-y-4">
                    <!-- Account Management Links -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <a href="{{ route('company.profile') }}"
                           class="flex items-center justify-between p-4 bg-gray-700 rounded-lg hover:bg-gray-600 transition-colors">
                            <div class="flex items-center space-x-3">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                                <span class="text-white font-medium">Edit Profile</span>
                            </div>
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </a>

                        <a href="{{ route('company.billing') }}"
                           class="flex items-center justify-between p-4 bg-gray-700 rounded-lg hover:bg-gray-600 transition-colors">
                            <div class="flex items-center space-x-3">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                </svg>
                                <span class="text-white font-medium">Billing & Credits</span>
                            </div>
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </a>

                        <a href="{{ route('company.settings') }}"
                           class="flex items-center justify-between p-4 bg-gray-700 rounded-lg hover:bg-gray-600 transition-colors">
                            <div class="flex items-center space-x-3">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h1a1 1 0 011 1v5m-4 0h4"></path>
                                </svg>
                                <span class="text-white font-medium">Company Settings</span>
                            </div>
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </a>

                        <button type="button"
                                onclick="clearAllData()"
                                class="flex items-center justify-between p-4 bg-red-900 rounded-lg hover:bg-red-800 transition-colors">
                            <div class="flex items-center space-x-3">
                                <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                                <span class="text-white font-medium">Clear All Data</span>
                            </div>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Save Button -->
            <div class="flex justify-end">
                <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-8 py-3 rounded-lg font-medium transition-colors">
                    Save Settings
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Temperature slider
    const tempSlider = document.getElementById('temperature-slider');
    const tempValue = document.getElementById('temp-value');

    tempSlider.addEventListener('input', function() {
        tempValue.textContent = this.value;
    });

    // Clear all data function
    window.clearAllData = function() {
        if (confirm('Are you sure you want to clear all your chat history and settings? This action cannot be undone.')) {
            // Clear localStorage
            localStorage.clear();

            // Clear sessionStorage
            sessionStorage.clear();

            alert('All local data has been cleared.');
        }
    };
});
</script>
@endpush
