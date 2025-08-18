@extends('desktop.layout')

@section('content')
<div class="container mx-auto px-6 py-8">
    <!-- Welcome Section -->
    <div class="text-center mb-12">
        <h1 class="text-4xl font-bold text-white mb-4">Welcome to SurrealPilot Desktop</h1>
        <p class="text-xl text-gray-300 mb-8">AI Copilot for Unreal Engine Development</p>
        
        <div class="flex justify-center space-x-4">
            <a href="{{ route('desktop.chat') }}" 
               class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg transition duration-200">
                Start Chatting
            </a>
            <a href="{{ route('desktop.settings') }}" 
               class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-3 px-6 rounded-lg transition duration-200">
                Configure Settings
            </a>
        </div>
    </div>

    <!-- Features Grid -->
    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8 mb-12">
        <!-- AI Integration -->
        <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
            <div class="text-blue-400 mb-4">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                </svg>
            </div>
            <h3 class="text-lg font-semibold text-white mb-2">Multi-Provider AI</h3>
            <p class="text-gray-400">Support for OpenAI, Anthropic, Gemini, and local Ollama models.</p>
        </div>

        <!-- UE Integration -->
        <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
            <div class="text-green-400 mb-4">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
            </div>
            <h3 class="text-lg font-semibold text-white mb-2">Unreal Engine Plugin</h3>
            <p class="text-gray-400">Seamless integration with UE through our companion plugin.</p>
        </div>

        <!-- Local Processing -->
        <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
            <div class="text-purple-400 mb-4">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                </svg>
            </div>
            <h3 class="text-lg font-semibold text-white mb-2">Local & Secure</h3>
            <p class="text-gray-400">Run locally with your own API keys or connect to SaaS.</p>
        </div>
    </div>

    <!-- Status Cards -->
    <div class="grid md:grid-cols-2 gap-8">
        <!-- Server Status -->
        <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
            <h3 class="text-lg font-semibold text-white mb-4">Server Status</h3>
            <div class="space-y-3">
                <div class="flex justify-between items-center">
                    <span class="text-gray-400">Status:</span>
                    <span class="text-green-400 font-medium">Running</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-gray-400">Port:</span>
                    <span class="text-white font-medium" id="status-port">Loading...</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-gray-400">URL:</span>
                    <span class="text-blue-400 font-medium" id="status-url">Loading...</span>
                </div>
            </div>
        </div>

        <!-- AI Provider Status -->
        <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
            <h3 class="text-lg font-semibold text-white mb-4">AI Provider Status</h3>
            <div class="space-y-3">
                <div class="flex justify-between items-center">
                    <span class="text-gray-400">Preferred:</span>
                    <span class="text-white font-medium" id="preferred-provider">Loading...</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-gray-400">Ollama:</span>
                    <span class="text-yellow-400 font-medium" id="ollama-status">Checking...</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-gray-400">SaaS:</span>
                    <span class="text-yellow-400 font-medium" id="saas-status">Checking...</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', async function() {
    // Update server status
    try {
        const response = await fetch('/api/desktop/server-info');
        const data = await response.json();
        
        document.getElementById('status-port').textContent = data.port;
        document.getElementById('status-url').textContent = data.url;
    } catch (error) {
        console.error('Failed to load server info:', error);
    }
    
    // Update provider status
    try {
        const response = await fetch('/api/providers');
        const data = await response.json();
        
        document.getElementById('preferred-provider').textContent = data.default || 'None';
    } catch (error) {
        console.error('Failed to load provider info:', error);
    }
    
    // Test Ollama connection
    try {
        const response = await fetch('/api/desktop/test-connection', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.csrfToken
            },
            body: JSON.stringify({ service: 'ollama' })
        });
        const data = await response.json();
        
        const statusEl = document.getElementById('ollama-status');
        if (data.status === 'connected') {
            statusEl.textContent = 'Connected';
            statusEl.className = 'text-green-400 font-medium';
        } else {
            statusEl.textContent = 'Not Available';
            statusEl.className = 'text-red-400 font-medium';
        }
    } catch (error) {
        document.getElementById('ollama-status').textContent = 'Error';
        document.getElementById('ollama-status').className = 'text-red-400 font-medium';
    }
    
    // Test SaaS connection
    try {
        const response = await fetch('/api/desktop/test-connection', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.csrfToken
            },
            body: JSON.stringify({ service: 'saas' })
        });
        const data = await response.json();
        
        const statusEl = document.getElementById('saas-status');
        if (data.status === 'connected') {
            statusEl.textContent = 'Connected';
            statusEl.className = 'text-green-400 font-medium';
        } else {
            statusEl.textContent = 'Not Configured';
            statusEl.className = 'text-yellow-400 font-medium';
        }
    } catch (error) {
        document.getElementById('saas-status').textContent = 'Error';
        document.getElementById('saas-status').className = 'text-red-400 font-medium';
    }
});
</script>
@endpush