@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto px-6 py-8">
    <h1 class="text-2xl font-bold text-white mb-8">API Provider Settings</h1>

    <!-- API Key Management -->
    <div class="bg-gray-800 border border-gray-700 rounded p-6 mb-8">
        <h2 class="text-xl font-semibold text-white mb-4">API Keys</h2>
        <p class="text-gray-300 mb-6">Manage your AI provider API keys for {{ $company->name }}.</p>

        <form method="POST" action="/company/provider-settings" class="space-y-6">
            @csrf
            @method('PATCH')

            <!-- OpenAI -->
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">OpenAI API Key</label>
                <input type="password" name="openai_api_key" 
                       class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white placeholder-gray-400"
                       placeholder="sk-...">
                <p class="text-xs text-gray-400 mt-1">Used for GPT models</p>
            </div>

            <!-- Anthropic -->
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Anthropic API Key</label>
                <input type="password" name="anthropic_api_key" 
                       class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white placeholder-gray-400"
                       placeholder="sk-ant-...">
                <p class="text-xs text-gray-400 mt-1">Used for Claude models</p>
            </div>

            <!-- Google -->
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Google AI API Key</label>
                <input type="password" name="google_api_key" 
                       class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white placeholder-gray-400"
                       placeholder="AI...">
                <p class="text-xs text-gray-400 mt-1">Used for Gemini models</p>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded">
                    Save API Keys
                </button>
            </div>
        </form>
    </div>

    <!-- Provider Status -->
    <div class="bg-gray-800 border border-gray-700 rounded p-6">
        <h3 class="text-lg font-semibold text-white mb-4">Provider Status</h3>
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-gray-300">OpenAI</span>
                <span class="text-green-400">Connected</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-gray-300">Anthropic</span>
                <span class="text-yellow-400">Not configured</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-gray-300">Google AI</span>
                <span class="text-yellow-400">Not configured</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-gray-300">Ollama (Local)</span>
                <span class="text-green-400">Available</span>
            </div>
        </div>
    </div>
</div>
@endsection